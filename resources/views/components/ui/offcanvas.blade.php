@props([
    'id',
    'title' => '',
    'width' => 'w-96', // Tailwind width class
])

<div
    x-data="{ show: false }"
    x-on:open-offcanvas.window="if ($event.detail === '{{ $id }}') { show = true; document.body.classList.add('overflow-hidden'); }"
    x-on:close-offcanvas.window="if ($event.detail === '{{ $id }}') { show = false; document.body.classList.remove('overflow-hidden'); }"
    x-on:keydown.escape.window="show = false; document.body.classList.remove('overflow-hidden');"
>
    <template x-teleport="body">
        <div
            x-show="show"
            class="fixed inset-0 z-50 overflow-hidden"
            aria-labelledby="offcanvas-title-{{ $id }}"
            role="dialog"
            aria-modal="true"
            style="display: none;"
        >
            <!-- Background backdrop, show/hide based on slide-over state. -->
            <div
                x-show="show"
                x-transition:enter="transition-opacity ease-in-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in-out duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-950/80 transform-gpu"
                style="will-change: opacity;"
                x-on:click="show = false; document.body.classList.remove('overflow-hidden');"
            ></div>

            <div class="fixed inset-0 overflow-hidden pointer-events-none">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <!-- Slide-over panel, show/hide based on slide-over state. -->
                        <div
                            x-show="show"
                            x-transition:enter="transition-transform ease-in-out duration-300"
                            x-transition:enter-start="translate-x-full"
                            x-transition:enter-end="translate-x-0"
                            x-transition:leave="transition-transform ease-in-out duration-300"
                            x-transition:leave-start="translate-x-0"
                            x-transition:leave-end="translate-x-full"
                            class="pointer-events-auto {{ $width }} bg-slate-900 border-l border-slate-800 flex flex-col shadow-2xl h-full transform-gpu"
                            style="will-change: transform;"
                        >
                            <!-- Header -->
                            <div class="border-b border-slate-800/80 bg-slate-950/40 px-8 py-5 flex items-center justify-between shrink-0">
                                @if(isset($titleContent))
                                    <h5 class="font-extrabold text-white flex items-center text-base" id="offcanvas-title-{{ $id }}">
                                        {{ $titleContent }}
                                    </h5>
                                @else
                                    <h5 class="font-extrabold text-white flex items-center text-base" id="offcanvas-title-{{ $id }}">
                                        {{ $title }}
                                    </h5>
                                @endif
                                <button type="button" x-on:click="show = false; document.body.classList.remove('overflow-hidden');" class="text-slate-400 hover:text-white hover:bg-slate-800/60 rounded-lg p-1.5 focus:outline-hidden transition-colors cursor-pointer">
                                    <span class="sr-only">Close panel</span>
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Body -->
                            <div class="flex-1 overflow-y-auto p-8 relative">
                                {{ $slot }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
