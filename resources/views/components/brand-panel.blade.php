@props(['caption' => 'The modern marketplace for authentic Philippine goods.'])

<div class="hidden lg:flex lg:w-5/12 xl:w-2/5 h-full flex-shrink-0 relative overflow-hidden bg-gradient-to-br from-surface-container via-surface to-primary-fixed/20 border-r border-outline-variant/60 flex-col justify-between p-8 xl:p-12 select-none">

    {{-- Subtle Ambient Glow --}}
    <div class="absolute -top-24 -left-24 w-80 h-80 rounded-full bg-primary/5 blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 right-0 w-80 h-80 rounded-full bg-primary-container/5 blur-3xl pointer-events-none"></div>

    {{-- Brand Header --}}
    <div class="relative z-10">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-3 group">
            <div class="w-10 h-10 rounded-xl bg-surface-container-lowest border border-outline-variant/80 p-1.5 shadow-xs group-hover:border-primary transition-colors">
                <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-full h-full rounded-lg object-cover">
            </div>
            <div class="flex items-center gap-1.5">
                <span class="font-headline-md text-xl tracking-tight text-on-surface font-extrabold">Nexora</span>
                <span class="px-2 py-0.5 rounded-full bg-primary text-on-primary text-[10px] font-bold uppercase tracking-wider">Market</span>
            </div>
        </a>
    </div>

    {{-- Central Value Proposition --}}
    <div class="relative z-10 my-auto py-8 flex flex-col gap-4 max-w-sm">
        <h2 class="font-display-lg text-2xl xl:text-3xl font-extrabold text-on-surface tracking-tight leading-snug">
            Discover authentic products from verified merchants.
        </h2>
        <p class="font-body-md text-xs xl:text-sm text-on-surface-variant leading-relaxed">
            Connect directly with local and national sellers, track deliveries in real time, and manage your account with ease.
        </p>
    </div>

    {{-- Simple Footer Caption --}}
    <div class="relative z-10 pt-4 border-t border-outline-variant/50">
        <p class="text-xs text-on-surface-variant leading-relaxed">
            {{ $caption }}
        </p>
    </div>

</div>
