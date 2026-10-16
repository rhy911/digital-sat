@props(['tabs' => []])

{{-- Expects an ancestor with x-data containing: activeTab (string) --}}
<div class="class-tabs" role="tablist" aria-label="Class sections"
    @keydown.right.prevent="focusNextTab($event)"
    @keydown.down.prevent="focusNextTab($event)"
    @keydown.left.prevent="focusPrevTab($event)"
    @keydown.up.prevent="focusPrevTab($event)"
    @keydown.home.prevent="focusFirstTab($event)"
    @keydown.end.prevent="focusLastTab($event)"
    x-data="{
        focusNextTab(e) {
            const tabs = Array.from(e.currentTarget.querySelectorAll('[role=tab]'));
            const idx = tabs.indexOf(e.target);
            if (idx === -1) return;
            const next = tabs[(idx + 1) % tabs.length];
            next?.focus();
            next?.click();
        },
        focusPrevTab(e) {
            const tabs = Array.from(e.currentTarget.querySelectorAll('[role=tab]'));
            const idx = tabs.indexOf(e.target);
            if (idx === -1) return;
            const prev = tabs[(idx - 1 + tabs.length) % tabs.length];
            prev?.focus();
            prev?.click();
        },
        focusFirstTab(e) {
            const first = e.currentTarget.querySelector('[role=tab]');
            first?.focus();
            first?.click();
        },
        focusLastTab(e) {
            const tabs = e.currentTarget.querySelectorAll('[role=tab]');
            const last = tabs[tabs.length - 1];
            last?.focus();
            last?.click();
        }
    }">
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
