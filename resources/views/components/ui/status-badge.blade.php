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

    $defaultIconName = match ($status) {
        'success' => 'check-circle',
        'danger' => 'exclamation-circle-fill',
        'warning' => 'exclamation-triangle-fill',
        'brand' => 'info-circle',
        default => null,
    };
@endphp

<span {{ $attributes->class([
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide',
        $variantClasses,
    ]) }}>
    @if(isset($icon))
        <span class="shrink-0" aria-hidden="true">{{ $icon }}</span>
    @elseif($defaultIconName)
        <x-ui.icon :name="$defaultIconName" class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
    @endif
    {{ $slot }}
</span>

