<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AccountRestriction;
use App\Models\SafetyReport;
use App\Services\AccountEnforcementService;
use App\Services\SafetyReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SafetyReportController extends Controller
{
    public function __construct(
        protected SafetyReportService $reportService,
        protected AccountEnforcementService $enforcementService
    ) {}

    public function index(Request $request): View
    {
        $reports = SafetyReport::where('reporter_id', $request->user()->id)
            ->with(['order', 'product', 'publicNotes.author'])
            ->latest()
            ->paginate(15);

        $restrictions = AccountRestriction::where('user_id', $request->user()->id)
            ->with('appeals')
            ->latest()
            ->get();

        return view('safety.index', compact('reports', 'restrictions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(SafetyReport::CATEGORIES))],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'subject_type' => ['nullable', 'string', 'max:40'],
            'subject_id' => ['nullable', 'integer'],
            'reported_user_id' => ['nullable', 'exists:users,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'parcel_incident_id' => ['nullable', 'exists:parcel_incidents,id'],
            'dispute_id' => ['nullable', 'exists:disputes,id'],
            'evidence.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $files = $request->file('evidence', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        $report = $this->reportService->submitReport($request->user(), $validated, $files);

        return back()->with('status', "Report {$report->reference_number} submitted to Admin Safety Center.");
    }

    public function submitAppeal(Request $request, AccountRestriction $restriction): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $this->enforcementService->submitAppeal($restriction, $request->user(), $validated['reason']);

        return back()->with('status', 'Your restriction appeal has been submitted for Admin review.');
    }
}
