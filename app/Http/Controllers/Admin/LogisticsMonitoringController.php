<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\Hub;
use App\Models\InterCompanyShippingAllocation;
use App\Models\LogisticsAdminRemittance;
use App\Models\Order;
use App\Models\ParcelIncident;
use App\Models\RiderEarning;
use App\Models\TransferManifest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogisticsMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasAdminPermission('manage_logistics'), 403);

        $companyFilter = $request->query('company_id');
        $statusFilter = $request->query('status');
        $trackingSearch = trim((string) $request->query('tracking', ''));

        $stats = [
            'activeCompanies' => User::whereIn('role', ['logistics', 'sorting_center'])
                ->where('approval_status', 'approved')
                ->where('account_status', 'active')
                ->count(),
            'activeHubs' => Hub::where('is_active', true)->count(),
            'activeTransfers' => TransferManifest::whereIn('status', TransferManifest::ACTIVE_STATUSES)->count(),
            'inTransitParcels' => Order::whereIn('status', ['in_transit_hub', 'out_for_delivery'])->count(),
            'deliveredParcels' => Order::whereIn('status', ['delivered', 'completed'])->count(),
            'delayedParcels' => Order::whereIn('status', ['picked_up', 'received_at_hub', 'sorted', 'in_transit_hub'])
                ->where('updated_at', '<', now()->subHours(48))
                ->count(),
            'missingParcels' => ParcelIncident::where('incident_type', 'missing')->where('investigation_status', '!=', 'resolved')->count(),
            'damagedParcels' => ParcelIncident::where('incident_type', 'damaged')->where('investigation_status', '!=', 'resolved')->count(),
            'quarantinedParcels' => ParcelIncident::whereNotNull('quarantined_at')->whereNull('recovered_at')->where('investigation_status', '!=', 'resolved')->count(),
            'wrongHubIncidents' => ParcelIncident::where('incident_type', 'wrong_hub')->where('investigation_status', '!=', 'resolved')->count(),
            'openInvestigations' => ParcelIncident::whereIn('investigation_status', ['open', 'investigating', 'escalated'])->count(),
        ];

        $companies = User::whereIn('role', ['logistics', 'sorting_center'])
            ->with(['logisticsProfile', 'sortingCenterProfile'])
            ->withCount([
                'hubs as active_hubs_count' => fn ($q) => $q->where('is_active', true),
                'sentTransferManifests as active_manifests_count' => fn ($q) => $q->whereIn('status', TransferManifest::ACTIVE_STATUSES),
            ])
            ->orderBy('first_name')
            ->get();

        $incidentsQuery = ParcelIncident::with(['order', 'hub', 'reporter'])
            ->when($companyFilter, fn ($q) => $q->whereHas('hub', fn ($hq) => $hq->where('logistics_company_id', $companyFilter)))
            ->when($statusFilter, fn ($q) => $q->where('investigation_status', $statusFilter))
            ->latest()
            ->latest('id');
        $recentIncidents = $incidentsQuery->paginate(10, ['*'], 'incidents_page')->withQueryString();

        $manifests = TransferManifest::with(['originHub', 'destinationHub', 'assignedCourier', 'senderLogistics', 'receiverLogistics'])
            ->when($companyFilter, fn ($q) => $q->where(fn ($sq) => $sq->where('sender_logistics_id', $companyFilter)->orWhere('receiver_logistics_id', $companyFilter)))
            ->latest('updated_at')
            ->latest('id')
            ->limit(15)
            ->get();

        $trackedOrder = null;
        if ($trackingSearch !== '') {
            $trackedOrder = Order::findByScanIdentifier($trackingSearch);
            $trackedOrder?->load([
                'buyer',
                'seller.sellerProfile',
                'seller.address',
                'address',
                'originHub',
                'destinationHub',
                'currentHub',
                'pickupCourier',
                'deliveryCourier',
                'currentCustodyLogistics.logisticsProfile',
                'statusHistory.changer',
                'parcelIncidents.events',
                'transferManifestItems.manifest.originHub',
                'transferManifestItems.manifest.destinationHub',
            ]);
        }

        return view('admin.logistics_monitoring.index', compact(
            'stats',
            'companies',
            'recentIncidents',
            'manifests',
            'trackedOrder',
            'trackingSearch',
            'companyFilter',
            'statusFilter'
        ));
    }

    public function showCompany(Request $request, User $company): View
    {
        abort_unless($request->user()->hasAdminPermission('manage_logistics'), 403);
        abort_unless($company->isLogistics(), 404);

        $company->load(['logisticsProfile', 'sortingCenterProfile']);
        $hubs = Hub::where('logistics_company_id', $company->id)->get();
        $riders = User::where('role', 'rider')
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $company->id)->orWhere('sorting_center_id', $company->id))
            ->with('courierProfile')
            ->get();
        $couriers = User::where('role', 'courier')
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $company->id)->orWhere('sorting_center_id', $company->id))
            ->with('courierProfile')
            ->get();

        $activeManifests = TransferManifest::where(fn ($q) => $q->where('sender_logistics_id', $company->id)->orWhere('receiver_logistics_id', $company->id))
            ->whereIn('status', TransferManifest::ACTIVE_STATUSES)
            ->with(['originHub', 'destinationHub', 'assignedCourier'])
            ->latest('updated_at')
            ->latest('id')
            ->get();

        $completedTransfersCount = TransferManifest::where(fn ($q) => $q->where('sender_logistics_id', $company->id)->orWhere('receiver_logistics_id', $company->id))
            ->whereIn('status', [TransferManifest::STATUS_RECEIVED, TransferManifest::STATUS_CLOSED_WITH_EXCEPTIONS])
            ->count();

        $deliveredCount = Order::where('delivery_logistics_id', $company->id)->whereIn('status', ['delivered', 'completed'])->count();
        $failedCount = Order::where('delivery_logistics_id', $company->id)->where('status', 'failed_delivery')->count();

        $openIncidents = ParcelIncident::whereHas('hub', fn ($q) => $q->where('logistics_company_id', $company->id))
            ->whereIn('investigation_status', ['open', 'investigating', 'escalated'])
            ->with(['order', 'hub'])
            ->latest('reported_at')
            ->latest('id')
            ->get();

        $remittances = LogisticsAdminRemittance::where('logistics_id', $company->id)->latest('updated_at')->latest('id')->limit(10)->get();
        $shippingAllocations = (float) InterCompanyShippingAllocation::where('logistics_company_id', $company->id)->sum('allocated_amount');
        $courierExpenses = (float) RiderEarning::whereIn('courier_id', $riders->pluck('id')->merge($couriers->pluck('id')))->sum('amount');
        $verifiedCodTotal = (float) CodCollection::where('logistics_id', $company->id)->where('status', 'verified')->sum('amount');

        return view('admin.logistics_monitoring.company', compact(
            'company',
            'hubs',
            'riders',
            'couriers',
            'activeManifests',
            'completedTransfersCount',
            'deliveredCount',
            'failedCount',
            'openIncidents',
            'remittances',
            'shippingAllocations',
            'courierExpenses',
            'verifiedCodTotal'
        ));
    }
}
