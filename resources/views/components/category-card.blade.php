@props([
    'category', // array|object: name, icon, url, iconBg, iconColor
])

@php
    $category = (object) $category;
    $iconBg = $category->iconBg ?? 'bg-primary-fixed-dim/30';
    $iconColor = $category->iconColor ?? 'text-primary';
@endphp

<a
    href="{{ $category->url ?? '#' }}"
    class="group relative rounded-2xl bg-surface-container-low p-5 flex flex-col items-center justify-center gap-3 transition-all hover:bg-surface hover:shadow-lg">
    <div class="w-12 h-12 rounded-full {{ $iconBg }} flex items-center justify-center {{ $iconColor }} group-hover:scale-110 transition-transform">
        <span class="material-symbols-outlined text-[22px]">{{ $category->icon }}</span>
    </div>
    <span class="font-headline-sm text-headline-sm text-on-surface">{{ $category->name }}</span>
</a>
