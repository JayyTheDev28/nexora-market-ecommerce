@extends('layouts.app')

@section('title', 'Privacy Policy — Nexora Market')

@section('content')
<div class="w-full bg-surface py-8 md:py-12 min-h-[80vh]">
    <div class="max-w-4xl mx-auto px-4 md:px-8">

        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-xs text-on-surface-variant mb-6">
            <a href="{{ url('/') }}" class="hover:text-primary transition-colors">Home</a>
            <span class="text-outline-variant">/</span>
            <span class="text-on-surface font-semibold">Privacy Policy</span>
        </nav>

        {{-- Header Card --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-10 mb-8 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-outline-variant/60">
                <div>
                    <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider mb-2">
                        <span class="material-symbols-outlined text-[20px]">policy</span>
                        <span>Legal & Data Protection Compliance</span>
                    </div>
                    <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Privacy Policy</h1>
                </div>
                <div class="flex items-center gap-2 bg-primary/10 text-primary px-3.5 py-1.5 rounded-full font-label-md text-xs font-semibold">
                    <span class="material-symbols-outlined text-[16px]">verified_user</span>
                    <span>RA 10173 Compliant</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-6 mt-4 text-xs text-on-surface-variant font-medium">
                <div>Last Updated: <span class="text-on-surface font-semibold">October 16, 2026</span></div>
                <div>Effective Date: <span class="text-on-surface font-semibold">January 1, 2025</span></div>
                <div>Jurisdiction: <span class="text-on-surface font-semibold">Republic of the Philippines</span></div>
            </div>
        </div>

        {{-- Content Card --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-10 shadow-xs flex flex-col gap-8 text-on-surface-variant text-xs md:text-sm leading-relaxed">

            {{-- 1. Introduction --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">1</span>
                    Introduction & Commitment to Data Privacy
                </h2>
                <p>
                    Nexora Market ("we," "our," or "the Platform") operates a multi-vendor e-commerce marketplace and logistics management system connecting consumers, verified merchants, central sorting facilities, and delivery riders across the Philippines. We are committed to protecting your privacy in strict accordance with <strong>Republic Act No. 10173</strong>, otherwise known as the <strong>Data Privacy Act of 2012 (DPA)</strong>, its Implementing Rules and Regulations (IRR), and all relevant issuances of the National Privacy Commission (NPC).
                </p>
                <p>
                    This Privacy Policy explains how we collect, process, store, share, and protect your personal data when you register an account, browse the marketplace, list items for sale, manage sorting operations, or complete orders.
                </p>
            </section>

            {{-- 2. Information We Collect --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">2</span>
                    Information We Collect
                </h2>
                <p>
                    Depending on your account role (Buyer, Seller, Logistics Hub Operator, or Courier/Rider), we collect and process the following categories of personal and business information:
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-1">
                    <div class="p-4 bg-surface-container-low rounded-2xl border border-outline-variant/60">
                        <h3 class="font-semibold text-on-surface text-xs md:text-sm mb-1 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-primary text-[18px]">person</span>
                            Personal Information (All Users)
                        </h3>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>Full legal name (First, Middle, Last Name)</li>
                            <li>Date of birth, age, and sex</li>
                            <li>Contact details: verified Philippine mobile number (+63), email address</li>
                            <li>Government-issued valid identification card (for human KYC clearance)</li>
                            <li>Standardized residential address based on PSGC standards (Province, City/Municipality, Barangay, Street)</li>
                        </ul>
                    </div>

                    <div class="p-4 bg-surface-container-low rounded-2xl border border-outline-variant/60">
                        <h3 class="font-semibold text-on-surface text-xs md:text-sm mb-1 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-primary text-[18px]">store</span>
                            Merchant & Logistics Entities
                        </h3>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>Registered business and store name (DTI / SEC / BIR registered)</li>
                            <li>Business permits, licenses, and official documentation</li>
                            <li>Courier vehicle details: vehicle type, official plate number, and OR/CR</li>
                            <li>Hub operating coordinates and facility dispatch logs</li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- 3. Purpose of Processing --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">3</span>
                    Purpose of Processing
                </h2>
                <p>We process your personal information strictly for legitimate commercial and regulatory purposes, including:</p>
                <ul class="list-disc list-inside space-y-1.5 ml-2">
                    <li><strong>Account Authentication & Clearance:</strong> Verifying your identity, preventing duplicate registrations, and enabling administrative approval before issuing marketplace access.</li>
                    <li><strong>Order Lifecycle & Fulfillment:</strong> Processing cart checkouts, coordinating merchant pickups, managing hub sorting, and assigning parcels to couriers.</li>
                    <li><strong>Financial Transactions:</strong> Calculating accurate payments, Cash on Delivery (COD) reconciliations, and platform commission deductions.</li>
                    <li><strong>Fraud Prevention & Platform Security:</strong> Detecting suspicious logins, verifying authentic Philippine mobile phone ownership, and preventing unauthorized account manipulation.</li>
                    <li><strong>Customer Support & Dispute Handling:</strong> Resolving refund requests, order discrepancies, damaged shipments, and buyer-seller feedback.</li>
                </ul>
            </section>

            {{-- 4. Information Sharing & Third-Party Disclosure --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">4</span>
                    Information Sharing & Authorized Disclosures
                </h2>
                <p>
                    Nexora Market does not sell, rent, or trade your personal data. Your information is disclosed only to authorized parties strictly on a need-to-know basis:
                </p>
                <ul class="list-disc list-inside space-y-1.5 ml-2">
                    <li><strong>With Sellers & Buyers:</strong> Merchants receive delivery addresses and contact information solely to prepare packages and address customer queries.</li>
                    <li><strong>With Logistics Hubs & Couriers:</strong> Sorting facilities and assigned delivery riders receive parcel recipient names, shipping addresses, and phone numbers to complete transit and delivery verification.</li>
                    <li><strong>Legal & Regulatory Authorities:</strong> Where required by Philippine law, subpoena, or lawful government directive (e.g., BIR, DTI, Law Enforcement).</li>
                </ul>
            </section>

            {{-- 5. Data Security & Storage --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">5</span>
                    Data Storage & Security Measures
                </h2>
                <p>
                    We implement physical, organizational, and technical security measures compliant with NPC guidelines. All passwords are encrypted using one-way cryptographic hashing (Bcrypt). File uploads (such as Government IDs and business permits) are segregated in access-restricted storage volumes. Sensitive data transfers are secured via 256-bit Secure Socket Layer (SSL/TLS) encryption.
                </p>
            </section>

            {{-- 6. Your Rights Under the Data Privacy Act --}}
            <section class="flex flex-col gap-3">
                <h2 class="font-headline-sm text-base md:text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">6</span>
                    Your Rights Under RA 10173
                </h2>
                <p>As a data subject under Philippine law, you are entitled to the following rights:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-1">
                    <div class="p-3.5 bg-surface-container rounded-xl border border-outline-variant/60">
                        <strong class="text-on-surface text-xs block mb-1">Right to Be Informed</strong>
                        <span class="text-xs text-on-surface-variant">Knowledge of how your personal data will be collected, used, and processed.</span>
                    </div>
                    <div class="p-3.5 bg-surface-container rounded-xl border border-outline-variant/60">
                        <strong class="text-on-surface text-xs block mb-1">Right to Access</strong>
                        <span class="text-xs text-on-surface-variant">Obtain reasonable access to your account data and transactions upon request.</span>
                    </div>
                    <div class="p-3.5 bg-surface-container rounded-xl border border-outline-variant/60">
                        <strong class="text-on-surface text-xs block mb-1">Right to Rectification</strong>
                        <span class="text-xs text-on-surface-variant">Correct inaccurate, obsolete, or misleading personal information via account settings.</span>
                    </div>
                    <div class="p-3.5 bg-surface-container rounded-xl border border-outline-variant/60">
                        <strong class="text-on-surface text-xs block mb-1">Right to Erasure or Blocking</strong>
                        <span class="text-xs text-on-surface-variant">Request the suspension, withdrawal, or deletion of your personal data under lawful grounds.</span>
                    </div>
                </div>
            </section>

            {{-- 7. Contact Us --}}
            <section class="flex flex-col gap-3 p-5 rounded-2xl bg-surface-container-low border border-outline-variant/80">
                <h3 class="font-bold text-on-surface text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">contact_support</span>
                    Data Protection Officer (DPO) Contact Information
                </h3>
                <p class="text-xs">
                    If you have questions, complaints, or wish to exercise your rights under the Data Privacy Act, you may reach out to our designated Data Protection Officer:
                </p>
                <div class="text-xs text-on-surface space-y-1 mt-1 font-medium">
                    <p><strong>Entity:</strong> Nexora Market E-Commerce Philippines</p>
                    <p><strong>Email:</strong> <a href="mailto:privacy@nexoramarket.store" class="text-primary hover:underline">privacy@nexoramarket.store</a></p>
                    <p><strong>Address:</strong> Nexora Technology Park, Santa Rosa, Laguna, Philippines</p>
                </div>
            </section>

        </div>

        {{-- Bottom Action Row --}}
        <div class="mt-8 flex items-center justify-between">
            <a href="{{ route('terms') }}" class="inline-flex items-center gap-1.5 text-xs text-primary font-semibold hover:underline">
                <span>View Terms of Service</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
            <a href="{{ url('/register') }}" class="px-5 py-2 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all shadow-xs">
                Back to Registration
            </a>
        </div>

    </div>
</div>
@endsection
