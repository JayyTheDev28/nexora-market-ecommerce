@extends('layouts.app')

@section('title', 'Seller Compliance & Warnings — Admin Portal')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{
    issueModalOpen: false,
    selectedSellerId: '',
    selectedProductId: '',
    violationType: 'category_mismatch',
    details: '',
    archiveProduct: false
}">
    <div class="flex flex-col lg:flex-row w-full">

            <x-admin-nav active="compliance" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-2 text-primary font-body-sm text-sm">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Seller Compliance & Regulatory Oversight</h1>
                <p class="font-body-md text-sm text-on-surface-variant">Monitor merchant adherence to marketplace policies, audit category mismatches, and issue formal compliance warnings.</p>
            </div>

            <button type="button" @click="issueModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-amber-600 text-white font-label-md text-xs font-semibold shadow-xs hover:bg-amber-700 transition-all">
                <span class="material-symbols-outlined text-[18px]">warning</span>
                <span>Issue Compliance Warning</span>
            </button>
        </div>

        {{-- Metric Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Total Warnings Issued</span>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-1">{{ number_format($stats['totalWarnings']) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">Recorded infractions</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Category Mismatches</span>
                <div class="font-headline-md text-2xl font-bold text-amber-600 mt-1">{{ number_format($stats['categoryMismatches']) }}</div>
                <span class="text-[11px] text-amber-600 font-medium mt-0.5 block">Catalog policy audits</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Prohibited Listings</span>
                <div class="font-headline-md text-2xl font-bold text-rose-600 mt-1">{{ number_format($stats['prohibitedItems']) }}</div>
                <span class="text-[11px] text-rose-600 font-medium mt-0.5 block">Dangerous / Banned goods</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Warned Merchants</span>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-1">{{ number_format($stats['warnedSellersCount']) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">Distinct sellers with warnings</span>
            </div>
        </div>

        {{-- Filters & Search --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6 shadow-xs">
            <form method="GET" action="{{ route('admin.compliance.index') }}" class="flex flex-col md:flex-row items-center gap-3">
                <div class="relative flex-1 w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Search warning details, seller, or product name..."
                           class="w-full pl-9 pr-4 py-2 bg-surface-container rounded-xl text-xs text-on-surface placeholder:text-outline border border-outline-variant focus:outline-none focus:border-primary">
                </div>

                <div class="w-full md:w-56">
                    <select name="violation_type" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">All Violation Types</option>
                        <option value="category_mismatch" {{ $violationType === 'category_mismatch' ? 'selected' : '' }}>Category Mismatch</option>
                        <option value="prohibited_item" {{ $violationType === 'prohibited_item' ? 'selected' : '' }}>Prohibited Item</option>
                        <option value="counterfeit" {{ $violationType === 'counterfeit' ? 'selected' : '' }}>Counterfeit / Replica</option>
                        <option value="misleading_pricing" {{ $violationType === 'misleading_pricing' ? 'selected' : '' }}>Misleading Pricing</option>
                        <option value="policy_violation" {{ $violationType === 'policy_violation' ? 'selected' : '' }}>General Policy Violation</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <button type="submit" class="w-full md:w-auto px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                        Filter
                    </button>
                    @if($search || $violationType || $sellerId)
                        <a href="{{ route('admin.compliance.index') }}" class="w-full md:w-auto px-3 py-2 rounded-xl border border-outline-variant text-on-surface-variant text-xs font-semibold hover:bg-surface-container transition-all text-center">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Warnings Table --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
            @if($warnings->isEmpty())
                <div class="p-12 text-center flex flex-col items-center justify-center">
                    <span class="material-symbols-outlined text-[48px] text-outline mb-3">verified_user</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">No compliance warnings found</h3>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">Sellers are compliant or no records match the criteria.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                            <tr>
                                <th class="px-5 py-3.5">Merchant / Store</th>
                                <th class="px-5 py-3.5">Violation Category</th>
                                <th class="px-5 py-3.5">Related Listing</th>
                                <th class="px-5 py-3.5">Warning Details</th>
                                <th class="px-5 py-3.5">Issued By / Date</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($warnings as $warning)
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-5 py-4">
                                        <a href="{{ route('admin.users.show', $warning->seller) }}" class="font-bold text-on-surface hover:text-primary transition-colors block">
                                            {{ $warning->seller->sellerProfile->business_name ?? $warning->seller->full_name }}
                                        </a>
                                        <span class="text-[10px] text-on-surface-variant">{{ $warning->seller->email }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700 capitalize">
                                            {{ str_replace('_', ' ', $warning->violation_type) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($warning->product)
                                            <div>
                                                <span class="font-medium text-on-surface block truncate max-w-[200px]" title="{{ $warning->product->name }}">
                                                    {{ $warning->product->name }}
                                                </span>
                                                <span class="text-[10px] text-outline">Category: {{ $warning->product->category?->name ?? 'Uncategorized' }}</span>
                                                @if($warning->product->status === 'archived')
                                                    <span class="text-[10px] text-rose-600 font-semibold block">(Archived)</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-outline text-[11px]">Store-level violation</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-on-surface-variant text-xs max-w-sm">{{ $warning->details }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-medium text-on-surface block">{{ $warning->issuedBy?->full_name ?? 'Administrator' }}</span>
                                        <span class="text-[10px] text-on-surface-variant">{{ $warning->created_at->format('M d, Y h:i A') }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('admin.users.show', $warning->seller) }}"
                                           class="px-3 py-1.5 rounded-full bg-surface-container-high text-on-surface hover:bg-surface-container-highest font-label-md text-xs font-semibold transition-all">
                                            Manage Seller
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($warnings->hasPages())
                    <div class="px-6 py-4 border-t border-outline-variant bg-surface-container-low">
                        {{ $warnings->links() }}
                    </div>
                @endif
            @endif
                    </div>
        </div>
    </div>
</div>


    {{-- Issue Compliance Warning Modal --}}
    <div x-show="issueModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
         @keydown.escape.window="issueModalOpen = false">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg w-full p-6 shadow-xl"
             @click.outside="issueModalOpen = false">
            <h3 class="font-headline-sm text-lg font-bold text-on-surface">Issue Compliance Warning</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                Formal warning issued to merchant regarding product listing or operational guidelines.
            </p>

            <form action="{{ route('admin.compliance.warnings.store') }}" method="POST" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Select Merchant / Seller <span class="text-error">*</span></label>
                    <select name="seller_id" required x-model="selectedSellerId"
                            class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">Choose a registered seller...</option>
                        @foreach($sellers as $seller)
                            <option value="{{ $seller->id }}">
                                {{ $seller->sellerProfile->business_name ?? $seller->full_name }} ({{ $seller->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Violation Category <span class="text-error">*</span></label>
                    <select name="violation_type" required x-model="violationType"
                            class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="category_mismatch">Category Mismatch (Item listed in wrong category)</option>
                        <option value="prohibited_item">Prohibited / Banned Goods</option>
                        <option value="counterfeit">Counterfeit / Unauthorized Replica</option>
                        <option value="misleading_pricing">Misleading Pricing or Attributes</option>
                        <option value="order_cancellation_rate">Excessive Order Cancellations</option>
                        <option value="policy_violation">Platform Terms of Service Breach</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Product ID (Optional)</label>
                    <input type="number" name="product_id" placeholder="Optional Product ID involved"
                           class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Warning Details & Required Remediation <span class="text-error">*</span></label>
                    <textarea name="details" rows="3" required
                              placeholder="Detail the infraction, cite relevant policy clauses, and state the deadline or required corrective action..."
                              class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary"></textarea>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="archive_product" value="1" id="archive_product"
                           class="rounded text-primary focus:ring-primary">
                    <label for="archive_product" class="text-xs text-on-surface font-medium cursor-pointer">
                        Immediately archive / delist the flagged product listing
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="issueModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700">
                        Dispatch Formal Warning
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
