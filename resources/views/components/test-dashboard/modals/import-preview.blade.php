<x-ui.modal id="importPreviewModal" max-width="xl">
    <x-slot:title>
        <div class="flex items-center gap-2">
            <i class="bi bi-file-earmark-spreadsheet-fill text-indigo-400 text-base animate-pulse"></i> Import Data Preview
        </div>
    </x-slot:title>

    <div class="space-y-4" id="previewContent">
        <!-- Preview items will be injected here -->
    </div>

    <x-slot:footer>
        <button type="button" class="px-4 py-2 bg-slate-900/40 border border-slate-800/80 text-slate-300 hover:text-white font-semibold text-sm rounded-lg hover:bg-slate-900/80 cursor-pointer" x-on:click="$dispatch('close-modal', 'importPreviewModal')">Close</button>
    </x-slot:footer>
</x-ui.modal>
