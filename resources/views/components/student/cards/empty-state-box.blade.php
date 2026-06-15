@props([
    'title',
    'class' => '',
])

<div {{ $attributes->merge(['class' => trim("test-box {$class}")]) }}>
    <h3 class="text-xl md:text-2xl text-center font-bold mb-3">{{ $title }}</h3>
    {{ $slot }}
</div>

