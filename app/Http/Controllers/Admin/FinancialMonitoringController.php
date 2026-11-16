<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\Commission;
use App\Models\Dispute;
use App\Models\FinancialAdjustment;
use App\Models\InterCompanyShippingAllocation;
use App\Models\LogisticsAdminRemittance;
use App\Models\RiderEarning;
use App\Models\SellerPayoutRequest;
use App\Services\FinancialReconciliationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialMonitoringController extends Controller
{
    public function index(Request $request, FinancialReconciliationService $reconciliationService): View
    {
        abort_unless($request->user()->hasAdminPermission('view_financials'), 403);

        // Strictly separate Submitted, Verified, Payable, Paid, Platform Revenue, Seller Liability, and Logistics Shipping
        $overview = [
            'verifiedMerchandiseCollections' => (float) CodCollection::where('status', 'verified')->sum('actual_collected_amount'),
            'riderSubmittedCodPending' => (float) CodRemittance::whereIn('status', ['pending', 'submitted', 'under_review'])->sum('claimed_amount'),
            'riderShortageVariance' => (float) CodRemittance::where('shortage_amount', '>', 0)->sum('shortage_amount'),
            'pendingAdminRemittances' => (float) LogisticsAdminRemittance::whereIn('status', ['submitted', 'under_review'])->sum('merchandise_amount'),
            'verifiedAdminRemittances' => (float) LogisticsAdminRemittance::where('status', 'verified')->sum('merchandise_amount'),
            'logisticsShippingRetained' => (float) LogisticsAdminRemittance::where('status', 'verified')->sum('shipping_retained'),
            'interCompanyShippingAllocated' => (float) InterCompanyShippingAllocation::sum('total_shipping_fee'),
            'platformCommissionsEarned' => (float) Commission::sum('commission_amount'),
            'platformCommissionsSettled' => (float) Commission::where('status', 'settled')->sum('commission_amount'),
            'sellerPayoutsPendingApproval' => (float) SellerPayoutRequest::where('status', 'pending')->sum('amount'),
            'sellerPayoutsApprovedPayable' => (float) SellerPayoutRequest::where('status', 'approved')->sum('amount'),
            'sellerPayoutsActuallyPaid' => (float) SellerPayoutRequest::whereIn('status', ['paid', 'processed'])->sum('amount'),
            'riderCompensationEarned' => (float) RiderEarning::whereIn('type', ['pickup', 'delivery', 'failed_delivery'])->sum('amount'),
            'transferCourierCompensationEarned' => (float) RiderEarning::where('type', 'transfer_trip')->sum('amount'),
            'openRefundLiabilities' => (float) Dispute::whereIn('return_stage', [
                'approved_awaiting_return',
                'in_return_transit',
                'received_for_inspection',
                'inspected',
                'refund_authorized',
            ])->sum('refund_amount'),
            'completedRefunds' => (float) Dispute::where('return_stage', 'refund_recorded')->sum('refund_amount'),
            'financialAdjustmentsTotal' => (float) FinancialAdjustment::sum('amount'),
        ];

        $overview['logisticsMerchandiseOutstanding'] = max(
            0.0,
            $overview['verifiedMerchandiseCollections'] - $overview['verifiedAdminRemittances']
        );

        $reconciliation = $reconciliationService->runReconciliationReport();

        $recentAdminRemittances = LogisticsAdminRemittance::with('logistics.logisticsProfile')->latest()->limit(8)->get();
        $recentSellerPayouts = SellerPayoutRequest::with('seller.sellerProfile')->latest()->limit(8)->get();
        $recentAdjustments = FinancialAdjustment::latest()->limit(8)->get();

        return view('admin.financial_monitoring.index', compact(
            'overview',
            'reconciliation',
            'recentAdminRemittances',
            'recentSellerPayouts',
            'recentAdjustments'
        ));
    }
}
