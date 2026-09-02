<footer class="w-full bg-surface-container-lowest border-t border-outline-variant pt-16 pb-8">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop flex flex-col gap-12">

        <div class="grid grid-cols-2 md:grid-cols-5 lg:grid-cols-6 gap-12">

            <div class="col-span-2 lg:col-span-2">
                <div class="flex items-center gap-stack-unit mb-6">
                    <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-6 h-6 rounded-full">
                    <span class="font-headline-sm text-headline-sm text-on-surface">Nexora</span>
                </div>
                <p class="text-body-sm text-on-surface-variant leading-relaxed max-w-sm">
                    Premium multi-vendor marketplace for high-end curated goods.
                </p>
            </div>

            <div class="flex flex-col gap-4">
                <h4 class="font-label-md text-label-md text-on-surface uppercase tracking-wider">Marketplace</h4>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">All Products</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">New Arrivals</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Best Sellers</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Curated Collections</a>
            </div>

            <div class="flex flex-col gap-4">
                <h4 class="font-label-md text-label-md text-on-surface uppercase tracking-wider">Customer</h4>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">My Orders</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Track Order</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">My Account</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Help Center</a>
            </div>

            {{-- Secondary: seller links exist but stay visually equal-weighted, never promoted --}}
            <div class="flex flex-col gap-4">
                <h4 class="font-label-md text-label-md text-on-surface uppercase tracking-wider">Seller</h4>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Seller Dashboard</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Become a Seller</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Seller Protection</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Resource Center</a>
            </div>

            <div class="flex flex-col gap-4">
                <h4 class="font-label-md text-label-md text-on-surface uppercase tracking-wider">Delivery</h4>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Track Delivery</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Delivery Info</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Become a Courier</a>
                <a class="text-body-sm text-on-surface-variant hover:text-primary" href="#">Courier Support</a>
            </div>

        </div>

        <div class="border-t border-outline-variant pt-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <p class="text-body-sm text-on-surface-variant font-label-sm text-label-sm">
                &copy; {{ now()->year }} Nexora Market. Built for Excellence.
            </p>
            <div class="flex items-center gap-6">
                <a class="text-on-surface-variant hover:text-primary transition-colors" href="#">
                    <span class="material-symbols-outlined">public</span>
                </a>
                <a class="text-on-surface-variant hover:text-primary transition-colors" href="#">
                    <span class="material-symbols-outlined">hub</span>
                </a>
                <a class="text-on-surface-variant hover:text-primary transition-colors" href="#">
                    <span class="material-symbols-outlined">mail</span>
                </a>
            </div>
        </div>

    </div>
</footer>
