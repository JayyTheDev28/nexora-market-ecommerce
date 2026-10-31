@props([
    'variant' => 'neutral', // 'primary' | 'success' | 'warning' | 'error' | 'neutral' | 'info'
    'dot' => false,
    'size' => 'sm', // 'xs' | 'sm' | 'md'
])

@php
    $variantClasses = match($variant) {
        'primary' => 'bg-primary/10 text-primary border-primary/20',
        'success' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
        'warning' => 'bg-amber-500/10 text-amber-800 dark:text-amber-400 border-amber-500/20',
        'error' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20',
        'info' => 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-500/20',
        default => 'bg-surface-container text-on-surface-variant border-outline-variant/60',
    };

    $dotClasses = match($variant) {
        'primary' => 'bg-primary',
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'error' => 'bg-rose-500',
        'info' => 'bg-sky-500',
        default => 'bg-outline',
    };

    $sizeClasses = match($size) {
        'xs' => 'px-2 py-0.5 text-[10px]',
        'md' => 'px-3 py-1 text-xs font-semibold',
        default => 'px-2.5 py-0.5 text-[11px] font-semibold',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border tracking-wide font-medium {$sizeClasses} {$variantClasses}"]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses }} flex-shrink-0"></span>
    @endif
    {{ $slot }}
</span>

