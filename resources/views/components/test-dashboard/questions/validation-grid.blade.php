<div id="validation-grid-container"
    class="rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden mb-8 hidden glass-panel">
    <div class="px-6 py-4 bg-slate-950 border-b border-slate-800 flex justify-between items-center">
        <div>
            <h5 class="text-base font-extrabold text-white flex items-center gap-3 tracking-tight">
                <div
                    class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/30 flex items-center justify-center">
                    <i class="bi bi-grid-3x3-gap-fill text-amber-400"></i>
                </div>
                <span>Review &amp; Validate Import Items</span>
            </h5>
            <p class="text-xs text-slate-400 mt-1 font-medium">Double-click cells to modify directly inside the matrix.
                Red boundaries highlight blocks, yellow represent warnings.</p>
        </div>
        <div>
            <button type="button"
                class="text-slate-400 hover:text-white transition-colors cursor-pointer flex items-center justify-center w-8 h-8 rounded-full hover:bg-slate-800/60"
                id="gridCloseBtn" aria-label="Close">
                <i class="bi bi-x-lg text-base"></i>
            </button>
        </div>
    </div>
    <div class="p-6">
        <!-- Glassmorphic Stepper -->
        <div
            class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-xl shadow-inner bg-slate-950/40 border border-slate-800/80 mb-6">
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-800 text-slate-400 font-extrabold text-[11px] border border-slate-700/60">1</span>
                <span class="font-extrabold text-slate-500 text-xs uppercase tracking-wider">Select Module</span>
            </div>
            <i class="bi bi-chevron-right text-slate-700 hidden md:block text-sm"></i>
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-800 text-slate-400 font-extrabold text-[11px] border border-slate-700/60">2</span>
                <span class="font-extrabold text-slate-500 text-xs uppercase tracking-wider">Upload &amp; Parse</span>
            </div>
            <i class="bi bi-chevron-right text-slate-700 hidden md:block text-sm"></i>
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-500 text-white font-extrabold text-[11px] border border-amber-600/25 shadow-lg">3</span>
                <span class="font-extrabold text-amber-400 text-xs uppercase tracking-wider">Interactive Grid
                    Correction</span>
            </div>
            <i class="bi bi-chevron-right text-slate-700 hidden md:block text-sm"></i>
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-900 text-slate-600 font-extrabold text-[11px] border border-slate-800/60">4</span>
                <span class="font-extrabold text-slate-600 text-xs uppercase tracking-wider">Import Complete</span>
            </div>
        </div>

        <!-- Error Summary Header / Status Alert -->
        <div id="gridStatusAlert"
            class="p-4 rounded-xl mb-5 bg-amber-500/5 border border-amber-500/15 text-amber-300 flex items-center justify-between shadow-xl gap-4">
            <div class="flex items-center gap-3">
                <i class="bi bi-exclamation-triangle-fill text-2xl text-amber-400 shrink-0 leading-none"></i>
                <div>
                    <strong class="font-extrabold text-amber-400 uppercase tracking-wider text-xs"
                        id="gridStatusTitle">Validation Errors Found</strong>
                    <div id="gridStatusMsg" class="text-xs text-amber-400 mt-0.5 leading-relaxed font-medium">Fix
                        validation bugs immediately in the matrix fields. Hover columns for dynamic helper tips.</div>
                </div>
            </div>
            <div class="flex gap-2 shrink-0">
                <span
                    class="inline-flex items-center px-3 py-1.5 rounded-full text-[10px] font-extrabold bg-rose-500/10 text-rose-400 border border-rose-500/20 uppercase tracking-wide"
                    id="gridBlockerCount">0 Blocker(s)</span>
                <span
                    class="inline-flex items-center px-3 py-1.5 rounded-full text-[10px] font-extrabold bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase tracking-wide"
                    id="gridWarningCount">0 Warning(s)</span>
            </div>
        </div>

        <!-- Spreadsheet container -->
        <div class="border border-slate-800/80 rounded-xl shadow-2xl bg-transparent overflow-hidden mb-4">
            <div id="validation-grid" class="w-full bg-transparent validation-grid-container"></div>
        </div>

        <!-- Actions row -->
        <div
            class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 mt-4 pt-4 border-t border-slate-800/80">
            <div>
                <button type="button"
                    class="w-full px-5 py-3 bg-slate-900/60 border border-slate-800/80 text-slate-200 font-extrabold text-xs uppercase tracking-wider rounded-xl hover:bg-slate-800 hover:text-white shadow-lg flex items-center justify-center gap-2 cursor-pointer"
                    id="gridCancelBtn">
                    <i class="bi bi-x-circle text-sm leading-none"></i> Discard Import
                </button>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <button type="button"
                    class="px-5 py-3 bg-sky-600/10 border border-sky-500/25 hover:bg-sky-600/20 text-sky-400 font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg flex items-center justify-center gap-1.5 cursor-pointer"
                    id="gridRevalidateBtn">
                    <i class="bi bi-arrow-repeat text-sm leading-none"></i> Re-validate Grid
                </button>
                <button type="button"
                    class="px-6 py-3 bg-linear-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-600/20 hover:shadow-emerald-600/35 transform flex items-center justify-center gap-1.5 cursor-pointer"
                    id="gridImportApprovedBtn">
                    <i class="bi bi-check-circle-fill text-sm leading-none"></i> Import Approved Rows
                </button>
            </div>
        </div>
    </div>
</div>