@extends('layouts.app')

@section('title', 'Seller Payout Management — Platform Administration')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="sellerPayoutManager()">
    <div class="flex flex-col lg:flex-row w-full">

        <x-admin-nav active="seller-payouts" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Breadcrumb --}}
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('admin.dashboard') }}" class="text-primary font-bold hover:underline">Admin Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant">Seller Payout Management</span>
                    </div>

                    <a href="{{ route('admin.logistics-remittances.index') }}" class="inline-flex items-center gap-1.5 text-xs text-primary font-semibold hover:underline">
                        <span class="material-symbols-outlined text-[16px]">payments</span>
                        <span>Logistics Remittances →</span>
                    </a>
                </div>

                {{-- Status Alert --}}
                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3 shadow-2xs">
                        <span class="material-symbols-outlined text-primary text-[22px]">check_circle</span>
                        <p class="text-xs md:text-sm text-primary font-semibold">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Header & Statistics (Section 7) --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-xl md:text-2xl font-bold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-[28px]">account_balance_wallet</span>
                                <span>Seller Payout Management</span>
                            </h1>
                            <p class="text-xs text-on-surface-variant mt-1.5 max-w-2xl leading-relaxed">
                                Review and disburse merchant earnings withdrawals. Under platform security rules, <strong>merchants can only withdraw funds verified from logistics remittances</strong>. Approval and payment disbursement are separate events requiring a valid transfer reference.
                            </p>
                        </div>
                    </div>

                    {{-- Stat Cards --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6 pt-6 border-t border-outline-variant/60">
                        <div class="bg-amber-500/10 rounded-2xl p-4 border border-amber-500/20">
                            <span class="text-[10px] text-amber-800 uppercase font-semibold block">Pending Requests</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-amber-900 mt-1 block">₱{{ number_format($stats['pendingAmount'], 2) }}</span>
                            <span class="text-[10px] text-amber-700 font-medium mt-0.5 block">{{ $stats['pendingCount'] }} request(s) awaiting review</span>
                        </div>

                        <div class="bg-indigo-500/10 rounded-2xl p-4 border border-indigo-500/20">
                            <span class="text-[10px] text-indigo-800 uppercase font-semibold block">Approved (Ready to Pay)</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-indigo-900 mt-1 block">₱{{ number_format($stats['approvedAmount'], 2) }}</span>
                            <span class="text-[10px] text-indigo-700 font-medium mt-0.5 block">{{ $stats['approvedCount'] }} approved request(s)</span>
                        </div>

                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Total Paid Out</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-emerald-800 mt-1 block">₱{{ number_format($stats['paidAmount'], 2) }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">{{ $stats['paidCount'] }} completed payout(s)</span>
                        </div>

                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Rejected Requests</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-on-surface mt-1 block">{{ $stats['rejectedCount'] }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">Balances restored to sellers</span>
                        </div>
                    </div>
                </div>

                {{-- Table & Filters --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    {{-- Status Pills & Search Form --}}
                    <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <div class="flex items-center gap-1.5 overflow-x-auto text-xs pb-1 md:pb-0">
                            <a href="{{ route('admin.seller-payouts.index') }}" class="px-3 py-1 rounded-full border transition-all {{ empty($status) ? 'bg-primary text-on-primary font-bold border-primary' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                All
                            </a>
                            <a href="{{ route('admin.seller-payouts.index', ['status' => 'pending']) }}" class="px-3 py-1 rounded-full border transition-all {{ $status === 'pending' ? 'bg-amber-600 text-white font-bold border-amber-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Pending
                            </a>
                            <a href="{{ route('admin.seller-payouts.index', ['status' => 'approved']) }}" class="px-3 py-1 rounded-full border transition-all {{ $status === 'approved' ? 'bg-indigo-600 text-white font-bold border-indigo-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Approved
                            </a>
                            <a href="{{ route('admin.seller-payouts.index', ['status' => 'paid']) }}" class="px-3 py-1 rounded-full border transition-all {{ in_array($status, ['paid', 'processed']) ? 'bg-emerald-600 text-white font-bold border-emerald-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Paid Out
                            </a>
                            <a href="{{ route('admin.seller-payouts.index', ['status' => 'rejected']) }}" class="px-3 py-1 rounded-full border transition-all {{ $status === 'rejected' ? 'bg-rose-600 text-white font-bold border-rose-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Rejected
                            </a>
                        </div>

                        <form method="GET" action="{{ route('admin.seller-payouts.index') }}" class="flex items-center gap-2">
                            @if($status)
                                <input type="hidden" name="status" value="{{ $status }}">
                            @endif

                            <select name="seller_id" onchange="this.form.submit()" class="rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-1 focus:ring-primary">
                                <option value="">All Merchants</option>
                                @foreach($sellers as $seller)
                                    <option value="{{ $seller->id }}" {{ (string)$sellerId === (string)$seller->id ? 'selected' : '' }}>
                                        {{ $seller->sellerProfile?->business_name ?? $seller->full_name }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="relative">
                                <input
                                    type="text"
                                    name="search"
                                    value="{{ $search }}"
                                    placeholder="Search ref #, account..."
                                    class="rounded-xl border border-outline-variant pl-8 pr-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-1 focus:ring-primary">
                                <span class="material-symbols-outlined text-[16px] text-outline absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none">search</span>
                            </div>
                        </form>
                    </div>

                    {{-- Table --}}
                    <div class="p-5 md:p-6 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] text-on-surface-variant uppercase font-semibold">
                                    <th class="pb-3">Payout Ref #</th>
                                    <th class="pb-3">Merchant / Store</th>
                                    <th class="pb-3 text-right">Amount</th>
                                    <th class="pb-3">Method</th>
                                    <th class="pb-3">Account Details</th>
                                    <th class="pb-3">Transfer Ref</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3">Date Requested</th>
                                    <th class="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($payouts as $payout)
                                    <tr class="hover:bg-surface-container-low/40 transition-colors">
                                        <td class="py-3.5 font-mono font-bold text-primary">
                                            {{ $payout->reference_number ?? ('SPR-' . str_pad($payout->id, 6, '0', STR_PAD_LEFT)) }}
                                        </td>
                                        <td class="py-3.5">
                                            <span class="font-bold text-on-surface block">
                                                {{ $payout->seller?->sellerProfile?->business_name ?? $payout->seller?->full_name }}
                                            </span>
                                            <span class="text-[10px] text-outline font-mono">{{ $payout->seller?->email }}</span>
                                        </td>
                                        <td class="py-3.5 text-right font-extrabold text-on-surface text-sm">
                                            ₱{{ number_format($payout->amount, 2) }}
                                        </td>
                                        <td class="py-3.5 uppercase text-[10px] font-semibold text-on-surface-variant">
                                            {{ str_replace('_', ' ', $payout->payout_method) }}
                                        </td>
                                        <td class="py-3.5">
                                            <div>
                                                <span class="font-semibold block text-on-surface">{{ $payout->account_name }}</span>
                                                <span class="font-mono text-[11px] text-outline">{{ $payout->account_number }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3.5 font-mono text-xs">
                                            @if($payout->transaction_reference)
                                                <span class="font-bold text-primary">{{ $payout->transaction_reference }}</span>
                                                @if($payout->proof_path)
                                                    <a href="{{ Storage::url($payout->proof_path) }}" target="_blank" class="text-[10px] text-outline block hover:underline">View Receipt</a>
                                                @endif
                                            @else
                                                <span class="text-outline italic">—</span>
                                            @endif
                                        </td>
                                        <td class="py-3.5">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border uppercase tracking-wider {{ $payout->statusBadgeClass() }}">
                                                {{ $payout->status === 'processed' ? 'PAID' : $payout->status }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 text-outline">
                                            {{ $payout->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="py-3.5 text-right space-x-1">
                                            @if($payout->isPending())
                                                <button
                                                    type="button"
                                                    @click="openApproveModal({{ $payout->id }}, '{{ number_format($payout->amount, 2) }}', '{{ $payout->seller?->sellerProfile?->business_name ?? $payout->seller?->full_name }}')"
                                                    class="px-2.5 py-1 rounded-xl bg-indigo-600 text-white font-bold text-[11px] hover:bg-indigo-700 transition-colors shadow-2xs">
                                                    Approve
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="openRejectModal({{ $payout->id }})"
                                                    class="px-2.5 py-1 rounded-xl border border-rose-500/30 text-rose-700 bg-rose-500/10 font-bold text-[11px] hover:bg-rose-500/20 transition-colors">
                                                    Reject
                                                </button>
                                            @elseif($payout->isApproved())
                                                <button
                                                    type="button"
                                                    @click="openDisburseModal({{ $payout->id }}, '{{ number_format($payout->amount, 2) }}', '{{ $payout->account_name }}', '{{ $payout->account_number }}', '{{ $payout->payout_method }}')"
                                                    class="px-3 py-1 rounded-xl bg-emerald-600 text-white font-bold text-[11px] hover:bg-emerald-700 transition-colors shadow-2xs">
                                                    Disburse & Mark Paid
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="openRejectModal({{ $payout->id }})"
                                                    class="px-2.5 py-1 rounded-xl border border-rose-500/30 text-rose-700 bg-rose-500/10 font-bold text-[11px] hover:bg-rose-500/20 transition-colors">
                                                    Reject
                                                </button>
                                            @elseif($payout->isPaid())
                                                <span class="text-[11px] text-emerald-800 font-bold">
                                                    Paid on {{ $payout->disbursed_at?->format('M d') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-12 text-center text-on-surface-variant">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <span class="material-symbols-outlined text-[40px] text-outline">account_balance_wallet</span>
                                                <span class="font-bold text-sm">No payout requests found.</span>
                                                <p class="text-xs text-outline max-w-sm">When merchants request withdrawals from their verified merchandise balances, they will appear here for approval and disbursement.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($payouts->hasPages())
                        <div class="p-4 border-t border-outline-variant/60">
                            {{ $payouts->links() }}
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- APPROVE MODAL --}}
    <div
        x-show="showApproveModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div @click.away="showApproveModal = false" class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl space-y-4">
            <h3 class="font-bold text-base text-on-surface">Approve Payout Request</h3>
            <p class="text-xs text-on-surface-variant">
                Approve withdrawal of <strong class="text-primary font-bold">₱<span x-text="activeAmount"></span></strong> for <strong x-text="activeSeller"></strong>.
                After approval, you can disburse the funds via bank or e-wallet transfer and record the transaction reference.
            </p>

            <form :action="'/admin/seller-payouts/' + activeId + '/approve'" method="POST" class="space-y-4">
                @csrf
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-on-surface-variant">Admin Approval Note (Optional)</label>
                    <textarea name="admin_notes" rows="2" placeholder="e.g. Account verified; queued for GCash batch" class="rounded-xl border border-outline-variant p-2.5 text-xs text-on-surface bg-surface"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showApproveModal = false" class="px-4 py-2 rounded-full border text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-full bg-indigo-600 text-white font-bold text-xs">Approve Request</button>
                </div>
            </form>
        </div>
    </div>

    {{-- DISBURSE / MARK PAID MODAL (Section 7) --}}
    <div
        x-show="showDisburseModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div @click.away="showDisburseModal = false" class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg w-full p-6 sm:p-8 shadow-xl space-y-4">
            <div class="flex items-center gap-2 text-emerald-800 font-bold text-base">
                <span class="material-symbols-outlined text-[24px]">payments</span>
                <span>Confirm Payment Disbursement</span>
            </div>

            <p class="text-xs text-on-surface-variant leading-relaxed">
                Record the actual payment transfer of <strong class="text-emerald-900 font-bold text-sm">₱<span x-text="activeAmount"></span></strong> sent to:
                <span class="block font-semibold mt-1" x-text="activeAccountName + ' (' + activeAccountNumber + ') • ' + activeMethod.toUpperCase()"></span>
            </p>

            <form :action="'/admin/seller-payouts/' + activeId + '/disburse'" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-on-surface-variant">Bank / GCash / Maya Transaction Reference <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        name="transaction_reference"
                        required
                        placeholder="e.g. GC1092837465 / REF-98234710"
                        class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface font-mono focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    <span class="text-[10px] text-outline">Never mark a payout as paid without a verified bank or e-wallet transaction reference.</span>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-on-surface-variant">Proof of Transfer Document / Screenshot (Optional)</label>
                    <input type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png" class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-on-surface-variant">Admin Remarks</label>
                    <textarea name="admin_notes" rows="2" placeholder="e.g. Disbursed via UnionBank Corporate portal" class="rounded-xl border border-outline-variant p-2.5 text-xs text-on-surface bg-surface"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showDisburseModal = false" class="px-4 py-2 rounded-full border text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 rounded-full bg-emerald-600 text-white font-bold text-xs shadow-md shadow-emerald-600/20 hover:bg-emerald-700 transition-all cursor-pointer">
                        Record Payment & Mark Paid
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- REJECT MODAL --}}
    <div
        x-show="showRejectModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div @click.away="showRejectModal = false" class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl space-y-4">
            <h3 class="font-bold text-base text-rose-700">Reject Payout Request</h3>
            <p class="text-xs text-on-surface-variant">
                Rejecting this request will immediately release the funds back to the merchant's available balance.
            </p>

            <form :action="'/admin/seller-payouts/' + activeId + '/reject'" method="POST" class="space-y-4">
                @csrf
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-on-surface-variant">Rejection Reason <span class="text-rose-500">*</span></label>
                    <textarea name="rejection_reason" required rows="3" placeholder="e.g. Invalid account number / Account name does not match merchant registration" class="rounded-xl border border-outline-variant p-2.5 text-xs text-on-surface bg-surface"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showRejectModal = false" class="px-4 py-2 rounded-full border text-xs font-semibold">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-full bg-rose-600 text-white font-bold text-xs">Reject Request</button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function sellerPayoutManager() {
        return {
            showApproveModal: false,
            showDisburseModal: false,
            showRejectModal: false,
            activeId: null,
            activeAmount: '0.00',
            activeSeller: '',
            activeAccountName: '',
            activeAccountNumber: '',
            activeMethod: '',

            openApproveModal(id, amount, seller) {
                this.activeId = id;
                this.activeAmount = amount;
                this.activeSeller = seller;
                this.showApproveModal = true;
            },

            openDisburseModal(id, amount, accountName, accountNumber, method) {
                this.activeId = id;
                this.activeAmount = amount;
                this.activeAccountName = accountName;
                this.activeAccountNumber = accountNumber;
                this.activeMethod = method;
                this.showDisburseModal = true;
            },

            openRejectModal(id) {
                this.activeId = id;
                this.showRejectModal = true;
            }
        };
    }
</script>
@endpush
@endsection

