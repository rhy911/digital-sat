<x-ui.modal id="quickAuthorWizardModal" max-width="3xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div
                class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center shadow-inner">
                <i class="bi bi-magic text-indigo-400 text-xl"></i>
            </div>
            <div>
                <h4 class="text-base font-extrabold text-white leading-none">Content Creation Wizard</h4>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1.5 leading-none">Select
                    your workflow</p>
            </div>
        </div>
    </x-slot:title>

    <div class="space-y-8 py-2">
        <!-- Recent Continuation Section -->
        <div id="wizard-recent-work-container" class="hidden">
            <div class="flex items-center justify-between mb-3 px-1">
                <h6 class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest flex items-center gap-2">
                    <span class="w-1 h-1 rounded-full bg-indigo-500"></span> Continue Recent Work
                </h6>
            </div>
            <div class="flex flex-wrap gap-2.5" id="wizard-recent-work-list">
                <!-- Populated via JS -->
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
            <!-- Option 1: Full SAT -->
            <button type="button"
                class="group relative rounded-2xl border border-slate-700/50 bg-slate-900/40 p-6 text-center cursor-pointer hover:border-indigo-500/60 hover:bg-slate-800/80 hover:shadow-2xl hover:shadow-indigo-500/10 transition-colors duration-200 flex flex-col items-center gap-5 outline-none focus:ring-2 focus:ring-indigo-500/40 transform-gpu"
                id="wizard-btn-full-sat">
                <div
                    class="w-16 h-16 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-2xl flex items-center justify-center group-hover:scale-110 group-hover:bg-indigo-500/20 transition-all duration-300 shadow-lg">
                    <i class="bi bi-journal-text text-3xl leading-none"></i>
                </div>
                <div>
                    <h4 class="text-base font-black text-white mb-2 uppercase tracking-wider leading-none">Full SAT
                    </h4>
                    <p class="text-slate-400 text-sm leading-snug font-bold">Standard 6-module structure</p>
                </div>
                <div
                    class="absolute -top-1.5 -right-1.5 px-2.5 py-1 bg-indigo-600 text-[8px] font-black text-white rounded-lg uppercase tracking-tighter shadow-lg z-10">
                    Classic</div>
            </button>

            <!-- Option 1b: Short Test -->
            <button type="button"
                class="group relative rounded-2xl border border-slate-700/50 bg-slate-900/40 p-6 text-center cursor-pointer hover:border-emerald-500/60 hover:bg-slate-800/80 hover:shadow-2xl hover:shadow-emerald-500/10 transition-colors duration-200 flex flex-col items-center gap-5 outline-none focus:ring-2 focus:ring-emerald-500/40 transform-gpu"
                id="wizard-btn-short-test">
                <div
                    class="w-16 h-16 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-2xl flex items-center justify-center group-hover:scale-110 group-hover:bg-emerald-500/20 transition-all duration-300 shadow-lg">
                    <i class="bi bi-lightning-charge text-3xl leading-none"></i>
                </div>
                <div>
                    <h4 class="text-base font-black text-white mb-2 uppercase tracking-wider leading-none">Short Test
                    </h4>
                    <p class="text-slate-400 text-sm leading-snug font-bold">Optimized custom modules</p>
                </div>
            </button>

            <!-- Option 1c: Module Only -->
            <button type="button"
                class="group relative rounded-2xl border border-slate-700/50 bg-slate-900/40 p-6 text-center cursor-pointer hover:border-rose-500/60 hover:bg-slate-800/80 hover:shadow-2xl hover:shadow-rose-500/10 transition-colors duration-200 flex flex-col items-center gap-5 outline-none focus:ring-2 focus:ring-rose-500/40 transform-gpu"
                id="wizard-btn-module-only">
                <div
                    class="w-16 h-16 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded-2xl flex items-center justify-center group-hover:scale-110 group-hover:bg-rose-500/20 transition-all duration-300 shadow-lg">
                    <i class="bi bi-box-seam text-3xl leading-none"></i>
                </div>
                <div>
                    <h4 class="text-base font-black text-white mb-2 uppercase tracking-wider leading-none">Module</h4>
                    <p class="text-slate-400 text-sm leading-snug font-bold">Single focused reusable pool</p>
                </div>
            </button>

            <!-- Option 2: Custom Content -->
            <button type="button"
                class="group relative rounded-2xl border border-slate-700/50 bg-slate-900/40 p-6 text-center cursor-pointer hover:border-amber-500/60 hover:bg-slate-800/80 hover:shadow-2xl hover:shadow-amber-500/10 transition-colors duration-200 flex flex-col items-center gap-5 outline-none focus:ring-2 focus:ring-amber-500/40 transform-gpu"
                id="wizard-btn-custom">
                <div
                    class="w-16 h-16 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-2xl flex items-center justify-center group-hover:scale-110 group-hover:bg-amber-500/20 transition-all duration-300 shadow-lg">
                    <i class="bi bi-puzzle text-3xl leading-none"></i>
                </div>
                <div>
                    <h4 class="text-base font-black text-white mb-2 uppercase tracking-wider leading-none">Custom</h4>
                    <p class="text-slate-400 text-sm leading-snug font-bold">Assemble mixed SAT parts</p>
                </div>
            </button>
        </div>

        <!-- Custom Flow Steps (Hidden initially) -->
        <div id="wizard-custom-flow" class="hidden border-t border-slate-800/60 pt-8 space-y-6">
            <!-- Step 1: Parent Test -->
            <div class="space-y-2" id="wizard-step-test">
                <div class="flex items-center gap-2 mb-1 px-1">
                    <span
                        class="w-5 h-5 rounded-full bg-slate-800 text-slate-400 text-[10px] font-black flex items-center justify-center border border-slate-700">1</span>
                    <label class="text-[10px] font-extrabold text-slate-400 tracking-widest uppercase">Select Parent
                        Test</label>
                </div>
                <select
                    class="w-full px-4 py-3 rounded-xl border border-slate-800 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none text-sm transition-all shadow-inner appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2364748b%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                    id="wizard-select-test">
                    <option value="">Choose a test...</option>
                    <!-- Populated via JS -->
                </select>
            </div>

            <!-- Step 2: Subject & Target -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 hidden" id="wizard-step-target">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 mb-1 px-1">
                        <span
                            class="w-5 h-5 rounded-full bg-slate-800 text-slate-400 text-[10px] font-black flex items-center justify-center border border-slate-700">2</span>
                        <label
                            class="text-[10px] font-extrabold text-slate-400 tracking-widest uppercase">Domain</label>
                    </div>
                    <select
                        class="w-full px-4 py-3 rounded-xl border border-slate-800 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none text-sm transition-all shadow-inner appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2364748b%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                        id="wizard-select-domain">
                        <option value="reading_writing">Reading & Writing</option>
                        <option value="math">Math</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 mb-1 px-1">
                        <span
                            class="w-5 h-5 rounded-full bg-slate-800 text-slate-400 text-[10px] font-black flex items-center justify-center border border-slate-700">3</span>
                        <label
                            class="text-[10px] font-extrabold text-slate-400 tracking-widest uppercase">Position</label>
                    </div>
                    <select
                        class="w-full px-4 py-3 rounded-xl border border-slate-800 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none text-sm transition-all shadow-inner appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2364748b%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                        id="wizard-select-module">
                        <option value="1_standard">Module 1 (Standard)</option>
                        <option value="2_easy">Module 2 (Easy)</option>
                        <option value="2_hard">Module 2 (Hard)</option>
                    </select>
                </div>
            </div>

            <!-- Navigation Actions -->
            <div class="flex justify-between items-center pt-6 border-t border-slate-800/60 mt-4">
                <button type="button"
                    class="px-5 py-3 bg-slate-800/60 border border-slate-700/50 text-slate-400 font-extrabold text-[10px] uppercase tracking-widest rounded-xl hover:bg-slate-700 hover:text-white transition-all flex items-center gap-2.5 cursor-pointer shadow-lg active:scale-95"
                    id="wizard-btn-back">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
                <div class="hidden" id="wizard-step-launch">
                    <button type="button"
                        class="px-7 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-[10px] uppercase tracking-widest rounded-xl shadow-xl shadow-emerald-600/20 hover:shadow-emerald-600/35 transform hover:-translate-y-0.5 active:translate-y-0 active:scale-95 transition-all flex items-center gap-2.5 cursor-pointer"
                        id="wizard-btn-launch">
                        Launch Builder <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div id="wizard-loading" class="text-center py-16 hidden flex flex-col items-center justify-center">
            <div class="relative w-16 h-16">
                <div class="absolute inset-0 border-4 border-indigo-500/20 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin">
                </div>
            </div>
            <h4 class="mt-6 text-white font-extrabold text-sm uppercase tracking-[0.2em] animate-pulse">Generating</h4>
            <p class="mt-2 text-slate-500 text-[10px] font-bold uppercase tracking-widest">Building your SAT
                structure...</p>
        </div>
    </div>
</x-ui.modal>