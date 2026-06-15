@props(['href' => null])

@php
    $content = $slot->isEmpty() ? 'Back' : $slot;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'back-link']) }}>
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        {{ $content }}
    </a>
@else
    <button type="button" data-auth-back-fallback="{{ route('signin') }}"
        {{ $attributes->merge(['class' => 'back-link']) }}>
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        {{ $content }}
    </button>
@endif
