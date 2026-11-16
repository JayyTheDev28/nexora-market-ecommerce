<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AccountRestriction;
use App\Models\SafetyReport;
use App\Services\AccountEnforcementService;
use App\Services\SafetyReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SafetyReportController extends Controller
{
    public function __construct(
        protected SafetyReportService $reportService,
        protected AccountEnforcementService $enforcementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $reports = SafetyReport::where('reporter_id', $user->id)
            ->with(['order:id,order_number,tracking_number', 'product:id,name', 'publicNotes.author'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (SafetyReport $r) => $this->formatReport($r)),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
            ],
            'message' => 'Safety reports retrieved.',
        ]);
    }

    public function store(Request $request): JsonResponse
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

        return response()->json([
            'data' => $this->formatReport($report),
            'message' => "Report {$report->reference_number} submitted for Admin review.",
        ], 201);
    }

    public function show(Request $request, SafetyReport $safetyReport): JsonResponse
    {
        abort_unless((int) $safetyReport->reporter_id === (int) $request->user()->id, 403);

        $safetyReport->load(['order:id,order_number,tracking_number', 'product:id,name', 'publicNotes.author']);

        return response()->json([
            'data' => $this->formatReport($safetyReport),
            'message' => 'Report details retrieved.',
        ]);
    }

    public function restrictions(Request $request): JsonResponse
    {
        $restrictions = AccountRestriction::where('user_id', $request->user()->id)
            ->with('appeals')
            ->latest()
            ->get()
            ->map(fn (AccountRestriction $r) => [
                'id' => $r->id,
                'restriction_type' => $r->restriction_type,
                'type_label' => $r->type_label,
                'reason' => $r->reason,
                'status' => $r->status,
                'starts_at' => $r->starts_at?->toIso8601String(),
                'expires_at' => $r->expires_at?->toIso8601String(),
                'appeals' => $r->appeals->map(fn ($a) => [
                    'id' => $a->id,
                    'status' => $a->status,
                    'reason' => $a->reason,
                    'admin_notes' => $a->admin_notes,
                    'created_at' => $a->created_at?->toIso8601String(),
                ]),
            ]);

        return response()->json([
            'data' => $restrictions,
            'message' => 'Account restrictions retrieved.',
        ]);
    }

    public function appeal(Request $request, AccountRestriction $restriction): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $appeal = $this->enforcementService->submitAppeal($restriction, $request->user(), $validated['reason']);

        return response()->json([
            'data' => [
                'id' => $appeal->id,
                'status' => $appeal->status,
                'reason' => $appeal->reason,
            ],
            'message' => 'Appeal submitted for Admin review.',
        ], 201);
    }

    private function formatReport(SafetyReport $r): array
    {
        return [
            'id' => $r->id,
            'reference_number' => $r->reference_number,
            'category' => $r->category,
            'category_label' => $r->category_label,
            'title' => $r->title,
            'description' => $r->description,
            'status' => $r->status,
            'priority' => $r->priority,
            'order_number' => $r->order?->order_number,
            'product_name' => $r->product?->name,
            'resolution_summary' => $r->resolution_summary,
            'closed_reason' => $r->closed_reason,
            'public_updates' => $r->relationLoaded('publicNotes')
                ? $r->publicNotes->map(fn ($n) => [
                    'id' => $n->id,
                    'note' => $n->note,
                    'author' => $n->author?->full_name ?? 'Admin',
                    'created_at' => $n->created_at?->toIso8601String(),
                ])->values()
                : [],
            'created_at' => $r->created_at?->toIso8601String(),
            'resolved_at' => $r->resolved_at?->toIso8601String(),
        ];
    }
}
