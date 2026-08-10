@props([
    'variant' => 'primary',
    'size' => 'md',
    'loading' => false,
    'href' => null,
    'type' => 'button',
])

@php
    $isDisabled = (bool) $loading;

    $sizeClasses = match ($size) {
        'sm' => 'min-h-9 px-3 text-sm gap-1.5',
        default => 'min-h-11 px-4 text-sm gap-2',
    };

    $variantClasses = match ($variant) {
        'secondary' => 'border border-slate-300 bg-white text-slate-800 hover:border-brand hover:bg-[var(--color-brand-soft)]',
        'danger' => 'bg-danger text-white hover:bg-rose-700',
        'ghost-on-dark' => 'border border-white/55 bg-white/10 text-white backdrop-blur-sm hover:bg-white/20 hover:border-white',
        default => 'bg-brand text-white hover:bg-brand-hover',
    };

    $classes = $attributes->class([
        'inline-flex items-center justify-center rounded-lg font-bold transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ds-focus)] focus-visible:ring-offset-2',
        'active:translate-y-px',
        'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:cursor-not-allowed aria-disabled:opacity-50',
        $sizeClasses,
        $variantClasses,
    ]);
@endphp

@if ($href)
    <a href="{{ $isDisabled ? '#' : $href }}" @if ($isDisabled) aria-disabled="true" tabindex="-1" @endif {{ $classes }}>
        @if ($loading)
            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @elseif (isset($icon))
            <span class="shrink-0">{{ $icon }}</span>
        @endif
        <span @class(['sr-only' => $loading])>{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" @disabled($isDisabled) {{ $classes }}>
        @if ($loading)
            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @elseif (isset($icon))
            <span class="shrink-0">{{ $icon }}</span>
        @endif
        <span @class(['sr-only' => $loading])>{{ $slot }}</span>
    </button>
@endif
