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
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '5xl' => 'sm:max-w-5xl',
    '6xl' => 'sm:max-w-6xl',
    '7xl' => 'sm:max-w-7xl',
    '80%' => 'sm:max-w-[80vw]',
][$maxWidth];
@endphp

<div
    id="{{ $id }}"
    data-modal-dialog="{{ $id }}"
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
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-950/65 transform-gpu"
        style="will-change: opacity;"
        data-modal-close
        x-on:click="show = false; document.body.classList.remove('overflow-hidden');"
        aria-hidden="true"
    ></div>

    <!-- Modal panel -->
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div
            x-show="show"
            x-transition:enter="transition-all ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition-all ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl sm:my-8 sm:w-full {{ $maxWidthClass }} border border-slate-200 transform-gpu"
            style="will-change: transform, opacity;"
        >
            @if($title)
            <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-slate-900" id="modal-title-{{ $id }}">
                    {{ $title }}
                </h3>
                <button type="button" data-modal-close x-on:click="show = false; document.body.classList.remove('overflow-hidden');" class="text-slate-500 hover:text-slate-900 focus:outline-none cursor-pointer" aria-label="Close modal">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            @endif

            <div class="px-4 py-5 sm:p-6 text-slate-700">
                {{ $slot }}
            </div>

            @if(isset($footer))
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-200">
                {{ $footer }}
            </div>
            @endif
        </div>
    </div>
</div>
