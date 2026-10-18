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
    'classrooms' => null,
    'itemLabel' => 'assignments',
])

<div class="sidebar-col class-col"
    x-data="{
        allItems: @js(collect($items)->map(fn($item) => [
            'name' => $item['name'] ?? '',
            'subtitle' => $item['subtitle'] ?? '',
            'class_name' => $item['class_name'] ?? $item['badge'] ?? '',
            'meta' => implode(' ', $item['meta'] ?? []),
            'status' => (string) ($item['status'] ?? 'all'),
            'class_id' => (string) ($item['class_id'] ?? ''),
        ])->all()),
        get matchCount() {
            const q = (typeof searchQuery === 'string' ? searchQuery : '').toLowerCase().trim();
            const sf = typeof statusFilter === 'string' ? statusFilter : 'all';
            return this.allItems.filter(it => {
                const search = (it.name + ' ' + it.subtitle + ' ' + it.class_name + ' ' + it.meta).toLowerCase();
                const matchesSearch = !q || search.includes(q);
                const matchesStatus = sf === 'all' || it.status === sf;
                const matchesClass = typeof classFilter === 'undefined' || classFilter === 'all' || String(it.class_id) === String(classFilter);
                return matchesSearch && matchesStatus && matchesClass;
            }).length;
        }
    }">
    <div class="sidebar-col-head class-col-head flex items-start justify-between gap-2">
        <div>
            <h1>{{ $title }}</h1>
            <div class="count">{{ $countText }}</div>
        </div>
        <button type="button" class="sm:hidden p-1.5 -mr-1 -mt-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition"
            data-mobile-nav-close aria-label="Close menu">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
    </div>

    @if (!empty($classrooms) && count($classrooms) > 0)
        <div class="sidebar-select-row">
            <label for="sidebar-class-filter" class="sr-only">Filter by class</label>
            <div class="relative">
                <select id="sidebar-class-filter"
                    x-model="classFilter"
                    class="w-full text-xs font-semibold rounded-lg border border-slate-200 bg-white py-1.5 pl-2.5 pr-7 text-slate-700 shadow-2xs focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand cursor-pointer truncate">
                    <option value="all">All Classes</option>
                    @foreach ($classrooms as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </div>
        </div>
    @endif

    <div class="search-row">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="m21 21-4.3-4.3" stroke-linecap="round" />
        </svg>
        <input type="text" placeholder="{{ $searchPlaceholder }}" aria-label="{{ $searchPlaceholder }}"
            autocomplete="off" x-model="searchQuery">
    </div>
    <div class="filter-row" role="tablist" aria-label="Filter">
        @foreach ($filters as $filter)
            <button type="button" role="tab" class="filter-chip" :class="{ 'active': statusFilter === '{{ $filter['value'] }}' }"
                :aria-selected="statusFilter === '{{ $filter['value'] }}'"
                @click="statusFilter = '{{ $filter['value'] }}'">
                <span>{{ $filter['label'] }}</span>
                @if (isset($filter['count']))
                    <span class="count-pill {{ ($filter['value'] === 'overdue' && $filter['count'] > 0) ? 'is-danger' : '' }}">{{ $filter['count'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{ $primaryAction ?? '' }}

    <div class="sidebar-list class-list">
        @foreach ($items as $item)
            @php
                $searchText = mb_strtolower(($item['name'] ?? '') . ' ' . ($item['subtitle'] ?? '') . ' ' . ($item['class_name'] ?? $item['badge'] ?? '') . ' ' . implode(' ', $item['meta'] ?? []));
                $status = (string) ($item['status'] ?? 'all');
                $classId = (string) ($item['class_id'] ?? '');
                $statusLabel = $item['status_label'] ?? ucfirst($status);
                $pillClass = match($item['status_variant'] ?? $status) {
                    'success', 'completed', 'published', 'active' => 'ok',
                    'danger', 'overdue' => 'danger',
                    'brand', 'in_progress', 'in-progress' => 'in-progress',
                    'neutral', 'closed', 'archived', 'draft' => 'draft',
                    default => 'pending',
                };
            @endphp
            <a href="{{ $item['route'] }}"
                :href="typeof assignmentUrl === 'function' ? assignmentUrl('{{ $item['base_route'] ?? $item['route'] }}') : '{{ $item['route'] }}'"
                data-search="{{ $searchText }}"
                data-status="{{ $status }}"
                data-class-id="{{ $classId }}"
                class="sidebar-item class-item {{ !empty($item['selected']) ? 'selected' : '' }} text-inherit no-underline"
                x-show="(typeof classFilter === 'undefined' || classFilter === 'all' || $el.dataset.classId === classFilter) && (statusFilter === 'all' || $el.dataset.status === statusFilter) && (!searchQuery.trim() || $el.dataset.search.includes(searchQuery.toLowerCase().trim()))">
                <div class="si-top ci-top">
                    <span class="si-name ci-name truncate font-bold text-xs" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                    <span class="status-pill {{ $pillClass }} shrink-0 text-3xs font-semibold">
                        <span class="d"></span>{{ $statusLabel }}
                    </span>
                </div>
                @if (!empty($item['subtitle']))
                    <div class="si-sub line-clamp-2 leading-snug break-words text-xs text-slate-700 font-medium" title="{{ $item['subtitle'] }}">
                        {{ $item['subtitle'] }}
                    </div>
                @endif
                <div class="si-meta ci-meta">
                    @if (!empty($item['class_name']))
                        <span class="si-badge" x-show="typeof classFilter === 'undefined' || classFilter === 'all'" title="{{ $item['class_name'] }}">
                            {{ $item['class_name'] }}
                        </span>
                    @elseif (!empty($item['badge']))
                        <span class="si-badge" title="{{ $item['badge'] }}">{{ $item['badge'] }}</span>
                    @endif
                    @foreach ($item['meta'] ?? [] as $index => $metaLine)
                        <span>{{ $metaLine }}</span>
                    @endforeach
                </div>
            </a>
        @endforeach

        @if (empty($items) || count($items) === 0)
            <div class="p-6 text-center text-slate-500 text-xs">
                <p class="font-medium text-slate-700 m-0">No {{ $itemLabel }} yet.</p>
            </div>
        @else
            <div x-cloak x-show="allItems.length > 0 && matchCount === 0" class="p-6 text-center text-slate-500 text-xs">
                <p class="font-medium text-slate-700 m-0" x-text="searchQuery.trim() ? ('No {{ $itemLabel }} match &ldquo;' + searchQuery + '&rdquo;') : ('No {{ $itemLabel }} match the selected filter.')"></p>
                <button type="button" @click="searchQuery = ''; statusFilter = 'all'; if (typeof classFilter !== 'undefined') classFilter = 'all';"
                    class="mt-2 text-xs font-semibold text-brand hover:underline cursor-pointer">
                    Reset filters
                </button>
            </div>
            <div class="sr-only" role="status" aria-live="polite"
                x-text="allItems.length > 0 ? (matchCount + ' {{ $itemLabel }} found') : ''"></div>
        @endif
    </div>
</div>
