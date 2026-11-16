<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SafetyReport;
use App\Models\User;
use App\Services\AccountEnforcementService;
use App\Services\SafetyReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SafetyReportController extends Controller
{
    public function __construct(
        protected SafetyReportService $reportService,
        protected AccountEnforcementService $enforcementService
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasAdminPermission('review_reports'), 403);

        $status = $request->query('status', 'all');
        $category = $request->query('category');
        $priority = $request->query('priority');
        $search = $request->query('q');

        $query = SafetyReport::query()->with(['reporter', 'reportedUser', 'assignedAdmin', 'order', 'product']);

        if ($status !== 'all' && in_array($status, SafetyReport::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('reporter', fn ($rq) => $rq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $reports = $query->latest()->paginate(15)->withQueryString();

        $counts = ['all' => SafetyReport::count()];
        foreach (SafetyReport::STATUSES as $st) {
            $counts[$st] = SafetyReport::where('status', $st)->count();
        }

        return view('admin.safety.index', compact('reports', 'counts', 'status', 'category', 'priority', 'search'));
    }

    public function show(Request $request, SafetyReport $safetyReport): View
    {
        abort_unless($request->user()->hasAdminPermission('review_reports'), 403);

        $safetyReport->load([
            'reporter',
            'reportedUser.accountRestrictions',
            'assignedAdmin',
            'order.buyer',
            'order.seller.sellerProfile',
            'product',
            'parcelIncident',
            'dispute',
            'notes.author',
        ]);

        $admins = User::where('role', 'admin')->where('account_status', 'active')->get();

        return view('admin.safety.show', compact('safetyReport', 'admins'));
    }

    public function update(Request $request, SafetyReport $safetyReport): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('review_reports'), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(SafetyReport::STATUSES)],
            'priority' => ['required', Rule::in(SafetyReport::PRIORITIES)],
            'assigned_admin_id' => ['nullable', 'exists:users,id'],
            'resolution_summary' => ['nullable', 'string', 'max:2000'],
            'closed_reason' => ['nullable', 'string', 'max:1000'],
            'public_message' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['status'] === 'closed' && empty($validated['closed_reason'])) {
            return back()->withInput()->withErrors(['closed_reason' => 'A documented reason is required when closing a case.']);
        }

        $this->reportService->updateCase($safetyReport, $request->user(), $validated);

        return back()->with('status', "Safety case {$safetyReport->reference_number} updated.");
    }

    public function addNote(Request $request, SafetyReport $safetyReport): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('review_reports'), 403);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $this->reportService->addNote(
            $safetyReport,
            $request->user(),
            $validated['note'],
            (bool) ($validated['is_internal'] ?? true)
        );

        return back()->with('status', 'Investigation note recorded.');
    }

    public function applyRestriction(Request $request, SafetyReport $safetyReport): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('manage_users'), 403);
        abort_unless($safetyReport->reported_user_id !== null, 422, 'No reported user is linked to this case.');

        $validated = $request->validate([
            'restriction_type' => ['required', Rule::in(array_keys(\App\Models\AccountRestriction::TYPES))],
            'reason' => ['required', 'string', 'max:1000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $target = User::findOrFail($safetyReport->reported_user_id);
        abort_if($target->id === $request->user()->id, 422, 'Cannot restrict your own account.');

        $this->enforcementService->applyRestriction(
            $target,
            $request->user(),
            $validated['restriction_type'],
            $validated['reason'],
            $validated['duration_days'] ?? null,
            $safetyReport->id
        );

        return back()->with('status', "Enforcement action applied to {$target->full_name} and logged.");
    }

    public function downloadEvidence(Request $request, SafetyReport $safetyReport, int $index): StreamedResponse
    {
        abort_unless($request->user()->hasAdminPermission('review_reports'), 403);

        $paths = $safetyReport->evidence_paths ?? [];
        abort_unless(isset($paths[$index]), 404, 'Evidence file not found.');

        $path = $paths[$index];
        abort_unless(Storage::disk('local')->exists($path), 404, 'Evidence file missing from private storage.');

        return Storage::disk('local')->response($path);
    }
}
