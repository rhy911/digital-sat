<!-- Quick Authoring Wizard Modal -->
<div class="modal fade" id="quickAuthorWizardModal" tabindex="-1" aria-labelledby="quickAuthorWizardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border border-slate-800/80 rounded-2xl shadow-2xl overflow-hidden glass-panel">
            <div class="modal-header px-6 py-4 border-b border-slate-800/80 bg-slate-950/40 flex justify-between items-center">
                <h5 class="modal-title text-base font-extrabold text-white flex items-center gap-3 mb-0" id="quickAuthorWizardModalLabel">
                    <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                        <i class="bi bi-magic text-indigo-400"></i>
                    </div>
                    Quick Authoring Wizard
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-6 space-y-6">
                
                <!-- Recent Continuation Section -->
                <div id="wizard-recent-work-container" class="d-none">
                    <h6 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-3">Continue Recent Work</h6>
                    <div class="flex flex-wrap gap-2" id="wizard-recent-work-list">
                        <!-- Populated via JS -->
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Option 1: Full SAT -->
                    <div class="group relative rounded-2xl border border-slate-800/80 p-6 text-center cursor-pointer bg-slate-900/40 hover:bg-slate-900/80 hover:border-indigo-500/65 hover:shadow-xl flex flex-col justify-between h-full" id="wizard-btn-full-sat">
                        <div class="flex-1 flex flex-col items-center justify-center py-4">
                            <div class="w-14 h-14 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-full flex items-center justify-center mb-4 shadow-lg">
                                <i class="bi bi-journal-text text-2.5xl"></i>
                            </div>
                            <h5 class="text-sm font-extrabold text-white mb-1 uppercase tracking-wide">Full SAT</h5>
                            <p class="text-slate-450 text-[10px] leading-relaxed max-w-[160px] mx-auto font-medium">6 modules structure.</p>
                        </div>
                    </div>

                    <!-- Option 1b: Short Test -->
                    <div class="group relative rounded-2xl border border-slate-800/80 p-6 text-center cursor-pointer bg-slate-900/40 hover:bg-slate-900/80 hover:border-emerald-500/65 hover:shadow-xl flex flex-col justify-between h-full" id="wizard-btn-short-test">
                        <div class="flex-1 flex flex-col items-center justify-center py-4">
                            <div class="w-14 h-14 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full flex items-center justify-center mb-4 shadow-lg">
                                <i class="bi bi-lightning-charge text-2.5xl"></i>
                            </div>
                            <h5 class="text-sm font-extrabold text-white mb-1 uppercase tracking-wide">Short Test</h5>
                            <p class="text-slate-450 text-[10px] leading-relaxed max-w-[160px] mx-auto font-medium">6 modules (fewer Qs).</p>
                        </div>
                    </div>

                    <!-- Option 1c: Module Only -->
                    <div class="group relative rounded-2xl border border-slate-800/80 p-6 text-center cursor-pointer bg-slate-900/40 hover:bg-slate-900/80 hover:border-rose-500/65 hover:shadow-xl flex flex-col justify-between h-full" id="wizard-btn-module-only">
                        <div class="flex-1 flex flex-col items-center justify-center py-4">
                            <div class="w-14 h-14 bg-rose-500/10 text-rose-455 border border-rose-500/20 rounded-full flex items-center justify-center mb-4 shadow-lg">
                                <i class="bi bi-box-seam text-2.5xl"></i>
                            </div>
                            <h5 class="text-sm font-extrabold text-white mb-1 uppercase tracking-wide">Module Only</h5>
                            <p class="text-slate-450 text-[10px] leading-relaxed max-w-[160px] mx-auto font-medium">Single module focused.</p>
                        </div>
                    </div>

                    <!-- Option 2: Custom Content -->
                    <div class="group relative rounded-2xl border border-slate-800/80 p-6 text-center cursor-pointer bg-slate-900/40 hover:bg-slate-900/80 hover:border-amber-500/65 hover:shadow-xl flex flex-col justify-between h-full" id="wizard-btn-custom">
                        <div class="flex-1 flex flex-col items-center justify-center py-4">
                            <div class="w-14 h-14 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full flex items-center justify-center mb-4 shadow-lg">
                                <i class="bi bi-puzzle text-2.5xl"></i>
                            </div>
                            <h5 class="text-sm font-extrabold text-white mb-1 uppercase tracking-wide">Custom</h5>
                            <p class="text-slate-450 text-[10px] leading-relaxed max-w-[160px] mx-auto font-medium">Individual pieces.</p>
                        </div>
                    </div>
                </div>

                <!-- Custom Flow Steps (Hidden initially) -->
                <div id="wizard-custom-flow" class="d-none border-t border-slate-800/80 pt-6 space-y-4">
                    <!-- Step 1: Parent Test -->
                    <div class="space-y-1.5" id="wizard-step-test">
                        <label class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Select Parent Test</label>
                        <select class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none" id="wizard-select-test">
                            <option value="">Choose a test...</option>
                            <!-- Populated via JS -->
                        </select>
                    </div>

                    <!-- Step 2: Subject & Target -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 d-none" id="wizard-step-target">
                        <div class="space-y-1.5">
                            <label class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Domain</label>
                            <select class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none" id="wizard-select-domain">
                                <option value="reading_writing">Reading & Writing</option>
                                <option value="math">Math</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Module Position</label>
                            <select class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none" id="wizard-select-module">
                                <option value="1_standard">Module 1 (Standard)</option>
                                <option value="2_easy">Module 2 (Easy)</option>
                                <option value="2_hard">Module 2 (Hard)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Navigation Actions -->
                    <div class="flex justify-between items-center pt-4 border-t border-slate-800/80 mt-6">
                        <button type="button" class="px-5 py-2.5 bg-slate-900/60 border border-slate-800/80 text-slate-350 font-extrabold text-xs uppercase tracking-wider rounded-xl hover:bg-slate-850 hover:text-white cursor-pointer flex items-center gap-2" id="wizard-btn-back">
                            <i class="bi bi-arrow-left text-xs"></i> Back
                        </button>
                        <div class="d-none" id="wizard-step-launch">
                            <button class="px-6 py-3 bg-gradient-to-r from-emerald-650 to-teal-600 hover:from-emerald-555 hover:to-teal-555 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-600/20 hover:shadow-emerald-600/35 transform flex items-center gap-2 cursor-pointer" id="wizard-btn-launch">
                                Launch Builder <i class="bi bi-arrow-right text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div id="wizard-loading" class="text-center py-10 d-none flex flex-col items-center justify-center">
                    <div class="w-10 h-10 border-4 border-indigo-400 border-t-transparent rounded-full animate-spin"></div>
                    <p class="mt-4 text-slate-400 text-xs font-extrabold uppercase tracking-widest">Generating structure...</p>
                </div>
            </div>
        </div>
    </div>
</div>
