@props([
    'id' => 'global-ready-modal',
])

<div x-data="{
    show: false,
    title: 'Ready to Begin Test?',
    testTitle: '',
    subtitle: '',
    actionRoute: '',
    inProgress: false,
    attemptInfo: ''
}"
x-on:open-ready-modal.window="
    title = $event.detail.title || 'Ready to Begin Test?';
    testTitle = $event.detail.testTitle || '';
    subtitle = $event.detail.subtitle || '';
    actionRoute = $event.detail.actionRoute || '';
    inProgress = !!$event.detail.inProgress;
    attemptInfo = $event.detail.attemptInfo || '';
    show = true;
    $nextTick(() => window.dispatchEvent(new CustomEvent('open-modal', { detail: '{{ $id }}' })));
"
x-on:close-modal.window="if ($event.detail === '{{ $id }}') { show = false; }"
x-on:keydown.escape.window="if (show) $dispatch('close-modal', '{{ $id }}')"
x-cloak>
    <template x-teleport="body">
        <div id="{{ $id }}" data-modal-dialog="{{ $id }}" x-show="show" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="ready-modal-title-{{ $id }}" :aria-hidden="show ? 'false' : 'true'">
            <!-- Backdrop -->
            <div x-show="show"
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm"
                x-on:click="$dispatch('close-modal', '{{ $id }}')" aria-hidden="true"></div>

            <!-- Modal Panel -->
            <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4 text-center">
                <div x-show="show"
                    x-transition:enter="transition-[transform,opacity] ease-out duration-200 will-change-[transform,opacity]"
                    x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="transition-[transform,opacity] ease-in duration-150 will-change-[transform,opacity]"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-0 sm:scale-95"
                    class="relative flex flex-col w-full transform overflow-hidden rounded-t-2xl sm:rounded-2xl bg-white text-left shadow-2xl sm:my-8 sm:w-full sm:max-w-3xl border border-slate-200/90 transform-gpu">

                    <div class="sm:hidden w-12 h-1 bg-slate-300 rounded-full mx-auto my-2.5 shrink-0" aria-hidden="true"></div>

                    <!-- Header -->
                    <div class="bg-slate-50/95 px-5 py-3.5 border-b border-slate-200/80 flex justify-between items-center shrink-0">
                        <h3 id="ready-modal-title-{{ $id }}" class="text-base font-semibold text-slate-900 tracking-tight m-0" x-text="title"></h3>
                        <button type="button" x-on:click="$dispatch('close-modal', '{{ $id }}')" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 rounded-lg p-1.5 transition-colors cursor-pointer" aria-label="Close modal">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body Content: 2-Column Horizontal Layout -->
                    <div class="p-4 sm:p-5 text-slate-700">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch">
                            <!-- Left: Test Identity & Launch Note -->
                            <div class="flex flex-col justify-between p-4 rounded-xl bg-slate-50 border border-slate-200/90 space-y-3">
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <span class="text-3xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-200/80 text-slate-700">Digital SAT Session</span>
                                        <template x-if="inProgress">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-3xs font-bold bg-amber-50 text-amber-800 border border-amber-200/80">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                In Progress
                                            </span>
                                        </template>
                                        <template x-if="!inProgress">
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-3xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200/80">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                New Attempt
                                            </span>
                                        </template>
                                    </div>
                                    <h3 class="text-base font-bold text-slate-900 tracking-tight m-0" x-text="testTitle"></h3>
                                    <template x-if="subtitle">
                                        <p class="text-xs text-slate-500 mt-1 m-0" x-text="subtitle"></p>
                                    </template>
                                    <template x-if="attemptInfo">
                                        <div class="text-2xs font-mono font-semibold text-brand mt-2 pt-2 border-t border-slate-200" x-text="attemptInfo"></div>
                                    </template>
                                </div>
                                <div class="text-xs text-slate-600 bg-white p-3 rounded-lg border border-slate-200/80 leading-relaxed">
                                    Click <strong>I'm Ready</strong> below to launch the testing environment in full-screen exam mode.
                                </div>
                            </div>

                            <!-- Right: Readiness & Rules Checklist -->
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/90 text-xs space-y-2.5">
                                <div class="font-bold uppercase tracking-wider text-2xs text-slate-500 flex items-center gap-1.5">
                                    <x-ui.icon name="check-circle" class="w-3.5 h-3.5 text-brand" />
                                    <span>Readiness &amp; Rule Checklist</span>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex items-start gap-2.5 text-slate-700">
                                        <div class="w-6 h-6 rounded-md bg-emerald-50 border border-emerald-200/80 flex items-center justify-center shrink-0 mt-0.5">
                                            <x-ui.icon name="wifi" class="w-3.5 h-3.5 text-emerald-600" />
                                        </div>
                                        <span class="leading-relaxed">Stable internet connection required for session sync.</span>
                                    </div>
                                    <div class="flex items-start gap-2.5 text-slate-700">
                                        <div class="w-6 h-6 rounded-md bg-amber-50 border border-amber-200/80 flex items-center justify-center shrink-0 mt-0.5">
                                            <x-ui.icon name="clock" class="w-3.5 h-3.5 text-amber-600" />
                                        </div>
                                        <span class="leading-relaxed">Timer begins immediately once you confirm readiness.</span>
                                    </div>
                                    <div class="flex items-start gap-2.5 text-slate-700">
                                        <div class="w-6 h-6 rounded-md bg-blue-50 border border-blue-200/80 flex items-center justify-center shrink-0 mt-0.5">
                                            <x-ui.icon name="calculator" class="w-3.5 h-3.5 text-blue-600" />
                                        </div>
                                        <span class="leading-relaxed">Built-in Desmos graphing calculator and tools are active.</span>
                                    </div>
                                    <div class="flex items-start gap-2.5 text-slate-700">
                                        <div class="w-6 h-6 rounded-md bg-indigo-50 border border-indigo-200/80 flex items-center justify-center shrink-0 mt-0.5">
                                            <x-ui.icon name="shield-check" class="w-3.5 h-3.5 text-indigo-600" />
                                        </div>
                                        <span class="leading-relaxed">Your answers and progress are continuously auto-saved.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer / Actions -->
                    <div class="bg-slate-50/95 px-5 py-3.5 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-200/80 gap-3 shrink-0">
                        <form method="POST" :action="actionRoute" class="m-0 w-full sm:w-auto">
                            @csrf
                            <button type="submit" data-dialog-initial-focus class="w-full sm:w-auto justify-center px-5 py-2.5 rounded-lg bg-brand hover:bg-brand/90 active:bg-brand/95 text-white font-semibold text-sm shadow-sm transition-colors cursor-pointer flex items-center justify-center gap-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                                <span x-text="inProgress ? 'I\'m Ready — Resume Test' : 'I\'m Ready — Begin Test'"></span>
                                <x-ui.icon name="arrow-right" class="w-4 h-4" />
                            </button>
                        </form>
                        <button type="button" class="w-full sm:w-auto justify-center px-4 py-2.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-medium text-sm transition-colors cursor-pointer mt-2 sm:mt-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2" x-on:click="$dispatch('close-modal', '{{ $id }}')">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
