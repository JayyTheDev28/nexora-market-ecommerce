<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RiskAlert;
use App\Services\RiskDetectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FraudAlertController extends Controller
{
    public function __construct(protected RiskDetectionService $riskService) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasAdminPermission('review_reports'), 403);

        $status = $request->query('status', 'all');
        $severity = $request->query('severity');

        $query = RiskAlert::query()->with(['user', 'assignedAdmin']);

        if ($status !== 'all' && in_array($status, RiskAlert::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($severity && in_array($severity, RiskAlert::SEVERITIES, true)) {
            $query->where('severity', $severity);
        }

        $alerts = $query->latest('detected_at')->paginate(15)->withQueryString();

        $counts = ['all' => RiskAlert::count()];
        foreach (RiskAlert::STATUSES as $st) {
            $counts[$st] = RiskAlert::where('status', $st)->count();
        }

        return view('admin.fraud_alerts.index', compact('alerts', 'counts', 'status', 'severity'));
    }

    public function scan(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('review_reports'), 403);

        $created = $this->riskService->runRulesScan($request->user());

        return back()->with('status', 'Rules-based risk scan completed. ' . count($created) . ' new alert(s) detected.');
    }

    public function review(Request $request, RiskAlert $riskAlert): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('review_reports'), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(RiskAlert::STATUSES)],
            'decision_notes' => ['required', 'string', 'max:1500'],
        ]);

        $old = ['status' => $riskAlert->status];

        $riskAlert->update([
            'status' => $validated['status'],
            'decision_notes' => trim($validated['decision_notes']),
            'assigned_admin_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        AuditLog::log(
            action: 'risk_alert_reviewed',
            auditable: $riskAlert,
            oldValues: $old,
            newValues: ['status' => $riskAlert->status],
            reason: $validated['decision_notes'],
            operatorId: $request->user()->id
        );

        return back()->with('status', "Risk alert #{$riskAlert->id} updated to " . ucfirst($riskAlert->status) . '.');
    }
}
