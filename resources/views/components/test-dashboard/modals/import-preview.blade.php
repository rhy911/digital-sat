<!-- Import Preview Modal -->
<div class="modal fade" id="importPreviewModal" tabindex="-1" aria-labelledby="importPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border border-slate-800/80 rounded-2xl shadow-2xl overflow-hidden glass-panel">
            <div class="modal-header px-6 py-4 bg-slate-950/40 border-b border-slate-800/80 flex justify-between items-center">
                <h5 class="modal-title text-sm font-bold text-white flex items-center gap-2 mb-0" id="importPreviewModalLabel">
                    <i class="bi bi-file-earmark-spreadsheet-fill text-indigo-400 text-base animate-pulse"></i> Import Data Preview
                </h5>
                <button type="button" class="text-slate-400 hover:text-white hover:bg-slate-900/60 rounded-lg p-1.5 focus:outline-hidden" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>
            <div class="modal-body p-6 bg-slate-900/20">
                <div id="previewContent" class="space-y-4">
                    <!-- Preview items will be injected here -->
                </div>
            </div>
            <div class="modal-footer px-6 py-4 bg-slate-950/40 border-t border-slate-800/80 flex justify-end">
                <button type="button" class="px-4 py-2 bg-slate-900/40 border border-slate-800/80 text-slate-300 hover:text-white font-semibold text-sm rounded-lg hover:bg-slate-900/80 cursor-pointer" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
