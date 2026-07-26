<div x-data="{
    toasts: [],
    addToast(type, message, list = []) {
        const id = Date.now() + Math.random();
        this.toasts.push({ id, type, message, list });
        setTimeout(() => this.removeToast(id), type === 'error' ? 7000 : 4500);
    },
    removeToast(id) {
        this.toasts = this.toasts.filter(t => t.id !== id);
    }
}"
x-init="
    @if (session('success'))
        addToast('success', @js(session('success')));
    @endif
    @if (session('status'))
        addToast('success', @js(session('status')));
    @endif
    @if (session('error'))
        addToast('error', @js(session('error')));
    @endif
    @if ($errors->any())
        addToast('error', 'Please check the form for errors:', @js($errors->all()));
    @endif
"
x-on:show-toast.window="addToast($event.detail.type || 'info', $event.detail.message, $event.detail.list || [])"
class="fixed top-5 right-5 z-[99999] flex flex-col gap-3 max-w-sm w-full pointer-events-none"
style="z-index: 99999;"
x-cloak>
    <template x-teleport="body">
        <div class="fixed top-5 right-5 z-[99999] flex flex-col gap-3 max-w-sm w-full pointer-events-none" style="z-index: 99999;">
            <template x-for="toast in toasts" :key="toast.id">
                <div x-show="true"
                    x-transition:enter="transition-[transform,opacity] ease-out duration-250"
                    x-transition:enter-start="opacity-0 translate-y-[-10px] scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition-[transform,opacity] ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-[-10px] scale-95"
                    class="pointer-events-auto relative overflow-hidden rounded-xl p-4 shadow-xl border flex items-start gap-3 transform transition-all duration-200"
                    :class="{
                        'bg-emerald-50/95 border-emerald-200 text-emerald-900 shadow-emerald-900/10': toast.type === 'success',
                        'bg-rose-50/95 border-rose-200 text-rose-900 shadow-rose-900/10': toast.type === 'error',
                        'bg-blue-50/95 border-blue-200 text-blue-900 shadow-blue-900/10': toast.type === 'info'
                    }"
                    style="transform: translateZ(0); will-change: transform, opacity; backdrop-filter: blur(8px);">

                    <!-- Toast Icon -->
                    <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-xs shadow-sm mt-0.5"
                        :class="{
                            'bg-emerald-600 text-white': toast.type === 'success',
                            'bg-rose-600 text-white': toast.type === 'error',
                            'bg-blue-600 text-white': toast.type === 'info'
                        }">
                        <span x-show="toast.type === 'success'">✓</span>
                        <span x-show="toast.type === 'error'">✕</span>
                        <span x-show="toast.type === 'info'">i</span>
                    </div>

                    <!-- Toast Content -->
                    <div class="flex-1 text-xs leading-relaxed font-medium">
                        <div class="font-bold text-sm mb-0.5" x-text="toast.type === 'success' ? 'Success' : (toast.type === 'error' ? 'Action Failed' : 'Notice')"></div>
                        <div x-text="toast.message"></div>
                        <template x-if="toast.list && toast.list.length > 0">
                            <ul class="mt-1.5 pl-4 list-disc space-y-0.5 text-xs opacity-90">
                                <template x-for="(err, idx) in toast.list" :key="idx">
                                    <li x-text="err"></li>
                                </template>
                            </ul>
                        </template>
                    </div>

                    <!-- Close Button -->
                    <button type="button" @click="removeToast(toast.id)"
                        class="text-slate-400 hover:text-slate-700 transition-colors p-1 -mr-1 -mt-1 rounded-lg focus:outline-none"
                        aria-label="Close notification">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </template>
</div>
