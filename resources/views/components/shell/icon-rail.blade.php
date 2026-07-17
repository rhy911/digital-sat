@props([
    'items' => [],
    'logoHref' => '#',
    'logoLabel' => 'D',
    'avatarLabel' => '',
])

<div class="icon-rail">
    <a href="{{ $logoHref }}" class="rail-logo">{{ $logoLabel }}</a>
    <div class="rail-nav">
        @foreach ($items as $item)
            <a href="{{ $item['route'] }}" class="rail-item {{ !empty($item['active']) ? 'active' : '' }}"
                @if (!empty($item['target'])) target="{{ $item['target'] }}" @endif>
                <span class="rail-tip">{{ $item['label'] }}</span>
                {!! $item['icon'] !!}
            </a>
        @endforeach
    </div>
    <div class="rail-avatar">{{ $avatarLabel }}</div>
</div>
