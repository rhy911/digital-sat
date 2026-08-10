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
"
x-on:keydown.escape.window="show = false"
x-cloak>
    <template x-teleport="body">
        <div id="{{ $id }}" x-show="show" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" :aria-hidden="show ? 'false' : 'true'">
            <!-- Backdrop -->
            <div x-show="show"
                x-transition:enter="transition-opacity ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-950/60"
                x-on:click="show = false" aria-hidden="true"></div>

            <!-- Modal Panel -->
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div x-show="show"
                    x-transition:enter="transition-[transform,opacity] ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition-[transform,opacity] ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl sm:my-8 sm:w-full sm:max-w-lg border border-slate-200"
                    style="border-color: var(--border-strong); transform: translateZ(0); will-change: transform, opacity;">

                    <!-- Header -->
                    <div class="bg-slate-50 px-4 py-3 border-b flex justify-between items-center" style="background: var(--binder-bg); border-color: var(--border);">
                        <h3 class="text-base font-semibold text-slate-900" style="color: var(--ink);" x-text="title"></h3>
                        <button type="button" x-on:click="show = false" class="text-slate-400 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/20 rounded-lg p-1 transition-colors cursor-pointer" aria-label="Close modal">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body Content -->
                    <div class="p-5 space-y-4 text-slate-700">
                        <!-- Test Identity Banner -->
                        <div class="p-4 rounded-xl border" style="background: var(--binder-bg); border-color: var(--border-strong);">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="type-tag">Digital SAT Session</span>
                                <template x-if="inProgress">
                                    <span class="status-pill pending text-3xs font-bold"><span class="d"></span>In Progress</span>
                                </template>
                                <template x-if="!inProgress">
                                    <span class="status-pill ok text-3xs font-bold"><span class="d"></span>New Attempt</span>
                                </template>
                            </div>
                            <h3 class="text-base font-bold text-main mt-1" style="margin: 0;" x-text="testTitle"></h3>
                            <template x-if="subtitle">
                                <p class="text-xs text-muted mt-1" x-text="subtitle"></p>
                            </template>
                            <template x-if="attemptInfo">
                                <div class="text-2xs font-mono font-semibold text-accent mt-2 pt-2 border-t" style="border-color: var(--border);" x-text="attemptInfo"></div>
                            </template>
                        </div>

                        <!-- Readiness & Rules Checklist -->
                        <div class="p-4 rounded-xl border text-xs space-y-2" style="background: var(--manila); border-color: var(--binder-border); color: var(--manila-ink);">
                            <div class="font-bold uppercase tracking-wider text-2xs" style="color: var(--manila-soft);">Readiness &amp; Rule Checklist</div>
                            <ul class="space-y-1.5 list-disc pl-4 leading-relaxed">
                                <li>Ensure you have a stable internet connection before launching.</li>
                                <li>The module timer begins immediately when you click <strong>I'm Ready</strong>.</li>
                                <li>Built-in calculator, reference sheet, and annotation tools are available inside the engine.</li>
                                <li>Your progress is continuously auto-saved.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Footer / Actions -->
                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t gap-3" style="background: var(--binder-bg); border-color: var(--border);">
                        <form method="POST" :action="actionRoute" class="m-0 w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="btn-sm-primary btn-px-16 w-full sm:w-auto justify-center" style="padding: 8px 18px; font-size: 13px;">
                                <span x-text="inProgress ? 'I\'m Ready — Resume Test' : 'I\'m Ready — Begin Test'"></span>
                            </button>
                        </form>
                        <button type="button" class="btn-sm-ghost w-full sm:w-auto justify-center mt-2 sm:mt-0 cursor-pointer hover:bg-slate-200/80 hover:text-slate-900 transition-colors" x-on:click="show = false">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
