@props([
    'id' => 'global-confirm-delete-modal',
])

<div x-data="{
    show: false,
    title: 'Delete Item?',
    message: 'Are you sure you want to delete this item? This action cannot be undone.',
    actionUrl: ''
}"
x-on:open-confirm-delete.window="
    title = $event.detail.title || 'Delete Item?';
    message = $event.detail.message || 'Are you sure you want to delete this item? This action cannot be undone.';
    actionUrl = $event.detail.actionUrl || '';
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
                class="fixed inset-0 bg-slate-950/65"
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
                    class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl sm:my-8 sm:w-full sm:max-w-md border border-slate-200"
                    style="border-color: var(--border-strong); transform: translateZ(0); will-change: transform, opacity;">

                    <!-- Header -->
                    <div class="bg-slate-50 px-4 py-3 border-b flex justify-between items-center" style="background: var(--binder-bg); border-color: var(--border);">
                        <h3 class="text-base font-semibold text-rose-700 flex items-center gap-2" style="color: var(--red); margin: 0;" x-text="title"></h3>
                        <button type="button" x-on:click="show = false" class="text-slate-400 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/20 rounded-lg p-1 transition-colors cursor-pointer" aria-label="Close modal">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body Content -->
                    <div class="p-5 space-y-3 text-slate-700">
                        <div class="p-4 rounded-xl border flex items-start gap-3" style="background: var(--red-soft, #FFF5F5); border-color: rgba(193, 68, 58, 0.2);">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background: var(--red); color: #fff; font-size: 14px;">
                                🗑️
                            </div>
                            <div>
                                <h4 class="text-sm font-bold" style="color: var(--red); margin: 0 0 4px 0;">Permanent Action</h4>
                                <p class="text-xs leading-relaxed" style="color: var(--ink-soft); margin: 0;" x-text="message"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer / Actions -->
                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t gap-3" style="background: var(--binder-bg); border-color: var(--border);">
                        <form method="POST" :action="actionUrl" class="m-0 w-full sm:w-auto">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-sm-primary w-full sm:w-auto justify-center" style="background: var(--red, #C1443A); padding: 8px 18px; font-size: 13px;">
                                Confirm Delete
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
