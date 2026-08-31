@props([
    'title' => '',
    'countText' => '',
    'searchPlaceholder' => '',
    'items' => [],
    'filters' => [
        ['value' => 'all', 'label' => 'All'],
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'archived', 'label' => 'Archived'],
    ],
])

{{-- Expects an ancestor with x-data containing: searchQuery (string), statusFilter (string) --}}
<div class="class-col">
    <div class="class-col-head">
        <h1>{{ $title }}</h1>
        <div class="count">{{ $countText }}</div>
    </div>
    <div class="search-row">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="m21 21-4.3-4.3" stroke-linecap="round" />
        </svg>
        <input type="text" placeholder="{{ $searchPlaceholder }}" aria-label="{{ $searchPlaceholder }}"
            autocomplete="off" x-model="searchQuery">
    </div>
    <div class="filter-row">
        @foreach ($filters as $filter)
            <button type="button" class="filter-chip" :class="{ 'active': statusFilter === '{{ $filter['value'] }}' }"
                @click="statusFilter = '{{ $filter['value'] }}'">{{ $filter['label'] }}</button>
        @endforeach
    </div>

    {{ $primaryAction ?? '' }}

    <div class="class-list">
        @foreach ($items as $item)
            @php
                $searchText = mb_strtolower(($item['name'] ?? '') . ' ' . implode(' ', $item['meta'] ?? []));
                $status = (string) ($item['status'] ?? 'all');
            @endphp
            <a href="{{ $item['route'] }}"
                data-search="{{ $searchText }}"
                data-status="{{ $status }}"
                class="class-item {{ !empty($item['selected']) ? 'selected' : '' }} text-inherit no-underline"
                x-show="(statusFilter === 'all' || $el.dataset.status === statusFilter) && (!searchQuery.trim() || $el.dataset.search.includes(searchQuery.toLowerCase().trim()))">
                <div class="ci-top">
                    <span class="ci-name">{{ $item['name'] }}</span>
                </div>
                <div class="ci-meta">
                    @foreach ($item['meta'] as $index => $metaLine)
                        @if ($index > 0)
                            <span>·</span>
                        @endif
                        <span>{{ $metaLine }}</span>
                    @endforeach
                </div>
            </a>
        @endforeach
    </div>
</div>
