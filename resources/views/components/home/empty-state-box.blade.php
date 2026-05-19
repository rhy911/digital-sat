@props([
    'title',
    'class' => '',
])

<div {{ $attributes->merge(['class' => trim("test-box {$class}")]) }}>
    <h4>{{ $title }}</h4>
    {{ $slot }}
</div>

