@props([
    'id',
    'maxWidth' => '2xl',
    'title' => '',
])

@php
$maxWidthClass = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
@endphp

<div
    x-data="{ show: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $id }}') { show = true; document.body.classList.add('overflow-hidden'); }"
    x-on:close-modal.window="if ($event.detail === '{{ $id }}') { show = false; document.body.classList.remove('overflow-hidden'); }"
    x-on:keydown.escape.window="show = false; document.body.classList.remove('overflow-hidden');"
    x-show="show"
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title-{{ $id }}"
    role="dialog"
    aria-modal="true"
    style="display: none;"
>
    <!-- Background overlay -->
    <div
        x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity"
        x-on:click="show = false; document.body.classList.remove('overflow-hidden');"
        aria-hidden="true"
    ></div>

    <!-- Modal panel -->
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-xl bg-slate-800 text-left shadow-xl transition-all sm:my-8 sm:w-full {{ $maxWidthClass }} border border-slate-700"
        >
            @if($title)
            <div class="bg-slate-800 px-4 py-3 border-b border-slate-700 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-slate-100" id="modal-title-{{ $id }}">
                    {{ $title }}
                </h3>
                <button type="button" x-on:click="show = false; document.body.classList.remove('overflow-hidden');" class="text-slate-400 hover:text-white focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            @endif

            <div class="px-4 py-5 sm:p-6 text-slate-300">
                {{ $slot }}
            </div>

            @if(isset($footer))
            <div class="bg-slate-800/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-700">
                {{ $footer }}
            </div>
            @endif
        </div>
    </div>
</div>
