<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\ParcelIncident;
use App\Models\User;
use App\Services\ParcelIncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParcelExceptionController extends Controller
{
    public function __construct(
        protected ParcelIncidentService $incidentService
    ) {}

    /**
     * Centralized Logistics -> Parcel Exceptions dashboard backed by parcel_incidents.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->logisticsCompanyId();

        $baseScope = ParcelIncident::query()
            ->where(function ($q) use ($companyId) {
                $q->where('logistics_company_id', $companyId)
                    ->orWhereHas('manifest', fn ($mq) => $mq->where('sender_logistics_id', $companyId)->orWhere('receiver_logistics_id', $companyId))
                    ->orWhereHas('hub', fn ($hq) => $hq->where('logistics_company_id', $companyId))
                    ->orWhereHas('actualHub', fn ($ahq) => $ahq->where('logistics_company_id', $companyId));
            });

        $query = (clone $baseScope)
            ->with(['order', 'manifest', 'hub', 'expectedHub', 'actualHub', 'reporter', 'assignee'])
            ->latest('reported_at')
            ->latest('id');

        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES);
            } else {
                $query->where('investigation_status', $status);
            }
        }

        if ($type = $request->query('type')) {
            $query->where('incident_type', $type);
        }

        if ($stage = $request->query('stage')) {
            $query->where('source_stage', $stage);
        }

        if ($hubId = $request->query('hub_id')) {
            $query->where(function ($q) use ($hubId) {
                $q->where('hub_id', $hubId)->orWhere('actual_hub_id', $hubId)->orWhere('expected_hub_id', $hubId);
            });
        }

        if ($request->boolean('quarantined')) {
            $query->quarantined();
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('incident_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('manifest', fn ($mq) => $mq->where('manifest_number', 'like', "%{$search}%"));
            });
        }

        $incidents = $query->paginate(20)->withQueryString();

        $allForKpis = (clone $baseScope)->get();
        $kpis = [
            'active' => $allForKpis->filter(fn ($i) => $i->isUnresolved())->count(),
            'missing' => $allForKpis->filter(fn ($i) => $i->isUnresolved() && $i->incident_type === ParcelIncident::TYPE_MISSING)->count(),
            'quarantined' => $allForKpis->filter(fn ($i) => $i->isQuarantined() && $i->isUnresolved())->count(),
            'wrong_hub' => $allForKpis->filter(fn ($i) => $i->isUnresolved() && $i->incident_type === ParcelIncident::TYPE_WRONG_HUB)->count(),
            'overdue' => $allForKpis->filter(fn ($i) => $i->isOverdue())->count(),
            'resolved' => $allForKpis->filter(fn ($i) => $i->isResolved())->count(),
        ];

        $hubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        return view('logistics.exceptions.index', compact('incidents', 'kpis', 'hubs'));
    }

    /**
     * Full investigation, custody, quarantine, recovery, reroute & disposition workspace.
     */
    public function show(Request $request, ParcelIncident $incident): View
    {
        $this->incidentService->authorizeLogisticsOperator($request->user(), $incident);

        $incident->load([
            'order.buyer',
            'order.originHub',
            'order.destinationHub',
            'order.currentHub',
            'manifest.originHub',
            'manifest.destinationHub',
            'manifest.assignedCourier',
            'manifestItem',
            'hub',
            'expectedHub',
            'actualHub',
            'reroutedToHub',
            'reporter',
            'assignee',
            'quarantinedBy',
            'recoveredBy',
            'resolver',
            'closedBy',
            'events.actor',
        ]);

        $companyId = $request->user()->logisticsCompanyId();
        $investigators = User::where(function ($q) use ($companyId) {
            $q->where('id', $companyId)->orWhere('role', 'admin');
        })->where('status', 'active')->orderBy('first_name')->get();

        $allActiveHubs = Hub::where('is_active', true)->orderBy('name')->get();

        return view('logistics.exceptions.show', compact('incident', 'investigators', 'allActiveHubs'));
    }

    public function assign(Request $request, ParcelIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $investigator = User::findOrFail((int) $validated['assigned_to']);
        $this->incidentService->assignInvestigator($incident, $investigator, $request->user(), $validated['notes'] ?? null);

        return back()->with('status', "Assigned {$incident->incident_number} to {$investigator->full_name}.");
    }

    public function addEvent(Request $request, ParcelIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1500'],
            'investigation_status' => ['nullable', 'string', Rule::in([
                ParcelIncident::STATUS_OPEN,
                ParcelIncident::STATUS_UNDER_INVESTIGATION,
                ParcelIncident::STATUS_AWAITING_EVIDENCE,
                ParcelIncident::STATUS_AWAITING_DISPOSITION,
            ])],
            'location' => ['nullable', 'string', 'max:255'],
            'evidence_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $this->incidentService->addInvestigationEvent(
            $incident,
            $request->user(),
            $validated['notes'],
            $validated['investigation_status'] ?? null,
            $validated['location'] ?? null,
            $request->file('evidence_photo')
        );

        return back()->with('status', "Added investigation log entry to {$incident->incident_number}.");
    }

    public function quarantine(Request $request, ParcelIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $this->incidentService->quarantineParcel(
            $incident,
            $request->user(),
            $validated['notes'],
            $validated['location'] ?? null
        );

        return back()->with('status', "Parcel {$incident->tracking_number} placed in hub quarantine (dispatch blocked).");
    }

    public function recover(Request $request, ParcelIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'recovery_location' => ['required', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:1500'],
            'confirm_physical_receipt' => ['nullable', 'boolean'],
            'recovery_evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $this->incidentService->recoverMissingParcel(
            $incident,
            $request->user(),
            $validated['recovery_location'],
            $validated['notes'],
            $request->file('recovery_evidence'),
            $request->boolean('confirm_physical_receipt', true)
        );

        return back()->with('status', "Recovery verification recorded for {$incident->incident_number}.");
    }

    public function applyDisposition(Request $request, ParcelIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'disposition' => ['required', 'string', Rule::in(array_keys(ParcelIncident::ALL_DISPOSITIONS))],
            'resolution_notes' => ['required', 'string', 'max:2000'],
            'close_immediately' => ['nullable', 'boolean'],
            'evidence_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $this->incidentService->applyDisposition(
            $incident,
            $request->user(),
            $validated['disposition'],
            $validated['resolution_notes'],
            $request->file('evidence_photo'),
            $request->boolean('close_immediately', false)
        );

        return back()->with('status', "Applied disposition '" . (ParcelIncident::ALL_DISPOSITIONS[$validated['disposition']] ?? $validated['disposition']) . "' to {$incident->incident_number}.");
    }

    public function reroute(Request $request, ParcelIncident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'target_hub_id' => ['required', 'integer', 'exists:hubs,id'],
            'override_destination_hub' => ['nullable', 'boolean'],
            'notes' => ['required', 'string', 'max:1500'],
        ]);

        $targetHub = Hub::findOrFail((int) $validated['target_hub_id']);

        $this->incidentService->rerouteParcel(
            $incident,
            $targetHub,
            $request->user(),
            $validated['notes'],
            $request->boolean('override_destination_hub', false)
        );

        return back()->with('status', "Parcel {$incident->tracking_number} rerouted to {$targetHub->name}.");
    }

    public function dispatchOverdueAlerts(Request $request): RedirectResponse
    {
        $sent = $this->incidentService->dispatchOverdueAlerts($request->user()->logisticsCompanyId());

        return back()->with('status', "Processed overdue SLA alerts ({$sent} notification(s) dispatched without duplicates).");
    }
}
