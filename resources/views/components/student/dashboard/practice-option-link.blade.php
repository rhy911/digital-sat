@props([
    'href',
    'image',
    'alt',
    'title',
])

<a href="{{ $href }}">
    <div class="option">
        <img src="{{ $image }}" alt="{{ $alt }}">
        <h3 class="text-lg md:text-xl font-bold mt-3">{{ $title }}</h3>
    </div>
</a>

