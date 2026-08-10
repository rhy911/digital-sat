@props([
    'padded' => true,
    'shadow' => 'sm',
    'stretch' => false,
])

@php
    $shadowClasses = match ($shadow) {
        'sm' => 'shadow-sm',
        default => '',
    };
    $sectionPadding = $padded ? 'px-6 py-4' : '';
    $bodyPadding = $padded ? 'p-6' : '';
    // In a side-by-side row the taller card sets the height. Without this the
    // shorter card just grows a gap under its footer; with it the body absorbs
    // the slack and the footer stays pinned to the bottom edge.
    $stretchClasses = $stretch ? 'flex h-full flex-col' : '';
@endphp

<div {{ $attributes->class([
        'rounded-xl border border-slate-200 bg-white overflow-hidden',
        $shadowClasses,
        $stretchClasses,
    ]) }}>
    @isset($header)
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 {{ $sectionPadding }}">
            {{ $header }}
        </div>
    @endisset

    <div @class([$bodyPadding, 'grow' => $stretch])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex items-center justify-end gap-3 border-t border-slate-200 {{ $sectionPadding }}">
            {{ $footer }}
        </div>
    @endisset
</div>
