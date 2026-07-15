@props(['tests'])

<!-- 1-2-3 Wizard Flow -->
<div class="dash-panel p-5 mb-5">
    <div
        class="border-b border-slate-100 pb-4 mb-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2 tracking-tight mb-1">
                <i class="bi bi-cloud-arrow-up text-indigo-600 text-xl leading-none"></i> Import Questions
            </h3>
            <p class="text-sm text-slate-600 mb-0">Add SAT questions to a module from a file or editor. Preview catches
                missing fields before anything is saved.</p>
        </div>
        <button type="button" x-data x-on:click="$dispatch('open-modal', 'importGuideModal')"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 hover:text-indigo-700 rounded-lg text-xs font-bold transition-colors cursor-pointer border border-indigo-100/80 shadow-xs shrink-0">
            <i class="bi bi-info-circle text-sm leading-none"></i>
            <span>Import Guide</span>
        </button>
    </div>

    <div class="space-y-6">
        <!-- STEP 1 -->
        <div>
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 font-bold text-sm border border-indigo-100">1</span>
                <h4 class="text-base font-bold text-slate-800 tracking-tight">Choose where questions should go</h4>
            </div>
            <div class="pl-11 grid grid-cols-1 md:grid-cols-12 gap-5">
                <div class="md:col-span-8">
                    <label for="bulkQuestionModule" class="block text-xs font-bold text-slate-600 mb-1.5">Module <span
                            class="text-rose-500">*</span></label>
                    <select
                        class="w-full text-sm text-slate-800 bg-white border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 tom-select"
                        id="bulkQuestionModule" required>
                        <option value="">Search module to import into...</option>
                        @php
                            $hasModules = false;
                            foreach ($tests as $test) {
                                foreach ($test->sections as $section) {
                                    foreach ($section->modules as $module) {
                                        if (
                                            auth()->user()->role !== 'teacher' ||
                                            $module->created_by === auth()->id()
                                        ) {
                                            $hasModules = true;
                                            break 3;
                                        }
                                    }
                                }
                            }
                        @endphp
                        @if (!$hasModules)
                            <option value="" disabled>No data yet</option>
                        @endif
                        @foreach ($tests as $test)
                            @foreach ($test->sections as $section)
                                @foreach ($section->modules as $module)
                                    @if (auth()->user()->role !== 'teacher' || $module->created_by === auth()->id())
                                        <option value="{{ $module->id }}" data-section-type="{{ $section->type }}">
                                            {{ $test->title }} |
                                            {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod
                                            {{ $module->module_number }} ({{ $module->difficulty_level }})
                                        </option>
                                    @endif
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                    <div class="text-xs text-slate-500 mt-1.5">Select the test module that will receive these questions.
                    </div>
                </div>
                <div class="md:col-span-4">
                    <label for="bulkStartPosition" class="block text-xs font-bold text-slate-600 mb-1.5">Start at
                        question <span class="text-rose-500">*</span></label>
                    <input type="number"
                        class="w-full px-3 py-2 text-sm text-slate-800 bg-white border border-slate-200 rounded-lg placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                        id="bulkStartPosition" min="1" value="1" required>
                    <div class="text-xs text-slate-500 mt-1.5">Existing questions at this number or later move down
                        automatically.</div>
                </div>
            </div>
        </div>

        <hr class="border-slate-200 ml-11">
        <!-- STEP 2 -->
        <div x-data="{
            importTab: 'json',
            importTabs: ['json', 'csv', 'zip'],
            setImportTab(tab) { this.importTab = tab; },
            moveImportTab(delta) {
                const current = this.importTabs.indexOf(this.importTab);
                const next = (current + delta + this.importTabs.length) % this.importTabs.length;
                this.importTab = this.importTabs[next];
                this.$nextTick(() => document.getElementById(`import-${this.importTab}-tab`)?.focus());
            }
        }">
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 font-bold text-sm border border-indigo-100">2</span>
                <h4 class="text-base font-bold text-slate-800 tracking-tight">Choose an import method</h4>
            </div>
            <div class="pl-11">
                <ul class="flex flex-wrap gap-2 border-b border-slate-200 pb-3 mb-5" id="importMethodTabs"
                    role="tablist" @keydown.arrow-right.prevent="moveImportTab(1)"
                    @keydown.arrow-down.prevent="moveImportTab(1)" @keydown.arrow-left.prevent="moveImportTab(-1)"
                    @keydown.arrow-up.prevent="moveImportTab(-1)">
                    <li role="presentation">
                        <button id="import-json-tab"
                            class="rounded-lg px-4 py-2 font-semibold text-sm focus:outline-hidden transition-colors cursor-pointer"
                            :class="importTab === 'json' ? 'bg-indigo-600 text-white shadow-sm' :
                                'text-slate-650 hover:text-slate-900 hover:bg-slate-100'"
                            x-on:click="setImportTab('json')" type="button" role="tab" aria-controls="import-json"
                            :aria-selected="importTab === 'json' ? 'true' : 'false'"
                            :tabindex="importTab === 'json' ? '0' : '-1'">
                            <i class="bi bi-filetype-json mr-1.5 text-base leading-none"></i> Paste or upload
                        </button>
                    </li>
                    <li role="presentation">
                        <button id="import-csv-tab"
                            class="rounded-lg px-4 py-2 font-semibold text-sm focus:outline-hidden transition-colors cursor-pointer"
                            :class="importTab === 'csv' ? 'bg-indigo-600 text-white shadow-sm' :
                                'text-slate-650 hover:text-slate-900 hover:bg-slate-100'"
                            x-on:click="setImportTab('csv')" type="button" role="tab" aria-controls="import-csv"
                            :aria-selected="importTab === 'csv' ? 'true' : 'false'"
                            :tabindex="importTab === 'csv' ? '0' : '-1'">
                            <i class="bi bi-file-earmark-spreadsheet mr-1.5 text-base leading-none"></i> CSV
                        </button>
                    </li>
                    <li role="presentation">
                        <button id="import-zip-tab"
                            class="rounded-lg px-4 py-2 font-semibold text-sm focus:outline-hidden transition-colors cursor-pointer"
                            :class="importTab === 'zip' ? 'bg-indigo-600 text-white shadow-sm' :
                                'text-slate-650 hover:text-slate-900 hover:bg-slate-100'"
                            x-on:click="setImportTab('zip')" type="button" role="tab" aria-controls="import-zip"
                            :aria-selected="importTab === 'zip' ? 'true' : 'false'"
                            :tabindex="importTab === 'zip' ? '0' : '-1'">
                            <i class="bi bi-file-earmark-zip mr-1.5 text-base leading-none"></i> ZIP + images
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="importMethodContent">
                    <!-- JSON / Editor Tab -->
                    <div x-show="importTab === 'json'" id="import-json" role="tabpanel"
                        aria-labelledby="import-json-tab" :aria-hidden="importTab === 'json' ? 'false' : 'true'"
                        x-transition.opacity.duration.150ms style="display: none;">
                        <div class="flex items-center gap-2 mb-4 mt-2">
                            <span
                                class="flex items-center justify-center w-6 h-6 rounded-full bg-amber-50 text-amber-700 font-bold text-xs border border-amber-100">3</span>
                            <h5 class="text-sm font-bold text-slate-800 tracking-tight">Add question data</h5>
                        </div>

                        <p class="text-slate-500 text-sm mb-4 leading-relaxed">
                            Upload a JSON file or paste question data into the editor. The preview step checks
                            SAT-required fields before import.
                        </p>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            <div class="lg:col-span-5 flex flex-col justify-between">
                                <div>
                                    <label
                                        class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Import
                                        JSON File</label>
                                    <div
                                        class="file-dropzone border-2 border-dashed border-slate-200 rounded-xl p-6 text-center bg-slate-50 relative cursor-pointer hover:bg-slate-100/60 hover:border-indigo-500/50">
                                        <input type="file"
                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                            id="bulkJsonFile" accept=".json,application/json" style="z-index: 10;"
                                            aria-label="Upload JSON questions file">
                                        <i
                                            class="bi bi-filetype-json text-4xl text-slate-400 mb-2 block leading-none"></i>
                                        <span
                                            class="font-semibold block text-slate-700 text-sm mb-1 drag-instruction">Drag
                                            &amp; drop JSON here</span>
                                        <span class="text-slate-450 text-xs">or click to browse file</span>
                                        <div
                                            class="file-name-display mt-2.5 text-xs text-emerald-600 font-bold hidden">
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2 leading-normal">Selecting a JSON file parses
                                        it and populates its contents into the editor.</p>
                                </div>

                                <div class="mt-6">
                                    <h6 class="text-[10px] font-bold text-slate-400 mb-3 tracking-wide uppercase">
                                        Examples &amp; Templates</h6>
                                    <div class="space-y-2">
                                        <button type="button"
                                            class="w-full text-left px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-700 flex items-center font-semibold"
                                            id="bulkLoadExampleRwBtn">
                                            <i
                                                class="bi bi-plus-circle mr-2 text-indigo-600 text-base leading-none"></i>
                                            Insert R&amp;W Example
                                        </button>
                                        <button type="button"
                                            class="w-full text-left px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-700 flex items-center font-semibold"
                                            id="bulkLoadExampleMathBtn">
                                            <i
                                                class="bi bi-plus-circle mr-2 text-indigo-600 text-base leading-none"></i>
                                            Insert Math Example
                                        </button>
                                        <div class="flex gap-2 pt-1.5">
                                            <button type="button"
                                                class="flex-1 px-3 py-2 text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-100"
                                                id="bulkDownloadRwSampleBtn">R&amp;W Sample.json</button>
                                            <button type="button"
                                                class="flex-1 px-3 py-2 text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-100"
                                                id="bulkDownloadMathSampleBtn">Math Sample.json</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-7 flex flex-col">
                                <div class="flex justify-between items-center mb-2">
                                    <label for="bulkQuestionsJson"
                                        class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-0">Question
                                        data</label>
                                    <button type="button" class="text-xs font-bold text-rose-600 hover:text-rose-700"
                                        id="bulkClearEditorBtn">Clear Editor</button>
                                </div>
                                <textarea
                                    class="w-full px-3 py-2.5 text-sm text-slate-800 bg-white border border-slate-200 rounded-lg placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-mono"
                                    id="bulkQuestionsJson" rows="12" spellcheck="false" placeholder='{ "items": [ ... ] }'></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                            <button type="button"
                                class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:text-slate-900 rounded-lg hover:bg-slate-50 shadow-sm text-sm font-semibold flex items-center gap-1.5"
                                id="bulkPreviewBtn">
                                <i class="bi bi-eye text-base leading-none"></i> Preview
                            </button>
                            <button type="button"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg shadow-sm flex items-center gap-1.5"
                                id="bulkImportSubmitBtn">
                                <i class="bi bi-cloud-arrow-up text-base leading-none"></i> Import from Editor
                            </button>
                        </div>
                    </div>

                    <!-- CSV Tab -->
                    <div x-show="importTab === 'csv'" id="import-csv" role="tabpanel"
                        aria-labelledby="import-csv-tab" :aria-hidden="importTab === 'csv' ? 'false' : 'true'"
                        x-transition.opacity.duration.150ms style="display: none;">
                        <div class="flex items-center gap-2 mb-4 mt-2">
                            <span
                                class="flex items-center justify-center w-6 h-6 rounded-full bg-amber-50 text-amber-700 font-bold text-xs border border-amber-100">3</span>
                            <h5 class="text-sm font-bold text-slate-800 tracking-tight">Upload CSV</h5>
                        </div>
                        <p class="text-slate-500 text-sm mb-4 leading-relaxed">
                            Use CSV when your team prepares questions in Excel or Google Sheets. Download a template
                            when you need the exact column names.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">CSV
                                    File (.csv or .txt) <span class="text-rose-500">*</span></label>
                                <div
                                    class="file-dropzone border-2 border-dashed border-slate-200 rounded-xl p-8 text-center bg-slate-50 relative cursor-pointer hover:bg-slate-100/60 hover:border-indigo-500/50">
                                    <input type="file"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        id="bulkCsvFile" accept=".csv,.txt,text/csv,text/plain" style="z-index: 10;"
                                        aria-label="Upload CSV questions file">
                                    <i
                                        class="bi bi-file-earmark-spreadsheet text-4xl text-slate-400 mb-3 block leading-none"></i>
                                    <span class="font-semibold block text-slate-700 text-sm mb-1 drag-instruction">Drag
                                        &amp; drop CSV here</span>
                                    <span class="text-slate-450 text-xs">or click to browse file</span>
                                    <div class="file-name-display mt-2.5 text-xs text-emerald-600 font-bold hidden">
                                    </div>
                                </div>
                                <div class="mt-2 text-xs text-slate-500"><strong>Note on Scoring:</strong> Set the
                                    <code>is_pretest</code> column to <code>1</code> for trial items, or <code>0</code>
                                    for scored items.
                                </div>
                            </div>
                            <div class="flex flex-col justify-center gap-3">
                                <span class="text-sm font-bold text-slate-700">Need a structured template?</span>
                                <button type="button"
                                    class="w-fit px-4 py-2 text-sm bg-white border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-700 shadow-sm flex items-center gap-2 font-semibold"
                                    id="bulkDownloadRwSampleCsvBtn">
                                    <i class="bi bi-download text-indigo-600 text-base leading-none"></i> Download
                                    R&amp;W Template.csv
                                </button>
                                <button type="button"
                                    class="w-fit px-4 py-2 text-sm bg-white border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-700 shadow-sm flex items-center gap-2 font-semibold"
                                    id="bulkDownloadMathSampleCsvBtn">
                                    <i class="bi bi-download text-indigo-600 text-base leading-none"></i> Download Math
                                    Template.csv
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                            <button type="button"
                                class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:text-slate-900 rounded-lg hover:bg-slate-50 shadow-sm text-sm font-semibold flex items-center gap-1.5"
                                id="bulkCsvPreviewBtn">
                                <i class="bi bi-eye text-base leading-none"></i> Preview
                            </button>
                            <button type="button"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg shadow-sm flex items-center gap-1.5"
                                id="bulkCsvImportSubmitBtn">
                                <i class="bi bi-cloud-arrow-up text-base leading-none"></i> Import CSV
                            </button>
                        </div>
                    </div>

                    <!-- ZIP Tab -->
                    <div x-show="importTab === 'zip'" id="import-zip" role="tabpanel"
                        aria-labelledby="import-zip-tab" :aria-hidden="importTab === 'zip' ? 'false' : 'true'"
                        x-transition.opacity.duration.150ms style="display: none;">
                        <div class="flex items-center gap-2 mb-4 mt-2">
                            <span
                                class="flex items-center justify-center w-6 h-6 rounded-full bg-amber-50 text-amber-700 font-bold text-xs border border-amber-100">3</span>
                            <h5 class="text-sm font-bold text-slate-800 tracking-tight">Upload ZIP Package</h5>
                        </div>
                        <p class="text-slate-500 text-sm mb-4 leading-relaxed">
                            Upload a ZIP when your question data references image files. Include one JSON or CSV file
                            plus the images it uses.
                        </p>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-end">
                            <div class="lg:col-span-8">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">ZIP
                                    File <span class="text-rose-500">*</span></label>
                                <div
                                    class="file-dropzone border-2 border-dashed border-slate-200 rounded-xl p-8 text-center bg-slate-50 relative cursor-pointer hover:bg-slate-100/60 hover:border-indigo-500/50">
                                    <input type="file"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        id="bulkZipFile" accept=".zip" style="z-index: 10;"
                                        aria-label="Upload ZIP questions package">
                                    <i
                                        class="bi bi-file-earmark-zip text-4xl text-slate-400 mb-3 block leading-none"></i>
                                    <span class="font-semibold block text-slate-700 text-sm mb-1 drag-instruction">Drag
                                        &amp; drop ZIP here</span>
                                    <span class="text-slate-450 text-xs">or click to browse file</span>
                                    <div class="file-name-display mt-2.5 text-xs text-emerald-600 font-bold hidden">
                                    </div>
                                </div>

                                <!-- ZIP Progress Bar Container -->
                                <div id="zipUploadProgressContainer"
                                    class="hidden mt-4 bg-slate-50 border border-slate-200 rounded-xl p-4 shadow-inner">
                                    <div class="flex justify-between text-xs font-semibold text-slate-500 mb-2">
                                        <span>Uploading &amp; Unzipping Package...</span>
                                        <span id="zipUploadPercentage" class="text-indigo-600 font-bold">0%</span>
                                    </div>
                                    <div
                                        class="w-full bg-slate-200 rounded-full h-2 overflow-hidden border border-slate-350">
                                        <div id="zipUploadProgressBar"
                                            class="bg-indigo-600 h-2 rounded-full relative overflow-hidden"
                                            style="width: 0%">
                                            <div class="absolute inset-0 bg-white/20"
                                                style="background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent); background-size: 1rem 1rem; animation: progress-stripes 1s linear infinite;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-4">
                                <button type="button"
                                    class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm flex items-center justify-center gap-2"
                                    id="bulkZipImportBtn">
                                    <i class="bi bi-cloud-arrow-up text-lg leading-none"></i> Import ZIP Package
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Hướng dẫn Nhập liệu & AI Prompt -->
<x-ui.modal id="importGuideModal" maxWidth="5xl" title="Teacher Import Guide & AI Prompt">
    <div x-data="{
        activeTab: 'overview',
        copied: false,
        copyPrompt() {
            const el = document.getElementById('aiPromptTextarea');
            if (el) {
                navigator.clipboard.writeText(el.value || el.textContent).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                });
            }
        }
    }" class="flex flex-col md:flex-row gap-5 min-h-[400px] md:h-[620px]">

        <!-- Tab Navigation (Sidebar-style in Modal) -->
        <div
            class="w-full md:w-56 shrink-0 flex flex-col gap-1 border-b md:border-b-0 md:border-r border-slate-200 pb-4 md:pb-0 md:pr-4">
            <button type="button" @click="activeTab = 'overview'"
                :class="activeTab === 'overview' ? 'bg-indigo-50 text-indigo-700 font-bold' :
                    'text-slate-655 hover:bg-slate-50'"
                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 cursor-pointer border-0">
                <i class="bi bi-info-circle text-base"></i>
                <span>Quy trình Import</span>
            </button>
            <button type="button" @click="activeTab = 'formatting'"
                :class="activeTab === 'formatting' ? 'bg-indigo-50 text-indigo-700 font-bold' :
                    'text-slate-655 hover:bg-slate-50'"
                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 cursor-pointer border-0">
                <i class="bi bi-type-italic text-base"></i>
                <span>Quy tắc Định dạng</span>
            </button>
            <button type="button" @click="activeTab = 'specs'"
                :class="activeTab === 'specs' ? 'bg-indigo-50 text-indigo-700 font-bold' :
                    'text-slate-655 hover:bg-slate-50'"
                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 cursor-pointer border-0">
                <i class="bi bi-file-earmark-code text-base"></i>
                <span>Cấu trúc File (JSON/ZIP/CSV)</span>
            </button>
            <button type="button" @click="activeTab = 'prompt'"
                :class="activeTab === 'prompt' ? 'bg-indigo-50 text-indigo-700 font-bold' :
                    'text-slate-655 hover:bg-slate-50'"
                class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 cursor-pointer border-0">
                <i class="bi bi-robot text-base"></i>
                <span>AI Conversion Prompt</span>
            </button>
        </div>

        <!-- Tab Content -->
        <div class="flex-1 overflow-y-auto md:h-full pr-2 text-slate-700 text-sm leading-relaxed space-y-4">

            <!-- OVERVIEW TAB -->
            <div x-show="activeTab === 'overview'" class="space-y-4">
                <h4
                    class="text-base font-extrabold text-slate-800 border-b pb-1.5 flex items-center gap-2 m-0 font-sans">
                    <i class="bi bi-list-ol text-indigo-600"></i> Quy trình Import câu hỏi (3 bước)
                </h4>

                <div class="space-y-4 mt-2">
                    <div class="flex gap-3">
                        <span
                            class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 font-bold shrink-0 text-xs">1</span>
                        <div>
                            <strong class="text-slate-800 font-bold block">Chọn vị trí đích</strong>
                            <p class="text-xs text-slate-600 mt-1">Chọn đúng Module bài thi cần import (ví dụ: R&W
                                Module 1, Math Module 2). Nhập vị trí bắt đầu chèn câu hỏi. Hệ thống sẽ tự động dịch
                                chuyển các câu hỏi đứng sau vị trí này xuống dưới.</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <span
                            class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 font-bold shrink-0 text-xs">2</span>
                        <div>
                            <strong class="text-slate-800 font-bold block">Chọn phương thức tải dữ liệu</strong>
                            <ul class="list-disc pl-5 text-xs text-slate-600 space-y-1.5 mt-1">
                                <li><strong>JSON (Paste hoặc File)</strong>: Phù hợp nhất khi có dữ liệu thô đã chuyển
                                    đổi bằng AI. Dán trực tiếp hoặc tải file <code>.json</code>.</li>
                                <li><strong>CSV</strong>: Phù hợp khi soạn bằng Excel. Tải file mẫu <code>.csv</code> để
                                    nhập đúng tên cột.</li>
                                <li><strong>ZIP (Bao gồm ảnh)</strong>: Bắt buộc dùng khi câu hỏi có đồ thị hoặc hình
                                    vẽ. Đóng gói file JSON/CSV kèm thư mục hình ảnh.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <span
                            class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 font-bold shrink-0 text-xs">3</span>
                        <div>
                            <strong class="text-slate-800 font-bold block">Xem trước (Preview) &amp; Lưu kết
                                quả</strong>
                            <p class="text-xs text-slate-600 mt-1">Nhấp nút <strong>Preview</strong> để chạy trình kiểm
                                định (Validator). Các lỗi như sai tên domain, thiếu câu trả lời đúng, thiếu passage (đối
                                với R&W)... sẽ hiển thị dạng danh sách lỗi màu đỏ để giáo viên kịp thời chỉnh sửa trước
                                khi chính thức lưu vào hệ thống.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORMATTING TAB -->
            <div x-show="activeTab === 'formatting'" class="space-y-4" style="display: none;">
                <h4
                    class="text-base font-extrabold text-slate-800 border-b pb-1.5 flex items-center gap-2 m-0 font-sans">
                    <i class="bi bi-regex text-indigo-600"></i> Quy tắc định dạng câu hỏi
                </h4>

                <div class="bg-indigo-50/40 rounded-xl p-4 border border-indigo-100/60 space-y-4 mt-2">
                    <div class="space-y-1.5">
                        <strong
                            class="text-indigo-900 flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider">
                            <i class="bi bi-calculator"></i> 1. Công thức Toán &amp; Biến số (LaTeX)
                        </strong>
                        <p class="text-xs text-slate-700 leading-normal">
                            Mọi biểu thức toán, phương trình, biến số (ví dụ: $$x$$, $$y$$), phép nhân chia, góc độ,
                            phân số đều phải được biểu diễn bằng LaTeX và bọc trong <strong>hai dấu đô-la</strong>
                            <code>$$...$$</code> (bao gồm cả dạng viết nội dòng).
                        </p>
                        <p class="text-xs text-slate-700 leading-normal font-bold">
                            Ràng buộc LaTeX quan trọng:
                        </p>
                        <ul class="list-disc pl-5 text-xs text-slate-600 space-y-1 mt-1">
                            <li><strong>Phân số (\frac)</strong>: Bắt buộc phải thêm <code>\displaystyle</code> ở đầu công thức. Ví dụ: <code class="text-rose-600">$$\displaystyle \frac{1}{2}$$</code> thay vì <code class="text-rose-600">$$\frac{1}{2}$$</code>.</li>
                            <li><strong>Ký hiệu Hy Lạp &amp; Đặc biệt</strong>: Mọi ký hiệu như đô-la ($), phần trăm (%), pi (\pi), omega (\omega), theta (\theta), lớn hơn hoặc bằng (\ge), nhỏ hơn hoặc bằng (\le) đều bắt buộc phải dùng LaTeX, không dùng text trần.</li>
                            <li><strong>Escape dấu gạch chéo ngược (\) trong JSON</strong>: Khi viết trong JSON, bắt buộc phải viết đúp thành hai dấu gạch chéo ngược <code>\\</code> để tránh lỗi parser. Ví dụ: <code>"$$ \\\\$5 $$"</code>, <code>"$$ 50\\\\% $$"</code>, <code>"$$ \\\\pi $$"</code>, <code>"$$ \\\\displaystyle \\\\frac{1}{2} $$"</code>.</li>
                        </ul>
                        <div
                            class="bg-white/95 rounded-lg p-2.5 font-mono text-xs border border-indigo-100/50 mt-1.5 text-slate-800">
                            Ví dụ thực tế:<br>
                            - Phân số hiển thị rộng: <code class="text-rose-600">$$\displaystyle \frac{1}{2}$$</code><br>
                            - Số mũ: <code class="text-rose-600">$$x^2 + 5x = 6$$</code><br>
                            - Độ: <code class="text-rose-600">$$180^\circ$$</code>
                        </div>
                    </div>

                    <div class="h-px bg-indigo-100/60"></div>

                    <div class="space-y-1.5">
                        <strong
                            class="text-indigo-900 flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider">
                            <i class="bi bi-grid-3x3"></i> 2. Bảng biểu dữ liệu (HTML Table)
                        </strong>
                        <p class="text-xs text-slate-700 leading-normal">
                            Khi cần tạo bảng dữ liệu có thể đọc và quét được (thay vì dùng ảnh), hãy mã hóa dưới dạng
                            bảng HTML <code>&lt;table&gt;</code> với class <code>min-w-full divide-y
                                divide-slate-200</code>.
                        </p>
                    </div>

                    <div class="h-px bg-indigo-100/60"></div>

                    <div class="space-y-1.5">
                        <strong
                            class="text-indigo-900 flex items-center gap-1.5 font-bold text-xs uppercase tracking-wider">
                            <i class="bi bi-image"></i> 3. Ký hiệu Hình ảnh / Đồ thị (Media Placeholder)
                        </strong>
                        <p class="text-xs text-slate-700 leading-normal">
                            Nếu đề bài có hình vẽ đồ thị, biểu đồ hình học hoặc ảnh bảng biểu không thể chuyển sang
                            text:
                            <br>Hãy sử dụng placeholder có cấu trúc: <code
                                class="text-rose-600 font-bold">[Media:q##_mota.ext]</code> đặt tại đúng vị trí ảnh
                            xuất hiện.
                            <br><em>Ví dụ:</em> <code>[Media:q05_scatterplot.png]</code> hoặc
                            <code>[Media:q12_triangle.jpg]</code>.
                            <br>Tải lên bằng phương thức **ZIP** chứa các file ảnh tương ứng này.
                        </p>
                    </div>
                </div>
            </div>

            <!-- SPECS TAB -->
            <div x-show="activeTab === 'specs'" class="space-y-4" style="display: none;">
                <h4
                    class="text-base font-extrabold text-slate-800 border-b pb-1.5 flex items-center gap-2 m-0 font-sans">
                    <i class="bi bi-file-earmark-code text-indigo-600"></i> Cấu trúc File &amp; Schema
                </h4>

                <div class="space-y-3 mt-2">
                    <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/50">
                        <strong class="text-slate-800 block text-xs font-bold uppercase tracking-wider mb-1"><i
                                class="bi bi-file-earmark-zip text-indigo-600 mr-1"></i> Gói ZIP chứa hình ảnh</strong>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Nếu import câu hỏi có hình ảnh, file ZIP phải chứa:
                            <br>1. Một file dữ liệu (ví dụ: <code>questions.json</code> hoặc <code>questions.csv</code>)
                            ở thư mục gốc.
                            <br>2. Các file ảnh đồ thị, nằm cùng thư mục hoặc trong thư mục con <code>images/</code>.
                        </p>
                    </div>

                    <!-- JSON SECTION -->
                    <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/50 space-y-2">
                        <strong class="text-slate-800 block text-xs font-bold uppercase tracking-wider mb-1"><i
                                class="bi bi-filetype-json text-indigo-600 mr-1"></i> Ví dụ JSON hoàn chỉnh (Đầy đủ
                            thuộc tính)</strong>
                        <p class="text-[11px] text-slate-500 leading-normal mt-0">Mẫu JSON dưới đây biểu diễn 1 câu R&W
                            (Multiple Choice với passage, LaTeX, giải thích chi tiết), 1 câu Toán MCQ có bảng HTML, và 1
                            câu Toán SPR chứa đồ thị hình ảnh:</p>
                        <div
                            class="bg-white rounded-lg p-2.5 font-mono text-[11px] leading-normal border border-slate-200 max-h-56 overflow-y-auto">
                            <pre class="m-0 text-slate-800">{
  "items": [
    {
      "question_number": 1,
      "question_type": "multiple_choice",
      "passage": "Scientists recently analyzed the atmospheric composition of exoplanet Kepler-186f. They discovered trace water vapor...",
      "stem": "Which choice best states the main idea of the text?",
      "difficulty": "medium",
      "skill_domain": "craft_and_structure",
      "skill_subdomain": "text_structure_and_purpose",
      "choices": {
        "A": "Kepler-186f has oceans of stable liquid water.",
        "B": "Recent analyses suggest water vapor is present, though ocean stability remains debated.",
        "C": "Low pressure completely rules out water on the exoplanet.",
        "D": "Earth and Kepler-186f have identical atmospheric profiles."
      },
      "correct_choice": "B",
      "explanation": "The text discusses water vapor and pressure counter-arguments.",
      "rationale_a": "Incorrect because ocean stability is disputed.",
      "rationale_b": "Correct because it captures the main idea.",
      "rationale_c": "Incorrect because 'completely rules out' is too extreme.",
      "rationale_d": "Incorrect because profiles are different.",
      "strategy_tip": "Identify both findings and limitations.",
      "common_mistakes": "Ignoring critics' reservations in choice A.",
      "is_pretest": false,
      "calculator_allowed": true,
      "external_id": "RW-01"
    },
    {
      "question_number": 2,
      "question_type": "multiple_choice",
      "stem": "Determine the slope of the linear relationship below:\n\n<table class=\"min-w-full divide-y divide-slate-200\"><thead><tr><th>$$x$$</th><th>$$y$$</th></tr></thead><tbody><tr><td>$$1$$</td><td>$$5$$</td></tr><tr><td>$$3$$</td><td>$$11$$</td></tr></tbody></table>",
      "difficulty": "easy",
      "skill_domain": "algebra",
      "skill_subdomain": "linear_functions",
      "choices": {
        "A": "$$2$$",
        "B": "$$3$$",
        "C": "$$5$$",
        "D": "$$6$$"
      },
      "correct_choice": "B",
      "explanation": "Slope formula gives $$(11 - 5)/(3 - 1) = 3$$.",
      "is_pretest": false,
      "calculator_allowed": true
    },
    {
      "question_number": 3,
      "question_type": "student_produced_response",
      "stem": "In the triangle shown, what is the value of $$\\tan(\\theta)$$?\n\n[Media:q03_triangle.png]",
      "difficulty": "hard",
      "skill_domain": "geometry_trigonometry",
      "skill_subdomain": "right_triangles_and_trigonometry",
      "spr_correct_answers": ["4/3", "1.33", "1.333"],
      "spr_hint": "Enter your answer as a fraction or decimal.",
      "explanation": "$$\\tan(\\theta) = \\text{Opposite}/\\text{Adjacent} = 4/3$$.",
      "is_pretest": false,
      "calculator_allowed": true
    }
  ]
}</pre>
                        </div>
                    </div>

                    <!-- CSV SECTION -->
                    <div class="border border-slate-200 rounded-xl p-3 bg-slate-50/50 space-y-2">
                        <strong class="text-slate-800 block text-xs font-bold uppercase tracking-wider mb-1"><i
                                class="bi bi-file-earmark-spreadsheet text-indigo-600 mr-1"></i> Định dạng CSV mẫu (Raw
                            CSV Text)</strong>
                        <p class="text-[11px] text-slate-550 leading-normal mt-0">Bạn có thể sao chép đoạn văn bản thô
                            dưới đây, lưu vào file dạng <code>.csv</code> (mã hóa UTF-8) để mở trực tiếp trong Excel
                            hoặc Google Sheets:</p>
                        <div
                            class="bg-white rounded-lg p-2.5 font-mono text-[10px] leading-normal border border-slate-200 max-h-48 overflow-y-auto">
                            <pre class="m-0 text-slate-800">question_type,difficulty,skill_domain,skill_subdomain,stem,passage_content,passage_genre,choice_a_content,choice_b_content,choice_c_content,choice_d_content,correct_choice,spr_correct_answers,spr_hint,explanation,strategy_tip,common_mistakes,is_pretest,calculator_allowed,external_id
multiple_choice,medium,craft_and_structure,words_in_context,"As used in the text, what does ""vital"" mean?","Notes were vital to the team's success.",natural_science,useless,essential,optional,secondary,B,,,Notes were essential.,Context clues.,Secondary meaning trap,0,1,RW-CSV-01
student_produced_response,hard,algebra,linear_equations_in_one_variable,"If $$3x - 5 = 10$$, what is $$x$$?",,,,,,,,5,Enter integer.,$$3x = 15 \Rightarrow x = 5$$.,Isolate variable.,Arithmetic error.,0,1,M-CSV-02</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PROMPT TAB -->
            <div x-show="activeTab === 'prompt'" style="display: none;">
                <div class="space-y-3 flex flex-col h-full">
                    <div class="flex justify-between items-center border-b pb-1.5">
                        <h4 class="text-base font-extrabold text-slate-800 flex items-center gap-2 m-0 font-sans">
                            <i class="bi bi-robot text-indigo-600"></i> AI Conversion Prompt
                        </h4>
                        <button type="button" @click="copyPrompt()"
                            class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition-colors cursor-pointer border-0 shadow-xs">
                            <i :class="copied ? 'bi bi-check-lg' : 'bi bi-copy'"></i>
                            <span x-text="copied ? 'Đã sao chép!' : 'Sao chép Prompt'"></span>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed mt-0">
                        Hãy copy prompt bên dưới và dán vào ChatGPT hoặc Claude cùng với file OCR/text đề thi của bạn. AI sẽ
                        chuyển đổi dữ liệu thô thành file JSON cực kỳ chính xác để import.
                    </p>
                    <div class="flex-1 relative">
                        <textarea id="aiPromptTextarea" readonly
                            class="w-full h-80 p-3 bg-slate-50 border border-slate-200 rounded-xl font-mono text-[11px] leading-relaxed text-slate-700 focus:outline-hidden"
                            style="resize: none;">Role:
You are a data conversion expert specialized in Digital SAT exam preparation materials. Your task is to extract questions from provided Question PDF/OCR/screenshots and merge them with correct answers and full explanations from provided Answer Explanation PDF/OCR/screenshots into import-ready JSON for a bulk ZIP/JSON question importer.

Source Data Priority:
- If any generated field can be directly determined from the source material, use the source material instead of inferring.
- Only infer fields when the source does not explicitly provide them.
- This applies to question_number, passage, stem, choices, correct answer, explanation, difficulty, skill_domain, skill_subdomain, media references, tables, and any other generated parameter.
- If the source explicitly labels a domain, skill, difficulty, answer, or explanation, preserve that value after converting it to the required importer key format.

Output Requirement:
Output ONLY valid JSON. Do not include markdown code blocks, comments, preambles, explanations, or conversational text.

Output Shape:
Return a JSON array of question objects under the key "items". For example:
{
  "items": [
    // question objects here
  ]
}

Each question object must follow this schema.

For multiple-choice questions:
{
  "question_number": 1,
  "question_type": "multiple_choice",
  "passage": "Required for Reading and Writing only. Omit for Math.",
  "stem": "The actual question or instruction.",
  "difficulty": "easy",
  "skill_domain": "information_and_ideas",
  "skill_subdomain": "central_ideas_and_details",
  "choices": {
    "A": "Choice A text",
    "B": "Choice B text",
    "C": "Choice C text",
    "D": "Choice D text"
  },
  "correct_choice": "B",
  "explanation": "Full explanation from the answer document.",
  "rationale_a": "Why choice A is wrong or right, if available.",
  "rationale_b": "Why choice B is wrong or right, if available.",
  "rationale_c": "Why choice C is wrong or right, if available.",
  "rationale_d": "Why choice D is wrong or right, if available.",
  "strategy_tip": "Optional strategy tip.",
  "common_mistakes": "Optional common mistake explanation.",
  "is_pretest": false,
  "calculator_allowed": true,
  "external_id": "Optional unique string identifier"
}

For Math student-produced response / grid-in questions:
{
  "question_number": 1,
  "question_type": "student_produced_response",
  "stem": "The actual question or instruction.",
  "difficulty": "medium",
  "skill_domain": "algebra",
  "skill_subdomain": "linear_equations_in_one_variable",
  "spr_correct_answers": ["5", "5.0"],
  "spr_hint": "Enter a number.",
  "explanation": "Full explanation from the answer document.",
  "strategy_tip": "Optional strategy tip.",
  "common_mistakes": "Optional common mistake explanation.",
  "is_pretest": false,
  "calculator_allowed": true,
  "external_id": "Optional unique string identifier"
}

Field Rules:
- question_number: integer from the source material, restart when moving into another module.
- question_type: use "multiple_choice" for A/B/C/D questions; use "student_produced_response" for Math grid-ins.
- passage: required for Reading and Writing questions; omit for Math questions.
- stem: the question text or instruction.
- difficulty: infer as "easy", "medium", or "hard".
- skill_domain: choose only from the allowed section-specific values:
  * Reading & Writing: information_and_ideas, craft_and_structure, expression_of_ideas, standard_english_conventions
  * Math: algebra, advanced_math, problem_solving_data_analysis, geometry_trigonometry
- skill_subdomain: choose only from the allowed subdomain values corresponding to domains (e.g., words_in_context, central_ideas_and_details, linear_functions, right_triangles_and_trigonometry, etc.).
- choices: required for multiple_choice; object with exactly keys "A", "B", "C", "D".
- correct_choice: required for multiple_choice; one of "A", "B", "C", "D".
- spr_correct_answers: required for student_produced_response; array of accepted answers as strings.
- spr_hint: optional for student_produced_response; include if useful.
- For multiple_choice, omit spr_correct_answers and spr_hint.
- For student_produced_response, omit passage, choices, correct_choice, and rationales.
- explanation: include the full explanation from the answer explanation document.
- rationale_a/rationale_b/rationale_c/rationale_d: include only if the explanation explicitly discusses individual choices. If not available, omit these fields.
- strategy_tip/common_mistakes: extract tips and traps from the explanation document if explicitly noted or easily inferred.

Formatting Rules:
- Use LaTeX for all math formulas, variables, measurements, symbols, and expressions.
- Always wrap math in $$...$$, even inline math. Example: $$x^2$$, $$\displaystyle \frac{1}{2}$$, $$18^\circ$$, $$y = mx + b$$.
- If a LaTeX formula contains a fraction (\frac), you MUST prepend it with \displaystyle inside the formula. Example: $$\displaystyle \frac{a}{b}$$ instead of $$\frac{a}{b}$$.
- Never use plain text for math variables, greek letters, or special symbols (e.g., $, %, \pi, \omega, \theta, \le, \ge). They must be formatted as LaTeX.
- In the JSON output, escape all backslashes as double backslashes (\\). For example:
  * For a dollar sign $, write: $$ \\$5 $$ (renders in JSON string as "$$ \\\\$5 $$").
  * For a percentage sign %, write: $$ 50\\% $$ (renders in JSON string as "$$ 50\\\\% $$").
  * For a fraction, write: $$ \\displaystyle \\frac{a}{b} $$ (renders in JSON string as "$$ \\\\displaystyle \\\\frac{a}{b} $$").
  * For greek letters, write: $$ \\pi $$ (renders in JSON string as "$$ \\\\pi $$").
- Preserve original paragraph breaks using \n\n inside strings.
- Preserve single line breaks using \n inside strings.
- Use <u>...</u> for underlined text.
- Use Markdown **bold** and *italic* for bold and italic text.
- If a passage, stem, choice, or explanation contains a readable table, encode it as an HTML <table> with valid child tags. Add class "min-w-full divide-y divide-slate-200" to the <table>.
- If a question contains a graph, diagram, geometric figure, image, chart, or unreadable table image that cannot be represented as HTML, replace that visual with a unique media placeholder in the exact format [Media:q##_description.png], where ## is the question number (padded with 0 if single digit). Examples: [Media:q05_graph.png], [Media:q12_triangle.png], [Media:q18_scatterplot.png]. Do not reuse generic names like [Media:media.png]. Place the placeholder exactly where the image appears.

Data Source:
[Paste raw question text/OCR/screenshots here, followed by answers and explanations]</textarea>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <x-slot:footer>
        <button type="button" x-on:click="$dispatch('close-modal', 'importGuideModal')"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-semibold transition-colors cursor-pointer border border-slate-250">
            Đóng
        </button>
    </x-slot:footer>
</x-ui.modal>
