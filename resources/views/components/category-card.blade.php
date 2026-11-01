@props([
    'category', // array|object: name, icon, url, iconBg, iconColor
])

@php
    $category = (object) $category;
    $iconBg = $category->iconBg ?? 'bg-primary/10';
    $iconColor = $category->iconColor ?? 'text-primary';
@endphp

<a
    href="{{ $category->url ?? url('/catalog') }}"
    class="group relative rounded-xl bg-surface-container-lowest border border-outline-variant/60 hover:border-primary/50 p-3 flex flex-col items-center justify-center gap-2 transition-all duration-150 hover:shadow-xs cursor-pointer select-none">
    
    <div class="w-10 h-10 md:w-11 md:h-11 rounded-xl {{ $iconBg }} flex items-center justify-center {{ $iconColor }} group-hover:scale-105 transition-transform duration-150 shadow-2xs">
        <span class="material-symbols-outlined text-[20px] md:text-[22px]">{{ $category->icon ?? 'sell' }}</span>
    </div>

    <span class="font-headline-sm text-xs font-semibold text-on-surface group-hover:text-primary transition-colors text-center line-clamp-1">
        {{ $category->name }}
    </span>
</a>
