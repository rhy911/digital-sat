@props([
    'href' => null,
    'size' => 'md',
    'tone' => 'brand',
    'label' => 'DFET SAT',
    'showIcon' => true,
])

@php
    $classes = trim("brand-wordmark brand-wordmark--{$size} brand-wordmark--{$tone} {$attributes->get('class')}");
    $icon = $showIcon
        ? '<img src="/brand/icon-primary.svg" alt="" aria-hidden="true" class="brand-wordmark__icon">'
        : '';
    $content = $icon . <<<'HTML'
    <span class="brand-wordmark__text">DFET SAT</span>
    HTML;
@endphp

@if ($href)
    <a href="{{ $href }}"
        {{ $attributes->except('class')->merge(['class' => $classes, 'aria-label' => $label]) }}>
        {!! $content !!}
    </a>
@else
    <span {{ $attributes->except('class')->merge(['class' => $classes, 'aria-label' => $label]) }}>
        {!! $content !!}
    </span>
@endif
