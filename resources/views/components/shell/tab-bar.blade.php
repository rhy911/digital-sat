@props(['tabs' => []])

{{-- Expects an ancestor with x-data containing: activeTab (string) --}}
<div class="class-tabs" role="tablist">
    @foreach ($tabs as $tab)
        <button class="seg-btn" role="tab" :aria-selected="activeTab === '{{ $tab['key'] }}' ? 'true' : 'false'"
            :class="{ 'active': activeTab === '{{ $tab['key'] }}' }"
            @click="activeTab = '{{ $tab['key'] }}'; (() => { const params = new URLSearchParams(location.search); params.set('tab', '{{ $tab['key'] }}'); history.replaceState(null, '', location.pathname + '?' + params.toString()); })()">
            {{ $tab['label'] }}
            @if (isset($tab['count']))
                <span class="count">{{ $tab['count'] }}</span>
            @endif
        </button>
    @endforeach
</div>
