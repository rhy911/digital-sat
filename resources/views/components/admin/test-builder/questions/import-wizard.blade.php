@props(['tests'])

<!-- 1-2-3 Wizard Flow -->
<div class="dash-panel p-5 mb-5">
    <div class="border-b border-slate-100 pb-4 mb-5">
        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2 tracking-tight mb-1">
            <i class="bi bi-cloud-arrow-up text-indigo-600 text-xl leading-none"></i> Import Questions
        </h3>
        <p class="text-sm text-slate-600 mb-0">Add SAT questions to a module from a file or editor. Preview catches
            missing fields before anything is saved.</p>
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
                                    for scored items.</div>
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
