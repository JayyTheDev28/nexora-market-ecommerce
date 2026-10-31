@extends('layouts.app')

@section('title', 'Terms of Service — Nexora Market')

@section('content')
<div class="w-full bg-surface py-8 md:py-12 min-h-[80vh]">
    <div class="max-w-4xl mx-auto px-4 md:px-8">

        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-xs text-on-surface-variant mb-6">
            <a href="{{ url('/') }}" class="hover:text-primary transition-colors">Home</a>
            <span class="text-outline-variant">/</span>
            <span class="text-on-surface font-semibold">Terms of Service</span>
        </nav>

        {{-- Header Card --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-10 mb-8 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-outline-variant/60">
                <div>
                    <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider mb-2">
                        <span class="material-symbols-outlined text-[20px]">gavel</span>
                        <span>Platform User Agreement</span>
                    </div>
                    <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Terms of Service</h1>
                </div>
                <div class="flex items-center gap-2 bg-primary/10 text-primary px-3.5 py-1.5 rounded-full font-label-md text-xs font-semibold">
                    <span class="material-symbols-outlined text-[16px]">verified</span>
                    <span>RA 8792 & RA 7394 Compliant</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-6 mt-4 text-xs text-on-surface-variant font-medium">
                <div>Last Updated: <span class="text-on-surface font-semibold">October 16, 2026</span></div>
                <div>Effective Date: <span class="text-on-surface font-semibold">January 1, 2025</span></div>
                <div>Governing Law: <span class="text-on-surface font-semibold">Republic of the Philippines</span></div>
            </div>
        </div>

        {{-- Content Card --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-10 shadow-xs flex flex-col gap-8 text-on-surface-variant text-xs md:text-sm leading-relaxed">

            {{-- 1. Acceptance --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">1</span>
                    Acceptance of Terms
                </h2>
                <p>
                    By registering for, accessing, or using Nexora Market ("Nexora," "we," "us," or "the Platform"), you agree to be bound by these Terms of Service ("Terms") and our Privacy Policy. If you do not agree to these terms, you must immediately discontinue use of the platform and refrain from creating an account.
                </p>
                <p>
                    These Terms constitute a legally binding agreement under the <strong>Electronic Commerce Act of 2000 (Republic Act No. 8792)</strong> and the <strong>Consumer Act of the Philippines (Republic Act No. 7394)</strong>.
                </p>
            </section>

            {{-- 2. Account Registration & Role Integrity --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">2</span>
                    Account Registration & Role Integrity
                </h2>
                <p>Nexora operates distinct role profiles with strict access boundaries:</p>
                <ul class="list-disc list-inside space-y-1.5 ml-2">
                    <li><strong>Buyer Accounts:</strong> Authorized to browse products, add items to cart, initiate checkout, confirm deliveries, submit reviews, and file disputes.</li>
                    <li><strong>Seller / Merchant Accounts:</strong> Authorized to manage inventory, set prices, fulfill orders, and inspect financial reports. <em>Sellers are strictly partitioned from purchasing and ordering through merchant accounts.</em></li>
                    <li><strong>Logistics / Sorting Center Hubs:</strong> Authorized to manage regional delivery areas, process incoming merchant parcels, perform sorting scans, and dispatch to couriers. <em>Sorting personnel are strictly partitioned from purchasing.</em></li>
                    <li><strong>Couriers / Delivery Riders:</strong> Authorized to accept parcel manifests, transport shipments, and verify delivery completion.</li>
                </ul>
                <div class="p-4 bg-surface-container-low rounded-2xl border border-outline-variant/60 mt-1">
                    <h3 class="font-bold text-on-surface text-xs mb-1">Registration Requirements & Data Accuracy</h3>
                    <ul class="list-disc list-inside space-y-1 text-xs">
                        <li>Names must consist of legal characters (2 to 50 characters, letters and spaces only, no special symbols).</li>
                        <li>No duplicate full names are permitted across the marketplace.</li>
                        <li>Contact numbers must be verified 11-digit Philippine mobile numbers (+63). Duplicate phone numbers are prohibited.</li>
                        <li>Registered birth dates cannot be in 2026 or later, and account age cannot be zero.</li>
                        <li>Business names for Sellers and Sorting Centers must be unique across the platform.</li>
                    </ul>
                </div>
            </section>

            {{-- 3. Identity Verification & Approval Workflows --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">3</span>
                    Verification & Approval Workflows (KYC)
                </h2>
                <p>
                    To maintain marketplace safety and eradicate fraudulent merchants or fake buyers, all newly registered accounts require:
                </p>
                <ol class="list-decimal list-inside space-y-1 ml-2">
                    <li>Email verification via secure signed link.</li>
                    <li>Administrative review of submitted government-issued identification cards and DTI/business permits.</li>
                </ol>
                <p class="mt-1">
                    Nexora reserves the right to decline or suspend any application that fails verification or provides illegible or counterfeit documents.
                </p>
            </section>

            {{-- 4. Pricing, Payments, and Commissions --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">4</span>
                    Pricing, Payments, and Platform Commission
                </h2>
                <p>
                    All product prices are stated in Philippine Pesos (₱/PHP) and include statutory taxes where applicable.
                </p>
                <ul class="list-disc list-inside space-y-1.5 ml-2">
                    <li><strong>Payment Methods:</strong> Nexora supports Cash on Delivery (COD), e-wallets (GCash, Maya), and Credit/Debit cards (Visa, Mastercard).</li>
                    <li><strong>Marketplace Commission:</strong> The Platform automatically deducts a standard <strong>2.5% marketplace commission</strong> on confirmed orders to maintain operations, secure infrastructure, and logistics coordination.</li>
                </ul>
            </section>

            {{-- 5. Shipping, Sorting, & Delivery --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">5</span>
                    Shipping & Sorting Center Fulfillment
                </h2>
                <p>
                    Orders placed on Nexora are fulfilled through our integrated logistics chain:
                </p>
                <p class="text-xs bg-surface-container p-3.5 rounded-xl border border-outline-variant/60 font-mono">
                    Merchant Preparation &rarr; Driver Pickup &rarr; Hub Incoming Intake &rarr; Sorting Scan &rarr; Rider Dispatch &rarr; Delivery Confirmation
                </p>
                <p>
                    Delivery estimates are dependent on regional distances and weather conditions. Buyers must provide an accurate PSGC-standard address and reachable mobile number for delivery riders.
                </p>
            </section>

            {{-- 6. Returns, Refunds, and 7-Day Buyer Protection --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">6</span>
                    7-Day Returns & Dispute Resolution
                </h2>
                <p>
                    In accordance with the Consumer Act of the Philippines, buyers are entitled to file for a return, replacement, or refund within <strong>seven (7) calendar days</strong> from delivery confirmation for items that are:
                </p>
                <ul class="list-disc list-inside space-y-1 ml-2">
                    <li>Damaged, defective, or non-functional upon receipt.</li>
                    <li>Significantly different from merchant descriptions or missing stated accessories.</li>
                    <li>Counterfeit or unauthentic items.</li>
                </ul>
            </section>

            {{-- 7. Prohibited Conduct & Account Suspension --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">7</span>
                    Prohibited Conduct & Account Termination
                </h2>
                <p>The following activities are strictly prohibited and result in immediate termination and referral to law enforcement:</p>
                <ul class="list-disc list-inside space-y-1 ml-2">
                    <li>Listing prohibited, hazardous, or illegal goods (firearms, narcotics, stolen property).</li>
                    <li>Creating multiple dummy accounts, fabricated reviews, or artificially inflating store ratings.</li>
                    <li>Attempting to bypass role access controls (e.g. attempting to submit orders using seller credentials).</li>
                    <li>Harassment of delivery riders, merchants, or support personnel.</li>
                </ul>
            </section>

            {{-- 8. Governing Law & Dispute Forum --}}
            <section class="flex flex-col gap-3 p-5 rounded-2xl bg-surface-container-low border border-outline-variant/80">
                <h3 class="font-bold text-on-surface text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">account_balance</span>
                    Governing Law & Legal Venue
                </h3>
                <p class="text-xs">
                    These Terms shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any judicial disputes arising from the use of this marketplace shall be submitted to the exclusive jurisdiction of the competent courts in the Province of Laguna or Metro Manila, Philippines.
                </p>
            </section>

        </div>

        {{-- Bottom Action Row --}}
        <div class="mt-8 flex items-center justify-between">
            <a href="{{ route('privacy') }}" class="inline-flex items-center gap-1.5 text-xs text-primary font-semibold hover:underline">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>View Privacy Policy</span>
            </a>
            <a href="{{ url('/register') }}" class="px-5 py-2 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all shadow-xs">
                Back to Registration
            </a>
        </div>

    </div>
</div>
@endsection
