<header class="fixed top-0 w-full z-50 bg-surface-container-lowest/95 backdrop-blur-md border-b border-outline-variant">
    <div class="h-16 max-w-none px-margin-mobile md:px-margin-desktop flex items-center justify-between gap-gutter">

        {{-- Logo --}}
        <a href="{{ url('/') }}" class="flex items-center gap-stack-unit flex-shrink-0">
            <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-8 h-8 rounded-full object-cover">
            <span class="font-headline-md text-headline-md text-on-surface">Nexora</span>
        </a>

        {{-- Categories + Search --}}
        <div class="flex-1 max-w-2xl hidden md:flex items-center bg-surface-container rounded-full px-4 py-2 border border-outline-variant hover:border-outline transition-colors">
            <button type="button" class="flex items-center gap-2 px-3 border-r border-outline-variant text-on-surface-variant font-label-md text-label-md hover:text-on-surface">
                All Categories
                <span class="material-symbols-outlined text-[20px]">expand_more</span>
            </button>
            {{-- action left as '#' until the products/search route exists (see routes/web.php) --}}
            <form action="#" method="GET" class="flex-1 flex items-center px-4">
                <input
                    type="text"
                    name="q"
                    class="bg-transparent w-full outline-none text-on-surface placeholder:text-on-surface-variant font-body-sm text-body-sm"
                    placeholder="Search products, brands, or categories...">
                <button type="submit" class="text-on-surface-variant ml-2">
                    <span class="material-symbols-outlined">search</span>
                </button>
            </form>
        </div>

        {{-- Cart / Account / Seller --}}
        <nav class="flex items-center gap-6">
            <a href="{{ url('/cart') }}" class="hidden sm:flex items-center text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined">shopping_cart</span>
            </a>

            <a href="#" class="hidden lg:block font-label-md text-label-md text-on-surface-variant hover:text-on-surface">
                Become a Seller
            </a>

            <div class="flex items-center gap-4 border-l border-outline-variant pl-6">
                <a href="{{ url('/login') }}" class="font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-colors">
                    Sign In
                </a>
                <a href="{{ url('/register') }}" class="bg-primary text-on-primary font-label-md text-label-md px-5 py-2 rounded-full hover:bg-primary/90 transition-colors">
                    Get Started
                </a>
            </div>
        </nav>

    </div>
</header>
