<x-ui.modal id="importPreviewModal" max-width="xl">
    <x-slot:title>
        <div class="flex items-center gap-2">
            <i class="bi bi-file-earmark-spreadsheet-fill text-indigo-600 text-base"></i> Import Data Preview
        </div>
    </x-slot:title>

    <div class="space-y-4" id="previewContent">
        <!-- Preview items will be injected here -->
    </div>

    <x-slot:footer>
        <button type="button" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:text-slate-900 font-semibold text-sm rounded-lg hover:bg-slate-50 cursor-pointer" x-on:click="$dispatch('close-modal', 'importPreviewModal')">Close</button>
    </x-slot:footer>
</x-ui.modal>
