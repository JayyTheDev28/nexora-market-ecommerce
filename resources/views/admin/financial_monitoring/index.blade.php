@extends('layouts.app')
@section('title', 'Financial Safety & Monitoring — Admin Portal')
@section('content')
<div class="w-full min-h-screen bg-surface flex flex-col lg:flex-row">
    <x-admin-nav active="financial-monitoring" />
    <main class="flex-1 p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-on-surface">Financial Safety &amp; Monitoring Center</h1>
                    <p class="text-sm text-on-surface-variant">Authoritative separation of Submitted, Verified, Payable, Paid, Platform Revenue, Seller Liabilities, and Logistics Shipping.</p>
                </div>
                <span class="px-3 py-1.5 rounded-full text-xs font-bold {{ ($reconciliation['status'] ?? 'balanced') === 'balanced' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                    Reconciliation: {{ strtoupper($reconciliation['status'] ?? 'balanced') }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ([
                    ['Verified Merchandise Collections', $overview['verifiedMerchandiseCollections'], 'Verified COD cash collected'],
                    ['Logistics Merchandise Outstanding', $overview['logisticsMerchandiseOutstanding'], 'Verified COD not yet remitted to Admin'],
                    ['Pending Admin Remittances (Submitted)', $overview['pendingAdminRemittances'], 'Awaiting Admin verification (not counted as verified)'],
                    ['Verified Admin Remittances', $overview['verifiedAdminRemittances'], 'Confirmed received by Admin'],
                    ['Platform Commissions Earned', $overview['platformCommissionsEarned'], 'Nexora platform revenue (settled: ₱' . number_format($overview['platformCommissionsSettled'], 2) . ')'],
                    ['Seller Payouts Payable (Approved)', $overview['sellerPayoutsApprovedPayable'], 'Approved, awaiting disbursement'],
                    ['Seller Payouts Actually Paid', $overview['sellerPayoutsActuallyPaid'], 'Completed disbursements'],
                    ['Open Refund Liabilities', $overview['openRefundLiabilities'], 'Completed refunds: ₱' . number_format($overview['completedRefunds'], 2)],
                    ['Logistics Shipping Retained', $overview['logisticsShippingRetained'], 'Inter-company allocated: ₱' . number_format($overview['interCompanyShippingAllocated'], 2)],
                    ['Rider Compensation Earned', $overview['riderCompensationEarned'], 'Transfer Courier: ₱' . number_format($overview['transferCourierCompensationEarned'], 2)],
                    ['Rider COD Pending Verification', $overview['riderSubmittedCodPending'], 'Shortage variance: ₱' . number_format($overview['riderShortageVariance'], 2)],
                    ['Financial Adjustments', $overview['financialAdjustmentsTotal'], 'Recorded ledger adjustments'],
                ] as [$title, $amount, $sub])
                    <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60">
                        <div class="text-xs font-semibold text-on-surface-variant">{{ $title }}</div>
                        <div class="text-xl font-bold text-on-surface mt-1">₱{{ number_format($amount, 2) }}</div>
                        <div class="text-[11px] text-on-surface-variant mt-1">{{ $sub }}</div>
                    </div>
                @endforeach
            </div>

            {{-- FinancialReconciliationService Results --}}
            <div class="bg-surface-container-lowest p-6 rounded-2xl border border-outline-variant/60 space-y-4">
                <h2 class="text-base font-bold">Financial Reconciliation Findings</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200">
                        <div class="font-bold text-red-800">Critical Discrepancies: {{ count($reconciliation['critical'] ?? []) }}</div>
                        @foreach (array_slice($reconciliation['critical'] ?? [], 0, 5) as $item)
                            <div class="mt-1 text-red-700">&bull; {{ $item['message'] ?? json_encode($item) }}</div>
                        @endforeach
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50 border border-amber-200">
                        <div class="font-bold text-amber-800">Warnings: {{ count($reconciliation['warnings'] ?? []) }}</div>
                        @foreach (array_slice($reconciliation['warnings'] ?? [], 0, 5) as $item)
                            <div class="mt-1 text-amber-700">&bull; {{ $item['message'] ?? json_encode($item) }}</div>
                        @endforeach
                    </div>
                    <div class="p-3 rounded-xl bg-surface-container-low border border-outline-variant">
                        <div class="font-bold">Manual Review Required: {{ count($reconciliation['manual_review'] ?? []) }}</div>
                        @foreach (array_slice($reconciliation['manual_review'] ?? [], 0, 5) as $item)
                            <div class="mt-1 text-on-surface-variant">&bull; {{ $item['message'] ?? json_encode($item) }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
    </main>
</div>
@endsection
