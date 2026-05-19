@props(['icon', 'title', 'description'])

<div class="info">
    <div class="icon">
        {{ $icon }}
    </div>
    <div>
        <h2>{{ $title }}</h2>
        <p>{{ $description }}</p>
    </div>
</div>
