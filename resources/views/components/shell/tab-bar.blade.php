@props(['tabs' => []])

{{-- Expects an ancestor with x-data containing: activeTab (string) --}}
<div class="class-tabs" role="tablist" aria-label="Class sections">
    @foreach ($tabs as $tab)
        <button id="tab-{{ $tab['key'] }}" class="seg-btn" role="tab"
            aria-controls="panel-{{ $tab['key'] }}"
            :aria-selected="activeTab === '{{ $tab['key'] }}' ? 'true' : 'false'"
            :tabindex="activeTab === '{{ $tab['key'] }}' ? '0' : '-1'"
            :class="{ 'active': activeTab === '{{ $tab['key'] }}' }"
            @click="activeTab = '{{ $tab['key'] }}'; (() => { const params = new URLSearchParams(location.search); params.set('tab', '{{ $tab['key'] }}'); history.replaceState(null, '', location.pathname + '?' + params.toString()); })()">
            @if (!empty($tab['icon']))
                <x-ui.icon :name="$tab['icon']" class="w-4 h-4 inline-block mr-1.5 opacity-75" />
            @endif
            {{ $tab['label'] }}
            @if (isset($tab['count']))
                <span class="count">{{ $tab['count'] }}</span>
            @endif
        </button>
    @endforeach
</div>
