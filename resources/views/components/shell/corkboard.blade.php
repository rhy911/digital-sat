@props(['header' => ''])

<div class="corkboard-pane">
    <div class="cork-texture">
        <div class="cork-header">{{ $header }}</div>
        {{ $slot }}
    </div>
</div>
