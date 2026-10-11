@props(['id', 'maxWidth' => '2xl', 'title' => '', 'dynamicMaxWidth' => null, 'overflow' => 'auto'])

@php
    $maxWidthClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
        '5xl' => 'sm:max-w-5xl',
        '6xl' => 'sm:max-w-6xl',
        '7xl' => 'sm:max-w-7xl',
        '80%' => 'sm:max-w-[80vw]',
        'adaptive' => 'transition-[max-width] duration-300 ease-in-out sm:max-w-[764px]',
    ][$maxWidth] ?? 'sm:max-w-2xl';
@endphp

<div x-data="{ show: false, lastActiveEl: null }"
    x-on:open-modal.window="if ($event.detail === '{{ $id }}') { lastActiveEl = document.activeElement; show = true; setTimeout(() => { const dialog = document.getElementById('{{ $id }}'); if (dialog) { const sc = dialog.querySelector('.overflow-y-auto'); if (sc) sc.scrollTop = 0; const el = dialog.querySelector('[autofocus], button[role=combobox]:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])'); if (el) el.focus({ preventScroll: true }); } }, 60); }"
    x-on:close-modal.window="if ($event.detail === '{{ $id }}') { show = false; setTimeout(() => { if (lastActiveEl && typeof lastActiveEl.focus === 'function') { lastActiveEl.focus({ preventScroll: true }); } }, 160); }"
    x-on:keydown.escape.window="if (show) { show = false; setTimeout(() => { if (lastActiveEl && typeof lastActiveEl.focus === 'function') { lastActiveEl.focus({ preventScroll: true }); } }, 160); }">
    <template x-teleport="body">
        <div id="{{ $id }}" data-modal-dialog="{{ $id }}" x-show="show"
            class="fixed inset-0 z-50 overflow-y-auto overflow-x-hidden" aria-labelledby="modal-title-{{ $id }}" role="dialog"
            aria-modal="true" :aria-hidden="show ? 'false' : 'true'" x-cloak>
            <!-- Background overlay -->
            <div x-show="show" x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-950/60"
                data-modal-close
                x-on:click="$dispatch('close-modal', '{{ $id }}')" aria-hidden="true"></div>

            <!-- Modal panel -->
            <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4 text-center">
                <div x-show="show" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 sm:scale-100"
                    x-transition:leave="transition-opacity ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="relative flex flex-col w-full transform rounded-t-2xl sm:rounded-2xl bg-white text-left shadow-2xl {{ $maxWidthClass }} border border-slate-200/90 max-h-[92vh] sm:max-h-[88vh] {{ $overflow === 'visible' ? 'overflow-visible' : 'overflow-hidden' }}"
                    @if ($dynamicMaxWidth)
                        :class="{{ $dynamicMaxWidth }}"
                    @elseif ($maxWidth === 'adaptive')
                        :class="typeof audience !== 'undefined' && audience === 'specific' ? 'sm:max-w-[1128px]' : 'sm:max-w-[764px]'"
                    @endif>
                    <div class="sm:hidden w-12 h-1 bg-slate-300 rounded-full mx-auto my-2.5 shrink-0" aria-hidden="true"></div>
                    @if ($title)
                        <div class="bg-slate-50/95 px-5 py-3.5 border-b border-slate-200/80 flex justify-between items-center shrink-0 rounded-t-2xl">
                            <h3 class="text-base sm:text-lg font-semibold text-slate-900 tracking-tight" id="modal-title-{{ $id }}">
                                {{ $title }}
                            </h3>
                            <button type="button" data-modal-close
                                x-on:click="$dispatch('close-modal', '{{ $id }}')"
                                class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 rounded-lg p-1.5 transition-colors cursor-pointer"
                                aria-label="Close modal">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @endif

                    <div class="px-5 py-5 sm:p-6 text-slate-700 flex-1 {{ $overflow === 'visible' ? 'overflow-visible' : 'overflow-y-auto overflow-x-hidden overscroll-contain' }} {{ !isset($footer) ? 'rounded-b-2xl' : '' }}">
                        {{ $slot }}
                    </div>

                    @if (isset($footer))
                        <div
                            class="bg-slate-50/95 px-5 py-3.5 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-200/80 shrink-0 gap-2.5 rounded-b-2xl">
                            {{ $footer }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </template>
</div>
