@props(['stats' => []])

<div class="stat-row">
    @foreach ($stats as $stat)
        <div class="s">
            <div class="num">{{ $stat['value'] }}</div>
            <div class="lbl">{{ $stat['label'] }}</div>
        </div>
    @endforeach
</div>
