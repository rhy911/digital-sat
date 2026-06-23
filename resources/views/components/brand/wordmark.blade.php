@props([
    'href' => null,
    'size' => 'md',
    'tone' => 'brand',
    'label' => 'DigiSAT',
])

@php
    $classes = trim("brand-wordmark brand-wordmark--{$size} brand-wordmark--{$tone} {$attributes->get('class')}");
    $content = <<<'HTML'
    <span class="brand-wordmark__text"><span class="brand-wordmark__prefix">Digi</span><span class="brand-wordmark__suffix">SAT</span></span>
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
