@props([
    'name',
    'id' => null,
    'value' => null,
    'placeholder' => 'Select an option...',
    'options' => [],
    'searchable' => true,
    'required' => false,
    'disabled' => false,
    'size' => 'sm',
])

@php
    $id = $id ?? $name;
    $initialValue = (string) old($name, $value ?? '');

    // Normalize options into a standard array of ['value', 'label', 'group', 'subtext']
    $normalizedOptions = collect($options)->map(function ($item, $key) {
        if (is_array($item) || is_object($item)) {
            $item = (array) $item;
            return [
                'value' => (string) ($item['value'] ?? $item['id'] ?? $key),
                'label' => (string) ($item['label'] ?? $item['title'] ?? $item['name'] ?? $key),
                'group' => $item['group'] ?? null,
                'subtext' => $item['subtext'] ?? null,
            ];
        }
        return [
            'value' => (string) $key,
            'label' => (string) $item,
            'group' => null,
            'subtext' => null,
        ];
    })->values()->all();

    // If options contain groups, keep groups contiguous
    if (collect($normalizedOptions)->contains(fn ($o) => !empty($o['group']))) {
        $normalizedOptions = collect($normalizedOptions)->groupBy('group')->flatten(1)->values()->all();
    }
@endphp

@if (! $searchable)
    <div {{ $attributes->merge(['class' => 'relative w-full']) }}>
        <select name="{{ $name }}" id="{{ $id }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            class="w-full text-xs font-medium bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 transition-colors text-slate-800 shadow-2xs focus:border-brand focus:ring-1 focus:ring-brand @if($disabled) opacity-60 bg-slate-50 cursor-not-allowed @endif">
            @if ($placeholder)
                <option value="" @if($initialValue === '') selected @endif>{{ $placeholder }}</option>
            @endif
            @foreach (collect($normalizedOptions)->groupBy('group') as $groupName => $groupOptions)
                @if ($groupName)
                    <optgroup label="{{ $groupName }}">
                @endif
                @foreach ($groupOptions as $opt)
                    <option value="{{ $opt['value'] }}" @if((string) $opt['value'] === $initialValue) selected @endif>
                        {{ $opt['label'] }}
                    </option>
                @endforeach
                @if ($groupName)
                    </optgroup>
                @endif
            @endforeach
        </select>
    </div>
@else
<div {{ $attributes->merge(['class' => 'relative w-full']) }}
    :class="open ? 'z-50' : ''"
    x-data="{
        open: false,
        search: '',
        validationError: false,
        value: '{{ $initialValue }}',
        options: {{ Js::from($normalizedOptions) }},
        filtered: [],
        selectedLabel: '',
        selectedSubtext: '',
        focusedIndex: -1,

        init() {
            this.filter();
            this.syncSelected();
            this.$watch('value', () => this.syncSelected());
        },

        syncSelected() {
            const found = this.options.find(o => o.value === this.value);
            this.selectedLabel = found ? found.label : '';
            this.selectedSubtext = found ? (found.subtext || '') : '';
        },

        filter() {
            const q = this.search.trim().toLowerCase();
            const list = !q ? this.options : this.options.filter(o =>
                o.label.toLowerCase().includes(q) ||
                (o.subtext && o.subtext.toLowerCase().includes(q)) ||
                (o.group && o.group.toLowerCase().includes(q))
            );
            let prevGroup = null;
            this.filtered = list.map(o => {
                const isHeader = !!(o.group && o.group !== prevGroup);
                prevGroup = o.group;
                return { ...o, isHeader };
            });
            this.focusedIndex = this.filtered.length > 0 ? 0 : -1;
        },

        toggle() {
            @if ($disabled) return; @endif
            this.open = !this.open;
            if (this.open) {
                if (this.search) {
                    this.search = '';
                    this.filter();
                }
                this.focusedIndex = this.filtered.findIndex(o => o.value === this.value);
                setTimeout(() => this.$refs.searchInput?.focus(), 50);
                if (this.focusedIndex > 0) {
                    setTimeout(() => {
                        const el = this.$refs.optionsList?.querySelector(`[data-option-index='${this.focusedIndex}']`);
                        if (el) el.scrollIntoView({ block: 'nearest' });
                    }, 60);
                }
            }
        },

        close(restoreFocus = false) {
            this.open = false;
            this.focusedIndex = -1;
            if (restoreFocus) this.$nextTick(() => this.$refs.trigger?.focus());
        },

        select(option) {
            this.value = option ? option.value : '';
            this.validationError = false;
            this.syncSelected();
            this.$dispatch('input', this.value);
            this.$dispatch('change', this.value);
            this.close(true);
        },

        navigate(direction) {
            const count = this.filtered.length;
            if (count === 0) return;
            if (direction === 'down') {
                this.focusedIndex = (this.focusedIndex + 1) % count;
            } else if (direction === 'up') {
                this.focusedIndex = (this.focusedIndex - 1 + count) % count;
            }
            this.$nextTick(() => {
                const el = this.$refs.optionsList?.querySelector(`[data-option-index='${this.focusedIndex}']`);
                if (el) el.scrollIntoView({ block: 'nearest' });
            });
        },

        selectFocused() {
            if (this.focusedIndex >= 0 && this.focusedIndex < this.filtered.length) {
                this.select(this.filtered[this.focusedIndex]);
            }
        }
    }"
    @click.outside="close()"
    @keydown.escape="if (open) { $event.stopPropagation(); close(true); }">

    <select name="{{ $name }}" id="{{ $id }}-value" class="sr-only" tabindex="-1" aria-hidden="true"
        @if($required) required @endif
        @invalid.prevent="validationError = true; $refs.trigger?.setAttribute('aria-invalid', 'true'); $refs.trigger?.focus()"
        :value="value">
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($normalizedOptions as $opt)
            <option value="{{ $opt['value'] }}" :selected="value === '{{ $opt['value'] }}'">{{ $opt['label'] }}</option>
        @endforeach
    </select>

    <!-- Trigger button -->
    <button type="button"
        id="{{ $id }}"
        x-ref="trigger"
        @click="toggle()"
        @keydown.arrow-down.prevent="if (!open) { toggle(); } else { navigate('down'); }"
        @keydown.arrow-up.prevent="if (open) navigate('up')"
        @keydown.enter.prevent="if (open) selectFocused()"
        aria-haspopup="listbox"
        aria-controls="{{ $id }}-options"
        :aria-expanded="open"
        aria-describedby="{{ $id }}-validation-error"
        :aria-invalid="validationError"
        class="w-full text-xs font-medium bg-white border rounded-lg px-2.5 py-1.5 flex items-center justify-between transition-colors cursor-pointer text-left shadow-2xs @if($disabled) opacity-60 bg-slate-50 cursor-not-allowed @endif"
        :class="validationError ? 'border-red-600 ring-1 ring-red-600' : (open ? 'border-brand ring-1 ring-brand' : 'border-slate-300 hover:border-slate-400')">

        <div class="flex items-center gap-2 truncate pr-2">
            <span class="truncate" :class="selectedLabel ? 'text-slate-900 font-medium' : 'text-slate-400'"
                x-text="selectedLabel || '{{ $placeholder }}'">
            </span>
            <span x-show="selectedSubtext" class="text-3xs text-slate-400 font-normal shrink-0" x-text="selectedSubtext"></span>
        </div>

        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 shrink-0"
            :class="open ? 'rotate-180 text-brand' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <p id="{{ $id }}-validation-error" class="mt-1 text-xs text-red-700" role="alert" x-show="validationError">Please select an option.</p>

    <!-- Floating popover -->
    <div x-show="open" x-cloak
        x-transition:enter="transition-opacity ease-out duration-100"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-75"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden text-xs">

        <div class="p-2 border-b border-slate-100 bg-slate-50/80">
            <div class="relative flex items-center">
                <x-ui.icon name="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input type="text" x-ref="searchInput" x-model="search" @input="filter()"
                    @keydown.arrow-down.prevent="navigate('down')"
                    @keydown.arrow-up.prevent="navigate('up')"
                    @keydown.enter.prevent="selectFocused()"
                    role="combobox"
                    aria-haspopup="listbox"
                    aria-label="Search options"
                    aria-autocomplete="list"
                    aria-controls="{{ $id }}-options"
                    :aria-expanded="open"
                    :aria-activedescendant="focusedIndex >= 0 ? '{{ $id }}-option-' + focusedIndex : null"
                    placeholder="Search..."
                    class="w-full pl-8 pr-7 py-1.5 text-xs bg-white border border-slate-200 rounded-md text-slate-800 placeholder:text-slate-400 focus:border-brand focus:ring-1 focus:ring-brand">
                <button type="button" x-show="search" @click="search = ''; filter(); $refs.searchInput?.focus()"
                    aria-label="Clear search"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-0.5">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        <div id="{{ $id }}-options" x-ref="optionsList" role="listbox" class="max-h-48 overflow-y-auto divide-y divide-slate-100 py-0.5">
            <template x-for="(opt, idx) in filtered" :key="opt.value">
                <div>
                    <!-- Group Header -->
                    <div x-show="opt.isHeader"
                        class="px-2.5 py-1 text-3xs font-bold uppercase tracking-wider text-slate-400 bg-slate-50/90 sticky top-0"
                        x-text="opt.group">
                    </div>
                    <div
                        @click="select(opt)"
                        :id="'{{ $id }}-option-' + idx"
                        :data-option-index="idx"
                        tabindex="-1"
                        class="w-full px-2.5 py-1.5 flex items-center justify-between text-left hover:bg-blue-50/70 cursor-pointer"
                        :class="value === opt.value ? 'bg-blue-50/90 text-brand font-semibold' : (idx === focusedIndex ? 'bg-slate-100 text-slate-900' : 'text-slate-700')"
                        role="option"
                        :aria-selected="value === opt.value">
                        <div class="flex items-center gap-1.5 truncate pr-2">
                            <span class="truncate" x-text="opt.label"></span>
                            <span x-show="opt.subtext" class="text-3xs text-slate-400 font-normal shrink-0" x-text="opt.subtext"></span>
                        </div>
                        <svg x-show="value === opt.value" class="w-3.5 h-3.5 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
            </template>

            <!-- Empty Results -->
            <div x-show="filtered.length === 0" class="py-3 text-center text-xs text-slate-400">
                No matching options found.
            </div>
        </div>
    </div>
</div>
@endif
