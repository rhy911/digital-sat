@props([
    'status' => 'neutral',
])

@php
    $variantClasses = match ($status) {
        'success' => 'bg-emerald-100 text-emerald-800',
        'danger' => 'bg-rose-100 text-rose-800',
        'warning' => 'bg-amber-100 text-amber-800',
        'brand' => 'bg-[var(--color-brand-soft)] text-brand',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

<span {{ $attributes->class([
        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide',
        $variantClasses,
    ]) }}>
    @isset($icon)
        <span class="shrink-0" aria-hidden="true">{{ $icon }}</span>
    @endisset
    {{ $slot }}
</span>
