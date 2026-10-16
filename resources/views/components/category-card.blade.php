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
    class="group relative rounded-2xl bg-surface-container-lowest border border-outline-variant/60 hover:border-primary/50 p-4 flex flex-col items-center justify-center gap-2.5 transition-all duration-200 hover:-translate-y-1 hover:shadow-md cursor-pointer select-none">
    
    <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl {{ $iconBg }} flex items-center justify-center {{ $iconColor }} group-hover:scale-110 transition-transform duration-200 shadow-xs">
        <span class="material-symbols-outlined text-[24px] md:text-[28px]">{{ $category->icon ?? 'sell' }}</span>
    </div>

    <span class="font-headline-sm text-xs font-semibold text-on-surface group-hover:text-primary transition-colors text-center line-clamp-1">
        {{ $category->name }}
    </span>
</a>
