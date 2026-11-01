@php
    $navCategories = \App\Models\Category::orderBy('name')->get();
    $currentCategory = request('category');
    $currentQuery = request('q');
    $isBuyerOrGuest = !auth()->check() || auth()->user()->isBuyer();
    $cartCount = (auth()->check() && auth()->user()->isBuyer()) ? (int) auth()->user()->cartItems()->count() : 0;
@endphp

<header
    x-data="{
        mobileMenuOpen: false,
        categoryDropdownOpen: false,
        accountDropdownOpen: false,
        selectedCategory: '{{ $isBuyerOrGuest ? ($currentCategory ?? '') : '' }}',
        selectedCategoryName: '{{ $isBuyerOrGuest ? ($currentCategory ? ($navCategories->firstWhere('slug', $currentCategory)->name ?? 'All Categories') : 'All Categories') : '' }}',
        cartCount: {{ $cartCount }},
        searchQuery: '{{ addslashes($currentQuery ?? '') }}',

        setCategory(slug, name) {
            this.selectedCategory = slug;
            this.selectedCategoryName = name;
            this.categoryDropdownOpen = false;
        }
    }"
    @cart-updated.window="cartCount = $event.detail.count"
    class="sticky top-0 w-full z-50 bg-surface-container-lowest/95 backdrop-blur-md border-b border-outline-variant shadow-xs">

    {{-- Top Mini Utility Bar (Desktop & Tablet) --}}
    <div class="hidden sm:block border-b border-outline-variant/60 bg-surface-container-low/60 text-on-surface-variant text-[11px] font-medium">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                @if($isBuyerOrGuest)
                    <span class="text-primary font-semibold">100% Authentic Products</span>
                    <span class="text-outline-variant">•</span>
                    <span>Free Delivery over ₱2,000</span>
                @else
                    <span class="text-primary font-semibold flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        {{ auth()->user()->roleLabel() }} Workspace
                    </span>
                    <span class="text-outline-variant">•</span>
                    <span>
                        @if(auth()->user()->isSeller())
                            {{ auth()->user()->sellerProfile?->business_name ?? 'Store Operations' }}
                        @elseif(auth()->user()->isSortingCenter())
                            {{ auth()->user()->sortingCenterProfile?->business_name ?? 'Sorting & Logistics Hub' }}
                        @else
                            Operations Portal
                        @endif
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-4">
                @auth
                    @if(auth()->user()->isSeller())
                        <a href="{{ route('seller.dashboard') }}" class="hover:text-primary transition-colors">
                            Seller Dashboard
                        </a>
                    @elseif(auth()->user()->isSortingCenter())
                        <a href="{{ route('logistics.dashboard') }}" class="hover:text-primary transition-colors">
                            Logistics Hub
                        </a>
                    @elseif(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="hover:text-primary transition-colors">
                            Admin Dashboard
                        </a>
                    @elseif(auth()->user()->isCourier())
                        <a href="{{ route('courier.dashboard') }}" class="hover:text-primary transition-colors">
                            Rider Dashboard
                        </a>
                    @else
                        <a href="{{ route('buyer.orders') }}" class="hover:text-primary transition-colors">
                            Track My Order
                        </a>
                    @endif
                @else
                    <a href="{{ url('/register') }}" class="hover:text-primary transition-colors">
                        Become a Seller
                    </a>
                    <span class="text-outline-variant">•</span>
                    <a href="{{ url('/login') }}" class="hover:text-primary transition-colors">
                        Help Center
                    </a>
                @endauth
            </div>
        </div>
    </div>

    {{-- Main Navbar Row --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between gap-3 sm:gap-6">

        {{-- Logo --}}
        <a href="{{ auth()->check() ? url(auth()->user()->homeRoute()) : url('/') }}" class="flex items-center gap-2.5 flex-shrink-0 group">
            <div class="w-9 h-9 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center p-1 group-hover:bg-primary/20 transition-all">
                <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-full h-full rounded-lg object-cover">
            </div>
            <div class="flex flex-col">
                <div class="flex items-center gap-1.5">
                    <span class="font-headline-md text-headline-md tracking-tight text-on-surface font-bold">Nexora</span>
                    <span class="px-1.5 py-0.2 rounded-md bg-primary text-on-primary text-[10px] font-extrabold uppercase tracking-wider">Market</span>
                </div>
                <span class="hidden lg:block text-[10px] text-on-surface-variant -mt-1 font-medium tracking-wide">Multi-Vendor Marketplace</span>
            </div>
        </a>

        {{-- Search Bar / Workspace Indicator (Desktop & Tablet) --}}
        <div class="flex-1 max-w-2xl hidden md:flex items-center">
            @if($isBuyerOrGuest)
                <form action="{{ url('/catalog') }}" method="GET" class="w-full flex items-center bg-surface-container rounded-full border border-outline-variant hover:border-primary/60 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 transition-all shadow-xs pl-2 pr-1.5 py-1">
                    
                    {{-- Category Selector Dropdown --}}
                    <div class="relative flex-shrink-0" @click.outside="categoryDropdownOpen = false">
                        <button
                            type="button"
                            @click="categoryDropdownOpen = !categoryDropdownOpen"
                            class="flex items-center gap-1.5 px-3 py-1.5 border-r border-outline-variant text-on-surface-variant font-label-md text-xs hover:text-on-surface transition-colors cursor-pointer select-none">
                            <span class="max-w-[110px] truncate" x-text="selectedCategoryName"></span>
                            <span class="material-symbols-outlined text-[18px] text-outline transition-transform duration-200" :class="categoryDropdownOpen ? 'rotate-180' : ''">expand_more</span>
                        </button>

                        {{-- Dropdown Menu --}}
                        <div
                            x-show="categoryDropdownOpen"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                            class="absolute left-0 top-full mt-2 w-56 max-h-72 overflow-y-auto bg-surface-container-lowest rounded-2xl border border-outline-variant shadow-xl py-2 z-50">
                            
                            <button
                                type="button"
                                @click="setCategory('', 'All Categories')"
                                class="w-full flex items-center gap-2.5 px-4 py-2 text-left font-body-sm text-xs hover:bg-surface-container transition-colors"
                                :class="!selectedCategory ? 'text-primary font-semibold bg-primary/5' : 'text-on-surface'">
                                <span class="material-symbols-outlined text-[18px]">category</span>
                                All Categories
                            </button>

                            <div class="border-t border-outline-variant my-1"></div>

                            @foreach($navCategories as $cat)
                                <button
                                    type="button"
                                    @click="setCategory('{{ $cat->slug }}', '{{ addslashes($cat->name) }}')"
                                    class="w-full flex items-center gap-2.5 px-4 py-2 text-left font-body-sm text-xs hover:bg-surface-container transition-colors"
                                    :class="selectedCategory === '{{ $cat->slug }}' ? 'text-primary font-semibold bg-primary/5' : 'text-on-surface'">
                                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant">{{ $cat->icon ?? 'label' }}</span>
                                    <span class="truncate">{{ $cat->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <input type="hidden" name="category" :value="selectedCategory">

                    {{-- Search Input --}}
                    <input
                        type="text"
                        name="q"
                        x-model="searchQuery"
                        class="flex-1 bg-transparent px-3 py-1.5 outline-none text-on-surface placeholder:text-on-surface-variant/70 font-body-sm text-sm"
                        placeholder="Search products, brands, or verified sellers...">

                    {{-- Search Button --}}
                    <button
                        type="submit"
                        class="bg-primary text-on-primary hover:bg-primary/90 px-4 py-2 rounded-full font-label-md text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-all hover:shadow-md cursor-pointer flex-shrink-0">
                        <span class="material-symbols-outlined text-[18px]">search</span>
                        <span>Search</span>
                    </button>
                </form>
            @else
                <div class="w-full flex items-center justify-between bg-surface-container-low px-4 py-2 rounded-2xl border border-outline-variant/60">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <div class="flex flex-col">
                            <span class="font-headline-sm text-xs font-bold text-on-surface">
                                {{ auth()->user()->roleLabel() }} Management Workspace
                            </span>
                            <span class="text-[11px] text-on-surface-variant font-medium">
                                @if(auth()->user()->isSeller())
                                    {{ auth()->user()->sellerProfile?->business_name ?? 'Store Operations' }}
                                @elseif(auth()->user()->isSortingCenter())
                                    {{ auth()->user()->sortingCenterProfile?->business_name ?? 'Sorting & Logistics Hub' }}
                                @else
                                    Nexora Operations Portal
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ auth()->user()->homeRoute() }}" class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-primary/10 text-primary hover:bg-primary/20 font-label-md text-xs font-semibold transition-colors">
                            <span class="material-symbols-outlined text-[16px]">dashboard</span>
                            <span>Open Dashboard</span>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        {{-- Right Side Actions (Cart & User Account) --}}
        <div class="flex items-center gap-2 md:gap-4">

            {{-- Cart Button (Only visible for buyers and guests) --}}
            @if($isBuyerOrGuest)
                <a
                    href="{{ url('/cart') }}"
                    class="relative flex items-center justify-center w-10 h-10 md:w-11 md:h-11 rounded-full bg-surface-container hover:bg-surface-container-high text-on-surface transition-all cursor-pointer"
                    aria-label="Shopping Cart">
                    <span class="material-symbols-outlined text-[22px]">shopping_cart</span>
                    <span
                        x-show="cartCount > 0"
                        x-cloak
                        x-text="cartCount > 99 ? '99+' : cartCount"
                        class="absolute -top-1 -right-1 min-w-[20px] h-[20px] px-1 rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center border-2 border-surface-container-lowest shadow-sm animate-in fade-in">
                    </span>
                </a>
            @endif

            {{-- Account Menu / Auth Buttons --}}
            @auth
                {{-- Logged in user dropdown --}}
                <div class="relative hidden sm:block" @click.outside="accountDropdownOpen = false">
                    <button
                        type="button"
                        @click="accountDropdownOpen = !accountDropdownOpen"
                        class="flex items-center gap-2 pl-2 pr-3 py-1.5 rounded-full bg-surface-container hover:bg-surface-container-high border border-outline-variant text-on-surface font-label-md text-xs font-semibold transition-all cursor-pointer">
                        <div class="w-7 h-7 rounded-full bg-primary text-on-primary font-bold text-xs flex items-center justify-center">
                            {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                        </div>
                        <span class="max-w-[100px] truncate">{{ auth()->user()->first_name }}</span>
                        <span class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform duration-200" :class="accountDropdownOpen ? 'rotate-180' : ''">expand_more</span>
                    </button>

                    {{-- Account Dropdown Menu --}}
                    <div
                        x-show="accountDropdownOpen"
                        x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                        class="absolute right-0 top-full mt-2 w-64 bg-surface-container-lowest rounded-2xl border border-outline-variant shadow-xl p-2 z-50">
                        
                        <div class="px-3 py-2.5 bg-surface-container-low rounded-xl mb-1">
                            <p class="font-headline-sm text-xs font-bold text-on-surface truncate">{{ auth()->user()->full_name }}</p>
                            <p class="font-body-sm text-[11px] text-on-surface-variant truncate">{{ auth()->user()->email }}</p>
                            <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full bg-primary/10 text-primary font-semibold text-[10px]">
                                {{ auth()->user()->roleLabel() }}
                            </span>
                        </div>

                        <div class="flex flex-col gap-0.5 pt-1">
                            @if(auth()->user()->isBuyer())
                                <a href="{{ route('buyer.dashboard') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">dashboard</span>
                                    Buyer Dashboard
                                </a>
                                <a href="{{ route('buyer.orders') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">package_2</span>
                                    My Orders
                                </a>
                                <a href="{{ route('buyer.wishlist') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">favorite</span>
                                    Wishlist & Stores
                                </a>
                                <a href="{{ route('buyer.vouchers') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">confirmation_number</span>
                                    Vouchers & Wallet
                                </a>
                                <a href="{{ route('buyer.chat.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">chat</span>
                                    Seller Messages
                                </a>
                                <a href="{{ route('buyer.account') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">manage_accounts</span>
                                    Account Settings
                                </a>
                            @elseif(auth()->user()->isSeller())
                                <a href="{{ route('seller.dashboard') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">dashboard</span>
                                    Seller Dashboard
                                </a>
                                <a href="{{ route('seller.products.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">inventory_2</span>
                                    Manage Products
                                </a>
                                <a href="{{ route('seller.orders.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">receipt_long</span>
                                    Customer Orders
                                </a>
                                <a href="{{ route('seller.reports.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">bar_chart</span>
                                    Analytics & Reports
                                </a>
                                <a href="{{ route('seller.feedback.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">rate_review</span>
                                    Customer Reviews
                                </a>
                            @elseif(auth()->user()->isSortingCenter())
                                <a href="{{ route('logistics.dashboard') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">dashboard</span>
                                    Operations Hub
                                </a>
                                <a href="{{ route('logistics.riders.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">two_wheeler</span>
                                    Riders Management
                                </a>
                                <a href="{{ route('logistics.remittances.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">account_balance</span>
                                    COD Remittances
                                </a>
                                <a href="{{ route('logistics.sorting.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">hub</span>
                                    Sorting Center
                                </a>
                                <a href="{{ route('logistics.account.show') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">settings</span>
                                    Facility Settings
                                </a>
                            @elseif(auth()->user()->isCourier())
                                <a href="{{ route('courier.dashboard') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">dashboard</span>
                                    Rider Dashboard
                                </a>
                                <a href="{{ route('courier.earnings') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">payments</span>
                                    Earnings & Remittance
                                </a>
                            @elseif(auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">dashboard</span>
                                    Admin Dashboard
                                </a>
                                <a href="{{ route('admin.applications.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">how_to_reg</span>
                                    Account Applications
                                </a>
                                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">group</span>
                                    User Accounts
                                </a>
                                <a href="{{ route('admin.disputes.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">report_problem</span>
                                    Dispute Claims
                                </a>
                                <a href="{{ route('admin.commissions.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">payments</span>
                                    Commissions (10%)
                                </a>
                                <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">tune</span>
                                    Platform Settings
                                </a>
                                <a href="{{ route('admin.account.show') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">manage_accounts</span>
                                    Admin Profile
                                </a>
                            @else
                                <a href="{{ auth()->user()->homeRoute() }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-primary">dashboard</span>
                                    My Dashboard
                                </a>
                            @endif

                            <div class="border-t border-outline-variant my-1"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-error hover:bg-error-container/30 transition-colors cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]">logout</span>
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <div class="hidden sm:flex items-center gap-2">
                    <a href="{{ url('/login') }}" class="font-label-md text-xs font-semibold px-4 py-2 rounded-full text-on-surface hover:bg-surface-container transition-colors">
                        Sign In
                    </a>
                    <a href="{{ url('/register') }}" class="bg-primary text-on-primary hover:bg-primary/90 font-label-md text-xs font-semibold px-4 py-2 rounded-full shadow-xs transition-all">
                        Register
                    </a>
                </div>
            @endauth

            {{-- Mobile Menu Hamburger Button (Under md) --}}
            <button
                type="button"
                @click="mobileMenuOpen = !mobileMenuOpen"
                class="md:hidden flex items-center justify-center w-10 h-10 rounded-full bg-surface-container text-on-surface hover:bg-surface-container-high transition-colors"
                aria-label="Toggle navigation menu">
                <span class="material-symbols-outlined text-[24px]" x-text="mobileMenuOpen ? 'close' : 'menu'"></span>
            </button>

        </div>
    </div>

    {{-- Mobile Search Row (Only for buyers & guests) --}}
    @if($isBuyerOrGuest)
        <div class="md:hidden max-w-7xl mx-auto px-4 pb-3 pt-0">
            <form action="{{ url('/catalog') }}" method="GET" class="w-full flex items-center bg-surface-container rounded-full border border-outline-variant focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 transition-all pl-3.5 pr-1.5 py-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant mr-2">search</span>
                <input
                    type="text"
                    name="q"
                    value="{{ $currentQuery ?? '' }}"
                    class="flex-1 bg-transparent py-1 outline-none text-on-surface placeholder:text-on-surface-variant font-body-sm text-xs"
                    placeholder="Search products, brands, or categories...">
                <button type="submit" class="bg-primary text-on-primary p-1.5 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </button>
            </form>
        </div>
    @endif

    {{-- Category Shortcuts Quick-Bar (Desktop — Only for buyers & guests) --}}
    @if($isBuyerOrGuest)
        <div class="hidden lg:block border-t border-outline-variant/60 bg-surface-container-lowest">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-9 flex items-center justify-between gap-6 text-xs font-medium text-on-surface-variant overflow-x-auto">
                <div class="flex items-center gap-6 whitespace-nowrap">
                    <a href="{{ url('/catalog') }}" class="hover:text-primary transition-colors font-semibold text-on-surface">
                        All Products
                    </a>
                    <span class="text-outline-variant">|</span>
                    @foreach($navCategories->take(7) as $cat)
                        <a
                            href="{{ url('/catalog?category=' . $cat->slug) }}"
                            class="hover:text-primary transition-colors {{ $currentCategory === $cat->slug ? 'text-primary font-bold' : '' }}">
                            {{ $cat->name }}
                        </a>
                    @endforeach
                </div>

                <div class="flex items-center gap-4 flex-shrink-0">
                    <a href="{{ url('/catalog?category=electronics') }}" class="text-primary font-semibold hover:underline">
                        Special Offers
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Mobile Slide-Over Drawer --}}
    <div
        x-show="mobileMenuOpen"
        x-cloak
        class="fixed inset-0 z-50 md:hidden"
        style="top: 0;">
        
        {{-- Backdrop --}}
        <div
            x-show="mobileMenuOpen"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileMenuOpen = false"
            class="fixed inset-0 bg-inverse-surface/60 backdrop-blur-xs"></div>

        {{-- Drawer Body --}}
        <div
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative w-4/5 max-w-sm h-full bg-surface-container-lowest shadow-2xl flex flex-col justify-between overflow-y-auto">
            
            <div class="p-5 flex flex-col gap-6">

                {{-- Header Inside Drawer --}}
                <div class="flex items-center justify-between pb-4 border-b border-outline-variant">
                    <a href="{{ auth()->check() ? url(auth()->user()->homeRoute()) : url('/') }}" class="flex items-center gap-2">
                        <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-8 h-8 rounded-lg object-cover">
                        <span class="font-headline-md font-bold text-on-surface">Nexora Market</span>
                    </a>
                    <button type="button" @click="mobileMenuOpen = false" class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant">
                        <span class="material-symbols-outlined text-[22px]">close</span>
                    </button>
                </div>

                {{-- User Profile / Role Info --}}
                @auth
                    <div class="p-3.5 bg-surface-container rounded-2xl flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary text-on-primary font-bold text-base flex items-center justify-center flex-shrink-0">
                            {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-headline-sm text-xs font-bold text-on-surface truncate">{{ auth()->user()->full_name }}</p>
                            <p class="font-body-sm text-[11px] text-on-surface-variant truncate">{{ auth()->user()->email }}</p>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded-full bg-primary/10 text-primary font-semibold text-[10px]">
                                {{ auth()->user()->roleLabel() }}
                            </span>
                        </div>
                    </div>

                    {{-- Role-specific Mobile Links --}}
                    <div class="flex flex-col gap-1">
                        @if(auth()->user()->isBuyer())
                            <a href="{{ route('buyer.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">dashboard</span>
                                Buyer Dashboard
                            </a>
                            <a href="{{ route('buyer.orders') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">receipt_long</span>
                                My Orders
                            </a>
                            <a href="{{ url('/cart') }}" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-[20px] text-primary">shopping_cart</span>
                                    Shopping Cart
                                </div>
                                <span x-show="cartCount > 0" x-text="cartCount" class="px-2 py-0.5 rounded-full bg-primary text-on-primary text-[10px] font-bold"></span>
                            </a>
                            <a href="{{ route('buyer.account') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">person</span>
                                My Account
                            </a>
                        @elseif(auth()->user()->isSeller())
                            <a href="{{ route('seller.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">dashboard</span>
                                Seller Dashboard
                            </a>
                            <a href="{{ route('seller.products.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">inventory_2</span>
                                My Inventory
                            </a>
                            <a href="{{ route('seller.orders.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">receipt_long</span>
                                Store Orders
                            </a>
                            <a href="{{ route('seller.reports.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">bar_chart</span>
                                Sales Reports
                            </a>
                            <a href="{{ route('seller.feedback.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">rate_review</span>
                                Customer Feedback
                            </a>
                        @elseif(auth()->user()->isSortingCenter())
                            <a href="{{ route('logistics.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">dashboard</span>
                                Logistics Dashboard
                            </a>
                            <a href="{{ route('logistics.riders.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">two_wheeler</span>
                                Rider Management
                            </a>
                            <a href="{{ route('logistics.pickups.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">hail</span>
                                Pickups
                            </a>
                            <a href="{{ route('logistics.incoming.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">qr_code_scanner</span>
                                Incoming Parcels
                            </a>
                            <a href="{{ route('logistics.sorting.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">hub</span>
                                Sorting Hub
                            </a>
                            <a href="{{ route('logistics.assignments.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">assignment_ind</span>
                                Dispatch Assignments
                            </a>
                            <a href="{{ route('logistics.deliveries.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">local_shipping</span>
                                Deliveries
                            </a>
                            <a href="{{ route('logistics.reports.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">analytics</span>
                                Logistics Reports
                            </a>
                            <a href="{{ route('logistics.account.show') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">settings</span>
                                Facility Settings
                            </a>
                        @elseif(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-primary">admin_panel_settings</span>
                                Admin Console
                            </a>
                        @endif
                    </div>
                @else
                    <div class="flex flex-col gap-2 p-3 bg-primary/5 rounded-2xl border border-primary/20">
                        <p class="font-headline-sm text-xs font-bold text-on-surface">Welcome to Nexora</p>
                        <p class="font-body-sm text-[11px] text-on-surface-variant">Sign in for exclusive deals, order tracking, and fast checkout.</p>
                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <a href="{{ url('/login') }}" class="flex items-center justify-center py-2 px-3 rounded-full border border-outline-variant font-label-md text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                                Sign In
                            </a>
                            <a href="{{ url('/register') }}" class="flex items-center justify-center py-2 px-3 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-colors">
                                Register
                            </a>
                        </div>
                    </div>
                @endauth

                {{-- Categories List (Buyer & Guest only) --}}
                @if($isBuyerOrGuest)
                    <div class="flex flex-col gap-2">
                        <p class="font-label-sm text-[10px] uppercase tracking-wider text-outline font-bold">Shop by Category</p>
                        <div class="flex flex-col gap-1 max-h-60 overflow-y-auto">
                            <a href="{{ url('/catalog') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                <span class="material-symbols-outlined text-[18px] text-primary">apps</span>
                                All Products
                            </a>
                            @foreach($navCategories as $cat)
                                <a href="{{ url('/catalog?category=' . $cat->slug) }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant">{{ $cat->icon ?? 'sell' }}</span>
                                    {{ $cat->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

            {{-- Drawer Footer --}}
            <div class="p-5 border-t border-outline-variant bg-surface-container-low/50">
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-xs font-semibold text-error hover:bg-error-container/30 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">logout</span>
                            Sign Out
                        </button>
                    </form>
                @else
                    <p class="text-[11px] text-center text-on-surface-variant">
                        &copy; {{ now()->year }} Nexora Market. All rights reserved.
                    </p>
                @endauth
            </div>

        </div>
    </div>

</header>
