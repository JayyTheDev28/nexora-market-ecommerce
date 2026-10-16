<footer class="w-full bg-surface-container-lowest border-t border-outline-variant pt-12 pb-8 mt-12">
    <div class="max-w-none px-4 md:px-8 flex flex-col gap-10">

        {{-- E-Commerce Value & Trust Badges Strip (Shopee / Lazada pattern) --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 bg-surface-container-low/70 rounded-3xl border border-outline-variant/60">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[24px]">local_shipping</span>
                </div>
                <div>
                    <h5 class="font-headline-sm text-xs font-bold text-on-surface">Free Delivery</h5>
                    <p class="font-body-sm text-[11px] text-on-surface-variant">On orders ₱2,000 and above</p>
                </div>
            </div>

            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[24px]">verified</span>
                </div>
                <div>
                    <h5 class="font-headline-sm text-xs font-bold text-on-surface">100% Authentic</h5>
                    <p class="font-body-sm text-[11px] text-on-surface-variant">Verified sellers & products</p>
                </div>
            </div>

            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[24px]">assignment_return</span>
                </div>
                <div>
                    <h5 class="font-headline-sm text-xs font-bold text-on-surface">7-Day Returns</h5>
                    <p class="font-body-sm text-[11px] text-on-surface-variant">Easy refund & dispute policy</p>
                </div>
            </div>

            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[24px]">payments</span>
                </div>
                <div>
                    <h5 class="font-headline-sm text-xs font-bold text-on-surface">Cash on Delivery</h5>
                    <p class="font-body-sm text-[11px] text-on-surface-variant">COD, GCash, Maya & Cards</p>
                </div>
            </div>
        </div>

        {{-- Main Footer Columns --}}
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8 lg:gap-10">

            {{-- Brand info --}}
            <div class="col-span-2 lg:col-span-2 flex flex-col gap-4">
                <a href="{{ auth()->check() ? url(auth()->user()->homeRoute()) : url('/') }}" class="flex items-center gap-2.5 group w-fit">
                    <div class="w-8 h-8 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center p-1 group-hover:bg-primary/20 transition-all">
                        <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-full h-full rounded-md object-cover">
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="font-headline-md font-bold text-on-surface tracking-tight">Nexora</span>
                        <span class="px-1.5 py-0.2 rounded bg-primary text-on-primary text-[10px] font-extrabold uppercase">Market</span>
                    </div>
                </a>
                <p class="text-xs text-on-surface-variant leading-relaxed max-w-sm">
                    The premier Philippine multi-vendor marketplace connecting verified sellers with discerning shoppers for authentic fashion, electronics, and lifestyle goods.
                </p>
                <p class="text-xs text-on-surface-variant">Customer Support: Mon - Sat, 8:00 AM - 8:00 PM</p>
            </div>

            {{-- Role-specific Portal Navigation / Customer Care --}}
            <div class="flex flex-col gap-3">
                @if(auth()->check() && auth()->user()->isSeller())
                    <h4 class="font-label-md text-xs font-bold text-on-surface uppercase tracking-wider">Seller Portal</h4>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ route('seller.dashboard') }}">Seller Dashboard</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ route('seller.products.index') }}">My Inventory</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ route('seller.orders.index') }}">Store Orders</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ route('seller.reports.index') }}">Sales Reports</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="#">Merchant FAQs</a>
                @elseif(auth()->check() && auth()->user()->isSortingCenter())
                    <h4 class="font-label-md text-xs font-bold text-on-surface uppercase tracking-wider">Logistics Portal</h4>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ route('logistics.dashboard') }}">Logistics Hub</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ route('logistics.riders.index') }}">Rider Fleet</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ route('logistics.sorting.index') }}">Sorting Center</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ route('logistics.deliveries.index') }}">Deliveries</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="#">Logistics Support</a>
                @else
                    <h4 class="font-label-md text-xs font-bold text-on-surface uppercase tracking-wider">Customer Care</h4>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ url('/buyer/orders') }}">Track Order</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ url('/cart') }}">My Cart</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ url('/buyer/account') }}">Account Settings</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ url('/catalog') }}">Browse Catalog</a>
                    <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="#">Help & FAQs</a>
                @endif
            </div>

            {{-- Multi-Vendor Platform --}}
            <div class="flex flex-col gap-3">
                <h4 class="font-label-md text-xs font-bold text-on-surface uppercase tracking-wider">Sell on Nexora</h4>
                <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ url('/register') }}">Become a Seller</a>
                <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="{{ url('/seller/dashboard') }}">Seller Centre</a>
                <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="#">Merchant Policies</a>
                <a class="text-xs text-on-surface-variant hover:text-primary transition-colors" href="#">Seller Protection</a>
            </div>

            {{-- Payments & Security --}}
            <div class="col-span-2 md:col-span-1 flex flex-col gap-3">
                <h4 class="font-label-md text-xs font-bold text-on-surface uppercase tracking-wider">Payment Methods</h4>
                <div class="flex flex-wrap gap-1.5">
                    <span class="px-2.5 py-1 rounded-lg bg-surface-container border border-outline-variant/60 text-[10px] font-bold text-on-surface">Cash on Delivery</span>
                    <span class="px-2.5 py-1 rounded-lg bg-primary/10 border border-primary/20 text-[10px] font-bold text-primary">GCash</span>
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-bold text-emerald-700">Maya</span>
                    <span class="px-2.5 py-1 rounded-lg bg-surface-container border border-outline-variant/60 text-[10px] font-bold text-on-surface">Visa</span>
                    <span class="px-2.5 py-1 rounded-lg bg-surface-container border border-outline-variant/60 text-[10px] font-bold text-on-surface">Mastercard</span>
                </div>

                <h4 class="font-label-md text-xs font-bold text-on-surface uppercase tracking-wider mt-2">Logistics</h4>
                <p class="text-xs text-on-surface-variant">Nexora Express Nationwide</p>
            </div>

        </div>

        {{-- Bottom Copyright & Disclaimer --}}
        <div class="border-t border-outline-variant/60 pt-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-[11px] text-on-surface-variant">
            <p>
                &copy; {{ now()->year }} Nexora Market. Built for Modern Philippine E-Commerce.
            </p>
            <div class="flex items-center gap-4">
                <span class="text-emerald-700 font-semibold text-xs">256-Bit SSL Secure Checkout</span>
                <span class="text-outline-variant">•</span>
                <a href="#" class="hover:text-primary transition-colors">Privacy Policy</a>
                <span class="text-outline-variant">•</span>
                <a href="#" class="hover:text-primary transition-colors">Terms of Service</a>
            </div>
        </div>

    </div>
</footer>
