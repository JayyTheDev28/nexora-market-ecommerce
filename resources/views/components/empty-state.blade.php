@props([
    'icon' => 'inbox',
    'title' => 'No items found',
    'description' => null,
    'actionText' => null,
    'actionUrl' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-16 px-6 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant/60 max-w-md mx-auto shadow-xs']) }}>
    <div class="w-16 h-16 rounded-2xl bg-surface-container flex items-center justify-center text-outline-variant mb-4">
        <span class="material-symbols-outlined text-[32px]">{{ $icon }}</span>
    </div>

    <h3 class="font-headline-sm text-base md:text-lg font-bold text-on-surface">
        {{ $title }}
    </h3>

    @if($description)
        <p class="font-body-sm text-xs md:text-sm text-on-surface-variant max-w-sm mt-1.5 leading-relaxed">
            {{ $description }}
        </p>
    @endif

    {{ $slot }}

    @if($actionText && $actionUrl)
        <a href="{{ $actionUrl }}" class="mt-6 inline-flex items-center gap-2 px-6 py-2.5 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all shadow-xs hover:scale-105 active:scale-95">
            <span>{{ $actionText }}</span>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </a>
    @endif
</div>

