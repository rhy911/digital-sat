@props([
    'id' => 'global-confirm-delete-modal',
])

<div x-data="{
    show: false,
    title: 'Delete Item?',
    heading: 'Permanent Action',
    message: 'Are you sure you want to delete this item? This action cannot be undone.',
    actionUrl: '',
    confirmButtonText: 'Delete Permanently',
    method: 'DELETE'
}"
x-on:open-confirm-delete.window="
    title = $event.detail.title || 'Delete Item?';
    heading = $event.detail.heading || 'Permanent Action';
    message = $event.detail.message || 'Are you sure you want to delete this item? This action cannot be undone.';
    actionUrl = $event.detail.actionUrl || '';
    confirmButtonText = $event.detail.confirmButtonText || 'Delete Permanently';
    method = ($event.detail.method || 'DELETE').toUpperCase();
    show = true;
"
x-on:keydown.escape.window="show = false"
x-cloak>
    <template x-teleport="body">
        <div id="{{ $id }}" x-show="show" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" :aria-hidden="show ? 'false' : 'true'">
            <!-- Backdrop -->
            <div x-show="show"
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-slate-950/60"
                x-on:click="show = false" aria-hidden="true"></div>

            <!-- Modal Panel -->
            <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4 text-center">
                <div x-show="show"
                    x-transition:enter="transition-[transform,opacity] ease-out duration-200 will-change-[transform,opacity]"
                    x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="transition-opacity ease-in duration-150 will-change-[opacity]"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="relative flex flex-col w-full transform overflow-hidden rounded-t-2xl sm:rounded-2xl bg-white text-left shadow-2xl sm:my-8 sm:w-full sm:max-w-md border border-slate-200/90 transform-gpu">
                    
                    <div class="sm:hidden w-12 h-1 bg-slate-300 rounded-full mx-auto my-2.5 shrink-0" aria-hidden="true"></div>

                    <!-- Header -->
                    <div class="bg-slate-50/95 px-5 py-3.5 border-b border-slate-200/80 flex justify-between items-center shrink-0">
                        <h3 class="text-base font-semibold text-rose-700 flex items-center gap-2 m-0" x-text="title"></h3>
                        <button type="button" x-on:click="show = false" class="text-slate-400 hover:text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/20 rounded-lg p-1.5 transition-colors cursor-pointer" aria-label="Close modal">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body Content -->
                    <div class="p-5 space-y-3 text-slate-700">
                        <div class="p-4 rounded-xl bg-rose-50/70 border border-rose-200/80 flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-rose-100/90 border border-rose-200 flex items-center justify-center shrink-0">
                                <x-ui.icon name="alert-triangle" class="w-5 h-5 text-rose-600" />
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-rose-900 mb-1" x-text="heading">Permanent Action</h4>
                                <p class="text-xs text-rose-800 leading-relaxed m-0" x-text="message"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer / Actions -->
                    <div class="bg-slate-50/95 px-5 py-3.5 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-200/80 gap-3 shrink-0">
                        <form method="POST" :action="actionUrl" class="m-0 w-full sm:w-auto">
                            @csrf
                            <template x-if="method !== 'POST'">
                                <input type="hidden" name="_method" :value="method">
                            </template>
                            <button type="submit" class="w-full sm:w-auto justify-center px-4 py-2.5 rounded-lg bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white font-semibold text-sm shadow-sm transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2">
                                <span x-text="confirmButtonText">Delete Permanently</span>
                            </button>
                        </form>
                        <button type="button" class="w-full sm:w-auto justify-center px-4 py-2.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-medium text-sm transition-colors cursor-pointer mt-2 sm:mt-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2" x-on:click="show = false">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
