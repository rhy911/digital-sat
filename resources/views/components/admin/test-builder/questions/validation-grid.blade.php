<div id="validation-grid-container" class="dash-panel overflow-hidden mb-8 hidden">
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
        <div>
            <h5 class="text-base font-extrabold text-slate-900 flex items-center gap-3 tracking-tight">
                <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-100 flex items-center justify-center">
                    <x-ui.icon name="grid-3x3-gap-fill" class="w-4 h-4 text-amber-600" />
                </div>
                <span>Review &amp; Validate Import Items</span>
            </h5>
            <p class="text-xs text-slate-500 mt-1 font-medium">Double-click cells to edit import fields. Red cells block
                import; yellow cells need review.</p>
        </div>
        <div>
            <button type="button"
                class="text-slate-500 hover:text-slate-900 transition-colors cursor-pointer flex items-center justify-center w-8 h-8 rounded-full hover:bg-slate-100"
                id="gridCloseBtn" aria-label="Close import validation grid">
                <x-ui.icon name="x-lg" class="w-4 h-4" />
            </button>
        </div>
    </div>
    <div class="p-6">
        <div
            class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200 mb-6">
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-slate-500 font-extrabold text-[11px] border border-slate-200">1</span>
                <span class="font-extrabold text-slate-500 text-xs uppercase tracking-wider">Select module</span>
            </div>
            <x-ui.icon name="chevron-right" class="w-3.5 h-3.5 text-slate-300 hidden md:block" />
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-slate-500 font-extrabold text-[11px] border border-slate-200">2</span>
                <span class="font-extrabold text-slate-500 text-xs uppercase tracking-wider">Upload &amp; parse</span>
            </div>
            <x-ui.icon name="chevron-right" class="w-3.5 h-3.5 text-slate-300 hidden md:block" />
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-700 font-extrabold text-[11px] border border-amber-200">3</span>
                <span class="font-extrabold text-amber-700 text-xs uppercase tracking-wider">Review grid</span>
            </div>
            <x-ui.icon name="chevron-right" class="w-3.5 h-3.5 text-slate-300 hidden md:block" />
            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-slate-400 font-extrabold text-[11px] border border-slate-200">4</span>
                <span class="font-extrabold text-slate-400 text-xs uppercase tracking-wider">Import complete</span>
            </div>
        </div>

        <!-- Error Summary Header / Status Alert -->
        <div id="gridStatusAlert"
            class="p-4 rounded-xl mb-5 bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <x-ui.icon name="exclamation-triangle-fill" class="w-6 h-6 text-amber-600 shrink-0 leading-none" />
                <div>
                    <strong class="font-extrabold text-amber-800 uppercase tracking-wider text-xs"
                        id="gridStatusTitle">Validation Errors Found</strong>
                    <div id="gridStatusMsg" class="text-xs text-amber-700 mt-0.5 leading-relaxed font-medium">Fix
                        required SAT fields in the grid before import.</div>
                </div>
            </div>
            <div class="flex gap-2 shrink-0">
                <span
                    class="inline-flex items-center px-3 py-1.5 rounded-full text-[10px] font-extrabold bg-rose-50 text-rose-700 border border-rose-100 uppercase tracking-wide"
                    id="gridBlockerCount">0 Blocker(s)</span>
                <span
                    class="inline-flex items-center px-3 py-1.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-700 border border-amber-200 uppercase tracking-wide"
                    id="gridWarningCount">0 Warning(s)</span>
            </div>
        </div>

        <!-- Spreadsheet container -->
        <div class="border border-slate-200 rounded-xl bg-white overflow-hidden mb-4">
            <div id="validation-grid" class="w-full bg-white min-h-[380px]"></div>
        </div>

        <!-- Actions row -->
        <div
            class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 mt-4 pt-4 border-t border-slate-200">
            <div>
                <button type="button"
                    class="w-full px-5 py-3 bg-white border border-slate-200 text-slate-700 font-extrabold text-xs uppercase tracking-wider rounded-xl hover:bg-slate-50 flex items-center justify-center gap-2 cursor-pointer"
                    id="gridCancelBtn">
                    <x-ui.icon name="x-circle" class="w-3.5 h-3.5 leading-none" /> Discard Import
                </button>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <button type="button"
                    class="px-5 py-3 bg-sky-50 border border-sky-100 hover:bg-sky-100 text-sky-700 font-extrabold text-xs uppercase tracking-wider rounded-xl flex items-center justify-center gap-1.5 cursor-pointer"
                    id="gridRevalidateBtn">
                    <x-ui.icon name="arrow-repeat" class="w-3.5 h-3.5 leading-none" /> Re-validate Grid
                </button>
                <button type="button"
                    class="px-6 py-3 bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl flex items-center justify-center gap-1.5 cursor-pointer"
                    id="gridImportApprovedBtn">
                    <x-ui.icon name="check-circle-fill" class="w-3.5 h-3.5 leading-none" /> Import Approved Rows
                </button>
            </div>
        </div>
    </div>
</div>
