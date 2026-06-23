@props(['compact' => false])

<span {{ $attributes->class([
    'inline-flex items-center font-bold text-slate-600',
    'text-xs' => $compact,
    'text-sm' => ! $compact,
]) }}>Estimated practice score</span>
