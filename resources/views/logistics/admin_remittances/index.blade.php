@extends('layouts.app')

@section('title', 'Remit Merchandise to Nexora Admin — Logistics Hub')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="adminRemittanceManager()">
    <div class="flex flex-col lg:flex-row w-full">

        <x-logistics-nav active="admin-remittances" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Breadcrumb --}}
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('logistics.dashboard') }}" class="text-primary font-bold hover:underline">Logistics Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant">Nexora Admin Remittances (Merchandise)</span>
                    </div>

                    <a href="{{ route('logistics.remittances.index') }}" class="inline-flex items-center gap-1 text-xs text-primary font-semibold hover:underline">
                        <span class="material-symbols-outlined text-[16px]">two_wheeler</span>
                        <span>Rider COD Collections</span>
                    </a>
                </div>

                {{-- Alerts --}}
                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3 shadow-2xs">
                        <span class="material-symbols-outlined text-primary text-[22px]">check_circle</span>
                        <p class="text-xs md:text-sm text-primary font-semibold">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                        <p class="font-bold mb-1">Please correct the following errors:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Header Card --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-xl md:text-2xl font-bold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-[28px]">account_balance</span>
                                <span>Logistics → Nexora Admin COD Remittance</span>
                            </h1>
                            <p class="text-xs text-on-surface-variant mt-1.5 max-w-2xl leading-relaxed">
                                Under Nexora Market's financial policy, <strong>100% of the shipping fee is retained by your logistics company</strong> as courier revenue. Only the merchandise/product amount is remitted to Nexora Admin for platform commission and seller settlement.
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="showCreateModal = true"
                            :disabled="availableCount === 0"
                            class="px-5 py-2.5 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-all shadow-md shadow-primary/20 flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shrink-0">
                            <span class="material-symbols-outlined text-[18px]">add_card</span>
                            <span>Create Remittance Batch</span>
                        </button>
                    </div>

                    {{-- COD Financial Summary Cards (Section 4 & 9) --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mt-6 pt-6 border-t border-outline-variant/60">
                        {{-- Total COD Received --}}
                        <div class="bg-surface-container/40 rounded-2xl p-4 border border-outline-variant/50">
                            <span class="text-[10px] text-on-surface-variant uppercase font-semibold block tracking-wider">Total COD Collected</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-on-surface mt-1 block">₱{{ number_format($summary['totalCodReceived'], 2) }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">Delivered orders from riders</span>
                        </div>

                        {{-- Shipping Fees Retained by Logistics --}}
                        <div class="bg-emerald-500/10 rounded-2xl p-4 border border-emerald-500/20">
                            <span class="text-[10px] text-emerald-800 uppercase font-semibold block tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">local_shipping</span>
                                <span>Shipping Retained</span>
                            </span>
                            <span class="text-xl sm:text-2xl font-extrabold text-emerald-900 mt-1 block">₱{{ number_format($summary['shippingRetained'], 2) }}</span>
                            <span class="text-[10px] text-emerald-700 font-medium mt-0.5 block">100% Logistics Courier Revenue</span>
                        </div>

                        {{-- Available for Remittance --}}
                        <div class="bg-primary/10 rounded-2xl p-4 border border-primary/20">
                            <span class="text-[10px] text-primary uppercase font-semibold block tracking-wider">Ready to Remit to Admin</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-primary mt-1 block">₱{{ number_format($summary['availableMerchandiseAmount'], 2) }}</span>
                            <span class="text-[10px] text-primary/80 mt-0.5 block">{{ $summary['availableOrdersCount'] }} delivered order(s) awaiting remittance</span>
                        </div>

                        {{-- Verified Remittances --}}
                        <div class="bg-surface-container/40 rounded-2xl p-4 border border-outline-variant/50">
                            <span class="text-[10px] text-on-surface-variant uppercase font-semibold block tracking-wider">Verified by Admin</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-emerald-800 mt-1 block">₱{{ number_format($summary['verifiedMerchandiseAmount'], 2) }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">{{ $summary['verifiedRemittancesCount'] }} batch(es) confirmed by Admin</span>
                        </div>
                    </div>

                    {{-- Additional Secondary Stats Row --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3 pt-3 border-t border-outline-variant/30 text-xs text-on-surface-variant">
                        <div>
                            <span>Pending Admin Verification:</span>
                            <strong class="text-amber-800 ml-1">₱{{ number_format($summary['pendingMerchandiseAmount'], 2) }}</strong>
                            <span class="text-[11px]">({{ $summary['pendingRemittancesCount'] }} batch)</span>
                        </div>
                        <div>
                            <span>Total Merchandise Payable:</span>
                            <strong class="text-on-surface ml-1">₱{{ number_format($summary['merchandisePayable'], 2) }}</strong>
                        </div>
                        <div>
                            <span>Rejected Batches:</span>
                            <strong class="{{ $summary['rejectedRemittancesCount'] > 0 ? 'text-rose-600' : 'text-on-surface' }} ml-1">{{ $summary['rejectedRemittancesCount'] }}</strong>
                        </div>
                    </div>
                </div>

                {{-- Remittance History Table --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-bold text-on-surface">Remittance Batches to Nexora Admin</h2>
                            <span class="text-xs text-on-surface-variant">({{ $remittances->total() }})</span>
                        </div>

                        {{-- Status Filters --}}
                        <div class="flex items-center gap-1.5 overflow-x-auto text-xs">
                            <a href="{{ route('logistics.admin-remittances.index') }}" class="px-3 py-1 rounded-full border transition-all {{ empty($statusFilter) ? 'bg-primary text-on-primary font-bold border-primary' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                All
                            </a>
                            <a href="{{ route('logistics.admin-remittances.index', ['status' => 'submitted']) }}" class="px-3 py-1 rounded-full border transition-all {{ $statusFilter === 'submitted' ? 'bg-amber-600 text-white font-bold border-amber-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Submitted
                            </a>
                            <a href="{{ route('logistics.admin-remittances.index', ['status' => 'under_review']) }}" class="px-3 py-1 rounded-full border transition-all {{ $statusFilter === 'under_review' ? 'bg-indigo-600 text-white font-bold border-indigo-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Under Review
                            </a>
                            <a href="{{ route('logistics.admin-remittances.index', ['status' => 'verified']) }}" class="px-3 py-1 rounded-full border transition-all {{ $statusFilter === 'verified' ? 'bg-emerald-600 text-white font-bold border-emerald-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Verified
                            </a>
                            <a href="{{ route('logistics.admin-remittances.index', ['status' => 'rejected']) }}" class="px-3 py-1 rounded-full border transition-all {{ $statusFilter === 'rejected' ? 'bg-rose-600 text-white font-bold border-rose-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Rejected
                            </a>
                        </div>
                    </div>

                    <div class="p-5 md:p-6 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] text-on-surface-variant uppercase font-semibold">
                                    <th class="pb-3">Batch Reference #</th>
                                    <th class="pb-3">Orders</th>
                                    <th class="pb-3 text-right">Total COD</th>
                                    <th class="pb-3 text-right">Shipping Retained</th>
                                    <th class="pb-3 text-right">Remitted to Admin</th>
                                    <th class="pb-3">Method & Ref</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3">Submitted At</th>
                                    <th class="pb-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($remittances as $remit)
                                    <tr class="hover:bg-surface-container-low/40 transition-colors">
                                        <td class="py-3.5 font-mono font-bold text-primary">
                                            <a href="{{ route('logistics.admin-remittances.show', $remit) }}" class="hover:underline">
                                                {{ $remit->reference_number }}
                                            </a>
                                        </td>
                                        <td class="py-3.5">
                                            <span class="px-2 py-0.5 rounded-md bg-surface-container border border-outline-variant/60 font-semibold">
                                                {{ $remit->items->count() }} orders
                                            </span>
                                        </td>
                                        <td class="py-3.5 text-right font-medium text-on-surface">
                                            ₱{{ number_format($remit->total_cod_amount, 2) }}
                                        </td>
                                        <td class="py-3.5 text-right font-bold text-emerald-800">
                                            ₱{{ number_format($remit->shipping_retained, 2) }}
                                        </td>
                                        <td class="py-3.5 text-right font-extrabold text-primary">
                                            ₱{{ number_format($remit->merchandise_amount, 2) }}
                                        </td>
                                        <td class="py-3.5">
                                            <div class="truncate max-w-[150px]">
                                                <span class="uppercase font-semibold text-[10px] text-on-surface-variant block">{{ str_replace('_', ' ', $remit->transfer_method) }}</span>
                                                <span class="font-mono text-[11px] text-outline">{{ $remit->transfer_reference }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3.5">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border uppercase tracking-wider {{ $remit->statusBadgeClass() }}">
                                                {{ str_replace('_', ' ', $remit->status) }}
                                            </span>
                                            @if($remit->isRejected())
                                                <span class="text-[10px] text-rose-600 block mt-0.5 max-w-[120px] truncate" title="{{ $remit->rejection_reason }}">
                                                    {{ $remit->rejection_reason }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 text-outline">
                                            {{ $remit->created_at->format('M d, Y h:i A') }}
                                        </td>
                                        <td class="py-3.5 text-right">
                                            <a href="{{ route('logistics.admin-remittances.show', $remit) }}" class="px-3 py-1 rounded-xl border border-outline-variant bg-surface text-primary font-bold hover:bg-primary/5 hover:border-primary transition-colors text-xs">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-10 text-center text-on-surface-variant">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <span class="material-symbols-outlined text-[36px] text-outline">account_balance_wallet</span>
                                                <span class="font-semibold">No remittance batches found.</span>
                                                <p class="text-xs text-outline max-w-sm">When your couriers turn over COD payments for delivered parcels, select the orders above to remit the merchandise amount to Nexora Admin.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($remittances->hasPages())
                        <div class="p-4 border-t border-outline-variant/60">
                            {{ $remittances->links() }}
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- CREATE REMITTANCE MODAL (Section 4) --}}
    <div
        x-show="showCreateModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div
            @click.away="showCreateModal = false"
            class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-4xl w-full p-6 sm:p-8 shadow-xl space-y-5 max-h-[90vh] overflow-y-auto">

            <div class="flex items-start justify-between border-b border-outline-variant/60 pb-4">
                <div>
                    <h3 class="font-headline-sm text-base sm:text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[22px]">payments</span>
                        <span>Create Remittance to Nexora Admin</span>
                    </h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">
                        Select eligible delivered COD orders. The shipping fee is kept by your facility; only merchandise funds are remitted to Admin.
                    </p>
                </div>
                <button type="button" @click="showCreateModal = false" class="text-on-surface-variant hover:text-on-surface p-1 rounded-xl hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <form method="POST" action="{{ route('logistics.admin-remittances.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                {{-- Step 1: Select Orders --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-on-surface uppercase tracking-wider">
                            1. Select Delivered Orders to Remit (<span x-text="selectedIds.length"></span> of <span x-text="orders.length"></span> selected)
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="selectAll()" class="text-[11px] text-primary font-bold hover:underline">Select All</button>
                            <span class="text-outline-variant">•</span>
                            <button type="button" @click="deselectAll()" class="text-[11px] text-outline font-semibold hover:underline">Clear</button>
                        </div>
                    </div>

                    <div class="border border-outline-variant rounded-2xl overflow-hidden max-h-64 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-surface-container-low/70 sticky top-0 z-10 text-[10px] uppercase font-semibold text-on-surface-variant border-b border-outline-variant">
                                <tr>
                                    <th class="p-2.5 w-10 text-center"></th>
                                    <th class="p-2.5">Order #</th>
                                    <th class="p-2.5">Seller</th>
                                    <th class="p-2.5 text-right">Total COD</th>
                                    <th class="p-2.5 text-right text-emerald-800">Shipping (Retained)</th>
                                    <th class="p-2.5 text-right text-primary">Merchandise (To Admin)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                <template x-for="order in orders" :key="order.id">
                                    <tr class="hover:bg-surface-container-low/30 cursor-pointer select-none" @click="toggleOrder(order.id)">
                                        <td class="p-2.5 text-center" @click.stop>
                                            <input
                                                type="checkbox"
                                                name="order_ids[]"
                                                :value="order.id"
                                                :checked="selectedIds.includes(order.id)"
                                                @change="toggleOrder(order.id)"
                                                class="w-4 h-4 rounded text-primary focus:ring-primary border-outline-variant">
                                        </td>
                                        <td class="p-2.5 font-mono font-bold text-on-surface" x-text="order.order_number"></td>
                                        <td class="p-2.5 truncate max-w-[120px]" x-text="order.seller_name"></td>
                                        <td class="p-2.5 text-right font-medium" x-text="'₱' + Number(order.total).toFixed(2)"></td>
                                        <td class="p-2.5 text-right font-bold text-emerald-800" x-text="'₱' + Number(order.shipping_fee).toFixed(2)"></td>
                                        <td class="p-2.5 text-right font-extrabold text-primary" x-text="'₱' + Number(order.merchandise).toFixed(2)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Live Financial Calculations Card (Section 4) --}}
                <div class="bg-surface-container-low p-4 sm:p-5 rounded-2xl border border-outline-variant/70 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="flex flex-col">
                        <span class="text-[11px] text-on-surface-variant font-medium">Total COD Collected</span>
                        <span class="text-lg font-bold text-on-surface mt-0.5" x-text="'₱' + totalCod.toFixed(2)"></span>
                        <span class="text-[10px] text-outline">From buyer collections</span>
                    </div>

                    <div class="flex flex-col">
                        <span class="text-[11px] text-emerald-800 font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined text-[13px]">check</span>
                            <span>Shipping Retained by You</span>
                        </span>
                        <span class="text-lg font-bold text-emerald-900 mt-0.5" x-text="'₱' + totalShipping.toFixed(2)"></span>
                        <span class="text-[10px] text-emerald-700">Excluded from remittance</span>
                    </div>

                    <div class="flex flex-col bg-surface-container-lowest p-3 rounded-xl border border-primary/30 shadow-2xs">
                        <span class="text-[11px] text-primary font-extrabold uppercase tracking-wider">Merchandise Payable to Nexora</span>
                        <span class="text-xl font-extrabold text-primary mt-0.5" x-text="'₱' + totalMerchandise.toFixed(2)"></span>
                        <span class="text-[10px] text-primary/80">Exact amount to transfer</span>
                    </div>
                </div>

                {{-- Step 2: Payment Transfer Information --}}
                <div class="space-y-4 pt-2 border-t border-outline-variant/60">
                    <h4 class="text-xs font-bold text-on-surface uppercase tracking-wider">2. Bank / Payment Transfer Information</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-on-surface-variant">Transfer Method <span class="text-rose-500">*</span></label>
                            <select name="transfer_method" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="bank_transfer">Direct Bank Transfer (BDO, BPI, UnionBank, Metrobank)</option>
                                <option value="gcash">GCash Enterprise Transfer</option>
                                <option value="maya">Maya Business Disbursement</option>
                                <option value="cash_deposit">Over-the-Counter Bank Cash Deposit</option>
                                <option value="check">Corporate Check Deposit</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-on-surface-variant">Transaction Reference Number <span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                name="transfer_reference"
                                required
                                placeholder="e.g. UB2026110898234 / GC1092837465"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface font-mono focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <div class="flex flex-col gap-1 md:col-span-2">
                            <label class="text-xs font-semibold text-on-surface-variant">Proof of Transfer Document / Screenshot <span class="text-rose-500">*</span></label>
                            <input
                                type="file"
                                name="proof_file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                required
                                class="text-xs text-on-surface-variant file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary hover:file:bg-primary/90">
                            <span class="text-[10px] text-outline">Upload a clear deposit slip, bank transfer receipt, or confirmation screenshot (Max 5MB).</span>
                        </div>

                        <div class="flex flex-col gap-1 md:col-span-2">
                            <label class="text-xs font-semibold text-on-surface-variant">Remittance Notes / Remarks (Optional)</label>
                            <textarea
                                name="notes"
                                rows="2"
                                placeholder="e.g. COD collections for Zone 4 riders (Batch Nov 08, 2026)"
                                class="rounded-xl border border-outline-variant p-3 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Modal Actions --}}
                <div class="flex items-center justify-between pt-4 border-t border-outline-variant/60">
                    <button type="button" @click="showCreateModal = false" class="px-5 py-2.5 rounded-full border border-outline-variant text-xs font-semibold text-on-surface-variant hover:bg-surface-container transition-colors cursor-pointer">
                        Cancel
                    </button>

                    <button
                        type="submit"
                        :disabled="selectedIds.length === 0"
                        class="px-7 py-3 rounded-full bg-primary text-on-primary text-xs font-bold shadow-md shadow-primary/20 hover:bg-primary/90 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">send</span>
                        <span>Submit Remittance to Nexora Admin</span>
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

@push('scripts')
<script>
    function adminRemittanceManager() {
        const rawOrders = @json($eligibleOrders ?? []);
        const formattedOrders = rawOrders.map(o => ({
            id: o.id,
            order_number: o.order_number,
            seller_name: o.seller ? (o.seller.seller_profile ? o.seller.seller_profile.business_name : (o.seller.first_name + ' ' + o.seller.last_name)) : 'Seller',
            total: parseFloat(o.total || 0),
            shipping_fee: parseFloat(o.shipping_fee || 0),
            merchandise: Math.max(0, parseFloat(o.subtotal || 0) - parseFloat(o.discount || 0)),
        }));

        return {
            showCreateModal: false,
            orders: formattedOrders,
            selectedIds: formattedOrders.map(o => o.id), // default select all eligible

            get availableCount() {
                return this.orders.length;
            },

            get selectedOrders() {
                return this.orders.filter(o => this.selectedIds.includes(o.id));
            },

            get totalCod() {
                return this.selectedOrders.reduce((sum, o) => sum + o.total, 0);
            },

            get totalShipping() {
                return this.selectedOrders.reduce((sum, o) => sum + o.shipping_fee, 0);
            },

            get totalMerchandise() {
                return this.selectedOrders.reduce((sum, o) => sum + o.merchandise, 0);
            },

            toggleOrder(id) {
                const idx = this.selectedIds.indexOf(id);
                if (idx > -1) {
                    this.selectedIds.splice(idx, 1);
                } else {
                    this.selectedIds.push(id);
                }
            },

            selectAll() {
                this.selectedIds = this.orders.map(o => o.id);
            },

            deselectAll() {
                this.selectedIds = [];
            }
        };
    }
</script>
@endpush
@endsection

