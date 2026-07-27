@props(['tests'])

<!-- 1-2-3 Wizard Flow -->
<div class="dash-panel p-5 mb-5">
    <div
        class="border-b border-slate-100 pb-4 mb-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2 tracking-tight mb-1">
                <x-ui.icon name="cloud-arrow-up" class="w-5 h-5 text-brand leading-none" /> Import Questions
            </h3>
            <p class="text-sm text-slate-600 mb-0">Add SAT questions to a module from a file or editor. Preview catches
                missing fields before anything is saved.</p>
        </div>
        <button type="button" x-data x-on:click="$dispatch('open-modal', 'importGuideModal')"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[var(--color-brand-soft)] hover:bg-[var(--color-brand-soft)] text-brand hover:text-brand rounded-lg text-xs font-bold transition-colors cursor-pointer border border-brand/20 shadow-xs shrink-0">
            <x-ui.icon name="info-circle" class="w-3.5 h-3.5 leading-none" />
            <span>Import Guide</span>
        </button>
    </div>

    <div class="space-y-6">
        <!-- STEP 1 -->
        <div>
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="flex items-center justify-center w-8 h-8 rounded-full bg-[var(--color-brand-soft)] text-brand font-bold text-sm border border-brand/20">1</span>
                <h4 class="text-base font-bold text-slate-800 tracking-tight">Choose where questions should go</h4>
            </div>
            <div class="pl-11 grid grid-cols-1 md:grid-cols-12 gap-5">
                <div class="md:col-span-8">
                    <label for="bulkQuestionModule" class="block text-xs font-bold text-slate-600 mb-1.5">Module <span
                            class="text-rose-500">*</span></label>
                    <select
                        class="w-full text-sm text-slate-800 bg-white border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-brand/20 focus:border-brand tom-select"
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
                        class="w-full px-3 py-2 text-sm text-slate-800 bg-white border border-slate-200 rounded-lg placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-brand/20 focus:border-brand"
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
                    class="flex items-center justify-center w-8 h-8 rounded-full bg-[var(--color-brand-soft)] text-brand font-bold text-sm border border-brand/20">2</span>
                <h4 class="text-base font-bold text-slate-800 tracking-tight">Choose an import method</h4>
            </div>
            <div class="pl-11">
                <ul class="flex flex-wrap gap-2 border-b border-slate-200 pb-3 mb-5" id="importMethodTabs"
                    role="tablist" @keydown.arrow-right.prevent="moveImportTab(1)"
                    @keydown.arrow-down.prevent="moveImportTab(1)" @keydown.arrow-left.prevent="moveImportTab(-1)"
                    @keydown.arrow-up.prevent="moveImportTab(-1)">
                    <li role="presentation">
                        <button id="import-json-tab"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 font-semibold text-sm focus:outline-hidden transition-colors cursor-pointer"
                            :class="importTab === 'json' ? 'bg-brand text-white shadow-xs' :
                                'text-slate-650 hover:text-slate-900 hover:bg-slate-100'"
                            x-on:click="setImportTab('json')" type="button" role="tab" aria-controls="import-json"
                            :aria-selected="importTab === 'json' ? 'true' : 'false'"
                            :tabindex="importTab === 'json' ? '0' : '-1'">
                            <x-ui.icon name="filetype-json" class="w-4 h-4 shrink-0" />
                            <span>Paste or upload</span>
                        </button>
                    </li>
                    <li role="presentation">
                        <button id="import-csv-tab"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 font-semibold text-sm focus:outline-hidden transition-colors cursor-pointer"
                            :class="importTab === 'csv' ? 'bg-brand text-white shadow-xs' :
                                'text-slate-650 hover:text-slate-900 hover:bg-slate-100'"
                            x-on:click="setImportTab('csv')" type="button" role="tab" aria-controls="import-csv"
                            :aria-selected="importTab === 'csv' ? 'true' : 'false'"
                            :tabindex="importTab === 'csv' ? '0' : '-1'">
                            <x-ui.icon name="file-earmark-spreadsheet" class="w-4 h-4 shrink-0" />
                            <span>CSV</span>
                        </button>
                    </li>
                    <li role="presentation">
                        <button id="import-zip-tab"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 font-semibold text-sm focus:outline-hidden transition-colors cursor-pointer"
                            :class="importTab === 'zip' ? 'bg-brand text-white shadow-xs' :
                                'text-slate-650 hover:text-slate-900 hover:bg-slate-100'"
                            x-on:click="setImportTab('zip')" type="button" role="tab" aria-controls="import-zip"
                            :aria-selected="importTab === 'zip' ? 'true' : 'false'"
                            :tabindex="importTab === 'zip' ? '0' : '-1'">
                            <x-ui.icon name="file-earmark-zip" class="w-4 h-4 shrink-0" />
                            <span>ZIP + images</span>
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
                                        class="file-dropzone border-2 border-dashed border-slate-200 rounded-xl p-6 text-center bg-slate-50 relative cursor-pointer hover:bg-slate-100/60 hover:border-brand/50">
                                        <input type="file"
                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                            id="bulkJsonFile" accept=".json,application/json" style="z-index: 10;"
                                            aria-label="Upload JSON questions file">
                                        <x-ui.icon name="filetype-json"
                                            class="w-10 h-10 text-slate-400 mx-auto mb-2 block" />
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
                                            <x-ui.icon name="plus-circle"
                                                class="w-4 h-4 mr-2 text-brand leading-none" />
                                            Insert R&amp;W Example
                                        </button>
                                        <button type="button"
                                            class="w-full text-left px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-700 flex items-center font-semibold"
                                            id="bulkLoadExampleMathBtn">
                                            <x-ui.icon name="plus-circle"
                                                class="w-4 h-4 mr-2 text-brand leading-none" />
                                            Insert Math Example
                                        </button>
                                        <div class="flex gap-2 pt-1.5">
                                            <button type="button"
                                                class="flex-1 px-3 py-2 text-xs font-semibold text-brand bg-[var(--color-brand-soft)] hover:bg-[var(--color-brand-soft)] rounded-lg border border-brand/20"
                                                id="bulkDownloadRwSampleBtn">R&amp;W Sample.json</button>
                                            <button type="button"
                                                class="flex-1 px-3 py-2 text-xs font-semibold text-brand bg-[var(--color-brand-soft)] hover:bg-[var(--color-brand-soft)] rounded-lg border border-brand/20"
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
                                    class="w-full px-3 py-2.5 text-sm text-slate-800 bg-white border border-slate-200 rounded-lg placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-brand/20 focus:border-brand font-mono"
                                    id="bulkQuestionsJson" rows="12" spellcheck="false" placeholder='{ "items": [ ... ] }'></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                            <button type="button"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:text-slate-900 rounded-lg hover:bg-slate-50 shadow-xs text-sm font-semibold cursor-pointer"
                                id="bulkPreviewBtn">
                                <x-ui.icon name="eye" class="w-4 h-4 shrink-0" />
                                <span>Preview</span>
                            </button>
                            <button type="button"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2 bg-brand hover:bg-brand-hover text-white font-semibold text-sm rounded-lg shadow-xs cursor-pointer"
                                id="bulkImportSubmitBtn">
                                <x-ui.icon name="cloud-arrow-up" class="w-4 h-4 shrink-0" />
                                <span>Import from Editor</span>
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
                                    class="file-dropzone border-2 border-dashed border-slate-200 rounded-xl p-8 text-center bg-slate-50 relative cursor-pointer hover:bg-slate-100/60 hover:border-brand/50">
                                    <input type="file"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        id="bulkCsvFile" accept=".csv,.txt,text/csv,text/plain" style="z-index: 10;"
                                        aria-label="Upload CSV questions file">
                                    <x-ui.icon name="file-earmark-spreadsheet"
                                        class="w-10 h-10 text-slate-400 mx-auto mb-3 block" />
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
                                    class="inline-flex items-center gap-2 w-fit px-4 py-2 text-sm bg-white border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-700 shadow-xs font-semibold cursor-pointer"
                                    id="bulkDownloadRwSampleCsvBtn">
                                    <x-ui.icon name="download" class="w-4 h-4 text-brand shrink-0" />
                                    <span>Download R&amp;W Template.csv</span>
                                </button>
                                <button type="button"
                                    class="inline-flex items-center gap-2 w-fit px-4 py-2 text-sm bg-white border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-700 shadow-xs font-semibold cursor-pointer"
                                    id="bulkDownloadMathSampleCsvBtn">
                                    <x-ui.icon name="download" class="w-4 h-4 text-brand shrink-0" />
                                    <span>Download Math Template.csv</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                            <button type="button"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:text-slate-900 rounded-lg hover:bg-slate-50 shadow-xs text-sm font-semibold cursor-pointer"
                                id="bulkCsvPreviewBtn">
                                <x-ui.icon name="eye" class="w-4 h-4 shrink-0" />
                                <span>Preview</span>
                            </button>
                            <button type="button"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2 bg-brand hover:bg-brand-hover text-white font-semibold text-sm rounded-lg shadow-xs cursor-pointer"
                                id="bulkCsvImportSubmitBtn">
                                <x-ui.icon name="cloud-arrow-up" class="w-4 h-4 shrink-0" />
                                <span>Import CSV</span>
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

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">ZIP
                                    File <span class="text-rose-500">*</span></label>
                                <div
                                    class="file-dropzone border-2 border-dashed border-slate-200 rounded-xl p-8 text-center bg-slate-50 relative cursor-pointer hover:bg-slate-100/60 hover:border-brand/50">
                                    <input type="file"
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        id="bulkZipFile" accept=".zip" style="z-index: 10;"
                                        aria-label="Upload ZIP questions package">
                                    <x-ui.icon name="file-earmark-zip"
                                        class="w-10 h-10 text-slate-400 mx-auto mb-3 block" />
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
                                        <span id="zipUploadPercentage" class="text-brand font-bold">0%</span>
                                    </div>
                                    <div
                                        class="w-full bg-slate-200 rounded-full h-2 overflow-hidden border border-slate-350">
                                        <div id="zipUploadProgressBar"
                                            class="bg-brand h-2 rounded-full relative overflow-hidden"
                                            style="width: 0%">
                                            <div class="absolute inset-0 bg-white/20"
                                                style="background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent); background-size: 1rem 1rem; animation: progress-stripes 1s linear infinite;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col justify-center gap-3">
                                <span class="text-sm font-bold text-slate-700">ZIP Package Requirements</span>
                                <ul class="text-xs text-slate-600 space-y-2 list-disc pl-4 leading-relaxed">
                                    <li>Contains exactly one <code>questions.json</code> or <code>questions.csv</code> file.</li>
                                    <li>Contains all referenced images in the root or subfolders.</li>
                                    <li>Images referenced in stems or choices are automatically mapped.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                            <button type="button"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2 bg-brand hover:bg-brand-hover text-white font-semibold text-sm rounded-lg shadow-xs cursor-pointer"
                                id="bulkZipImportBtn">
                                <x-ui.icon name="cloud-arrow-up" class="w-4 h-4 shrink-0" />
                                <span>Import ZIP Package</span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<x-admin.test-builder.questions.import-guide-modal />
