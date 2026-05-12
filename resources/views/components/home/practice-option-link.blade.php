@props([
    'href',
    'image',
    'alt',
    'title',
])

<a href="{{ $href }}">
    <div class="option">
        <img src="{{ $image }}" alt="{{ $alt }}">
        <h4>{{ $title }}</h4>
    </div>
</a>

