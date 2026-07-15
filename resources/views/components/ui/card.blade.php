@props([
    'padded' => true,
    'shadow' => 'sm',
])

@php
    $shadowClasses = match ($shadow) {
        'sm' => 'shadow-sm',
        default => '',
    };
    $sectionPadding = $padded ? 'px-6 py-4' : '';
    $bodyPadding = $padded ? 'p-6' : '';
@endphp

<div {{ $attributes->class([
        'rounded-xl border border-slate-200 bg-white overflow-hidden',
        $shadowClasses,
    ]) }}>
    @isset($header)
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 {{ $sectionPadding }}">
            {{ $header }}
        </div>
    @endisset

    <div class="{{ $bodyPadding }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex items-center justify-end gap-3 border-t border-slate-200 {{ $sectionPadding }}">
            {{ $footer }}
        </div>
    @endisset
</div>
