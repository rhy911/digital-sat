@props([
    'title' => '',
    'countText' => '',
    'searchPlaceholder' => '',
    'items' => [],
])

{{-- Expects an ancestor with x-data containing: searchQuery (string), statusFilter ('active'|'archived') --}}
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
        <input type="text" placeholder="{{ $searchPlaceholder }}" x-model="searchQuery">
    </div>
    <div class="filter-row">
        <button class="filter-chip" :class="{ 'active': statusFilter === 'active' }"
            @click="statusFilter = 'active'">Active</button>
        <button class="filter-chip" :class="{ 'active': statusFilter === 'archived' }"
            @click="statusFilter = 'archived'">Archived</button>
    </div>

    {{ $primaryAction ?? '' }}

    <div class="class-list">
        @foreach ($items as $item)
            <a href="{{ $item['route'] }}" class="class-item {{ !empty($item['selected']) ? 'selected' : '' }}"
                x-show="(statusFilter === 'all' || '{{ $item['status'] }}' === statusFilter) && ('{{ strtolower(addslashes($item['name'])) }}'.includes(searchQuery.toLowerCase()))"
                style="text-decoration: none; color: inherit; display: block;">
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
