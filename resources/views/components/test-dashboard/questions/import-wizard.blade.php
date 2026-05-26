@props(['tests'])

<!-- 1-2-3 Wizard Flow -->
<div class="glass-panel rounded-2xl p-6 mb-8 shadow-2xl">
    <h3
        class="text-lg font-bold text-white mb-6 flex items-center gap-2 border-b border-slate-800/80 pb-4 tracking-tight">
        <i class="bi bi-cloud-arrow-up text-indigo-400 text-xl leading-none animate-pulse"></i> Import Questions Wizard
    </h3>

    <div class="space-y-8">
        <!-- STEP 1 -->
        <div>
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-950/40 text-indigo-300 font-bold text-sm border border-indigo-900/60 shadow-[0_0_12px_rgba(99,102,241,0.2)]">1</span>
                <h4 class="text-base font-bold text-white tracking-tight">Select Target Module</h4>
            </div>
            <div class="pl-11 grid grid-cols-1 md:grid-cols-12 gap-5">
                <div class="md:col-span-8">
                    <label for="bulkQuestionModule" class="block text-sm font-semibold text-slate-300 mb-1.5">Target
                        Module <span class="text-rose-500">*</span></label>
                    <select
                        class="w-full text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 tom-select"
                        id="bulkQuestionModule" required>
                        <option value="">Search module to import into...</option>
                        @foreach ($tests as $test)
                            @foreach ($test->sections as $section)
                                @foreach ($section->modules as $module)
                                    <option value="{{ $module->id }}" data-section-type="{{ $section->type }}">
                                        {{ $test->title }} | {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod
                                        {{ $module->module_number }} ({{ $module->difficulty_level }})
                                    </option>
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label for="bulkStartPosition" class="block text-sm font-semibold text-slate-300 mb-1.5">Starting
                        Position <span class="text-rose-500">*</span></label>
                    <input type="number"
                    class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                    id="bulkStartPosition" min="1" value="1" required>
                    <div class="text-xs text-slate-500 mt-1.5">Existing questions will be shifted down.</div>
                    </div>
                    </div>
                    </div>

                    <hr class="border-slate-800/60 ml-11">
        <!-- STEP 2 -->
        <div x-data="{ importTab: 'json' }">
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-950/40 text-indigo-300 font-bold text-sm border border-indigo-900/60 shadow-[0_0_12px_rgba(99,102,241,0.2)]">2</span>
                <h3 class="text-base font-bold text-white tracking-tight">Choose Import Method</h4>
            </div>
            <div class="pl-11">
                <ul class="flex flex-wrap gap-2 border-b border-slate-800/80 pb-3 mb-5" id="importMethodTabs"
                    role="tablist">
                    <li role="presentation">
                        <button
                            class="rounded-lg px-4 py-2 font-semibold text-sm focus:outline-hidden transition-colors cursor-pointer"
                            :class="importTab === 'json' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                            x-on:click="importTab = 'json'" type="button" role="tab">
                            <i class="bi bi-filetype-json mr-1.5 text-base leading-none"></i> JSON / Editor
                        </button>
                    </li>
                    <li role="presentation">
                        <button
                            class="rounded-lg px-4 py-2 font-semibold text-sm focus:outline-hidden transition-colors cursor-pointer"
                            :class="importTab === 'csv' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                            x-on:click="importTab = 'csv'" type="button" role="tab">
                            <i class="bi bi-file-earmark-spreadsheet mr-1.5 text-base leading-none"></i> CSV
                        </button>
                    </li>
                    <li role="presentation">
                        <button
                            class="rounded-lg px-4 py-2 font-semibold text-sm focus:outline-hidden transition-colors cursor-pointer"
                            :class="importTab === 'zip' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                            x-on:click="importTab = 'zip'" type="button" role="tab">
                            <i class="bi bi-file-earmark-zip mr-1.5 text-base leading-none"></i> ZIP (with Images)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="importMethodContent">
                    <!-- JSON / Editor Tab -->
                    <div x-show="importTab === 'json'" id="import-json" role="tabpanel"
                        x-transition.opacity.duration.300ms style="display: none;">
                        <div class="flex items-center gap-2 mb-4 mt-2">
                            <span
                                class="flex items-center justify-center w-6 h-6 rounded-full bg-amber-950/40 text-amber-400 font-bold text-xs border border-amber-900/60">3</span>
                            <h5 class="text-sm font-bold text-white tracking-tight">Provide Data &amp; Upload</h5>
                        </div>

                        <p class="text-slate-400 text-sm mb-4 leading-relaxed">
                            <strong class="font-bold text-slate-200">Recommended:</strong> Upload a <code
                                class="bg-slate-900/60 px-1 py-0.5 rounded text-indigo-300 text-xs border border-slate-800/60">.json</code>
                            file or paste directly into the editor below. Every question object must conform fully to
                            the standard SAT database schema.
                        </p>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            <div class="lg:col-span-5 flex flex-col justify-between">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-300 mb-2">Import JSON
                                        File</label>
                                    <div
                                        class="file-dropzone border-2 border-dashed border-slate-800/80 rounded-xl p-6 text-center bg-slate-900/20 relative cursor-pointer hover:bg-slate-900/40 hover:border-indigo-500/50 shadow-inner">
                                        <input type="file"
                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                            id="bulkJsonFile" accept=".json,application/json" style="z-index: 10;">
                                        <i
                                            class="bi bi-filetype-json text-4xl text-slate-500 mb-2 block leading-none"></i>
                                        <span
                                            class="font-semibold block text-slate-200 text-sm mb-1 drag-instruction">Drag
                                            &amp; drop JSON here</span>
                                        <span class="text-slate-500 text-xs">or click to browse file</span>
                                        <div
                                            class="file-name-display mt-2.5 text-xs text-emerald-400 font-bold hidden animate-pulse">
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2 leading-normal">Selecting a JSON file parses
                                        it and populates its contents into the editor.</p>
                                </div>

                                <div class="mt-6">
                                    <h6 class="text-xs font-bold text-slate-500 mb-3 tracking-wide uppercase">Examples
                                        &amp; Templates</h6>
                                    <div class="space-y-2">
                                        <button type="button"
                                            class="w-full text-left px-3.5 py-2.5 text-sm bg-slate-900/40 border border-slate-800/80 rounded-xl hover:bg-slate-900/80 text-slate-200 hover:text-white flex items-center font-semibold"
                                            id="bulkLoadExampleRwBtn">
                                            <i
                                                class="bi bi-plus-circle mr-2 text-indigo-400 text-base leading-none"></i>
                                            Insert R&amp;W Example
                                        </button>
                                        <button type="button"
                                            class="w-full text-left px-3.5 py-2.5 text-sm bg-slate-900/40 border border-slate-800/80 rounded-xl hover:bg-slate-900/80 text-slate-200 hover:text-white flex items-center font-semibold"
                                            id="bulkLoadExampleMathBtn">
                                            <i
                                                class="bi bi-plus-circle mr-2 text-indigo-400 text-base leading-none"></i>
                                            Insert Math Example
                                        </button>
                                        <div class="flex gap-2 pt-1.5">
                                            <button type="button"
                                                class="flex-1 px-3 py-2 text-xs font-semibold text-indigo-400 bg-indigo-950/20 hover:bg-indigo-900/30 rounded-lg border border-indigo-900/30"
                                                id="bulkDownloadRwSampleBtn">R&amp;W Sample.json</button>
                                            <button type="button"
                                                class="flex-1 px-3 py-2 text-xs font-semibold text-indigo-400 bg-indigo-950/20 hover:bg-indigo-900/30 rounded-lg border border-indigo-900/30"
                                                id="bulkDownloadMathSampleBtn">Math Sample.json</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-7 flex flex-col">
                                <div class="flex justify-between items-center mb-2">
                                    <label for="bulkQuestionsJson"
                                        class="text-sm font-semibold text-slate-300 mb-0">Payload (JSON Editor)</label>
                                    <button type="button" class="text-xs font-bold text-rose-400 hover:text-rose-300"
                                        id="bulkClearEditorBtn">Clear Editor</button>
                                </div>
                                <textarea
                                    class="w-full px-3 py-2.5 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-600 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-mono"
                                    id="bulkQuestionsJson" rows="12" spellcheck="false"
                                    placeholder='{ "items": [ ... ] }'></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-800/60">
                            <button type="button"
                                class="px-4 py-2 bg-slate-900/40 border border-slate-800/80 text-slate-300 hover:text-white rounded-lg hover:bg-slate-900/80 shadow-md text-sm font-semibold flex items-center gap-1.5"
                                id="bulkPreviewBtn">
                                <i class="bi bi-eye text-base leading-none"></i> Preview
                            </button>
                            <button type="button"
                                class="px-5 py-2 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 hover:shadow-indigo-500/20 text-white font-semibold text-sm rounded-lg shadow-lg flex items-center gap-1.5"
                                id="bulkImportSubmitBtn">
                                <i class="bi bi-cloud-arrow-up text-base leading-none"></i> Import from Editor
                            </button>
                        </div>
                    </div>

                    <!-- CSV Tab -->
                    <div x-show="importTab === 'csv'" id="import-csv" role="tabpanel"
                        x-transition.opacity.duration.300ms style="display: none;">
                        <div class="flex items-center gap-2 mb-4 mt-2">
                            <span
                                class="flex items-center justify-center w-6 h-6 rounded-full bg-amber-950/40 text-amber-400 font-bold text-xs border border-amber-900/60">3</span>
                            <h5 class="text-sm font-bold text-white tracking-tight">Upload CSV</h5>
                        </div>
                        <p class="text-slate-400 text-sm mb-4 leading-relaxed">
                            Ideal for importing massive tables compiled in Excel or Google Sheets. The header row is
                            strictly required. Column header names must match identical lowercase snake_case keywords
                            (e.g. <code>stem</code>, <code>difficulty</code>).
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">CSV File (.csv or .txt)
                                    <span class="text-rose-500">*</span></label>
                                <div
                                    class="file-dropzone border-2 border-dashed border-slate-800/80 rounded-xl p-8 text-center bg-slate-900/20 relative cursor-pointer hover:bg-slate-900/40 hover:border-indigo-500/50 shadow-inner">
                                    <input type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        id="bulkCsvFile" accept=".csv,.txt,text/csv,text/plain" style="z-index: 10;">
                                    <i
                                        class="bi bi-file-earmark-spreadsheet text-4xl text-slate-500 mb-3 block leading-none"></i>
                                    <span class="font-semibold block text-slate-200 text-sm mb-1 drag-instruction">Drag
                                        &amp; drop CSV here</span>
                                    <span class="text-slate-500 text-xs">or click to browse file</span>
                                    <div
                                        class="file-name-display mt-2.5 text-xs text-emerald-400 font-bold hidden animate-pulse">
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col justify-center gap-3">
                                <span class="text-sm font-bold text-white">Need a structured template?</span>
                                <button type="button"
                                    class="w-fit px-4 py-2 text-sm bg-slate-900/40 border border-slate-800/80 rounded-lg hover:bg-slate-900/80 text-slate-300 hover:text-white shadow-md flex items-center gap-2 font-semibold"
                                    id="bulkDownloadRwSampleCsvBtn">
                                    <i class="bi bi-download text-indigo-400 text-base leading-none"></i> Download
                                    R&amp;W Template.csv
                                </button>
                                <button type="button"
                                    class="w-fit px-4 py-2 text-sm bg-slate-900/40 border border-slate-800/80 rounded-lg hover:bg-slate-900/80 text-slate-300 hover:text-white shadow-md flex items-center gap-2 font-semibold"
                                    id="bulkDownloadMathSampleCsvBtn">
                                    <i class="bi bi-download text-indigo-400 text-base leading-none"></i> Download Math
                                    Template.csv
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-800/60">
                            <button type="button"
                                class="px-4 py-2 bg-slate-900/40 border border-slate-800/80 text-slate-300 hover:text-white rounded-lg hover:bg-slate-900/80 shadow-md text-sm font-semibold flex items-center gap-1.5"
                                id="bulkCsvPreviewBtn">
                                <i class="bi bi-eye text-base leading-none"></i> Preview
                            </button>
                            <button type="button"
                                class="px-5 py-2 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 hover:shadow-indigo-500/20 text-white font-semibold text-sm rounded-lg shadow-lg flex items-center gap-1.5"
                                id="bulkCsvImportSubmitBtn">
                                <i class="bi bi-cloud-arrow-up text-base leading-none"></i> Import CSV
                            </button>
                        </div>
                    </div>

                    <!-- ZIP Tab -->
                    <div x-show="importTab === 'zip'" id="import-zip" role="tabpanel"
                        x-transition.opacity.duration.300ms style="display: none;">
                        <div class="flex items-center gap-2 mb-4 mt-2">
                            <span
                                class="flex items-center justify-center w-6 h-6 rounded-full bg-amber-950/40 text-amber-400 font-bold text-xs border border-amber-900/60">3</span>
                            <h5 class="text-sm font-bold text-white tracking-tight">Upload ZIP Package</h5>
                        </div>
                        <p class="text-slate-400 text-sm mb-4 leading-relaxed">
                            <strong class="font-bold text-slate-200">Power User Bundle:</strong> Upload a compressed
                            <code
                                class="bg-slate-900/60 px-1 py-0.5 rounded text-indigo-300 text-xs border border-slate-800/60">.zip</code>
                            containing a data payload (JSON/CSV) plus image files. Reference your files inside question
                            stems/passages via simple <code>[MEDIA:filename.png]</code> placeholders.
                        </p>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-end">
                            <div class="lg:col-span-8">
                                <label class="block text-sm font-semibold text-slate-300 mb-2">ZIP File <span
                                        class="text-rose-500">*</span></label>
                                <div
                                    class="file-dropzone border-2 border-dashed border-slate-800/80 rounded-xl p-8 text-center bg-slate-900/20 relative cursor-pointer hover:bg-slate-900/40 hover:border-indigo-500/50 shadow-inner">
                                    <input type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        id="bulkZipFile" accept=".zip" style="z-index: 10;">
                                    <i
                                        class="bi bi-file-earmark-zip text-4xl text-slate-500 mb-3 block leading-none"></i>
                                    <span class="font-semibold block text-slate-200 text-sm mb-1 drag-instruction">Drag
                                        &amp; drop ZIP here</span>
                                    <span class="text-slate-400 text-xs text-slate-500">or click to browse file</span>
                                    <div
                                        class="file-name-display mt-2.5 text-xs text-emerald-400 font-bold hidden animate-pulse">
                                    </div>
                                </div>

                                <!-- ZIP Progress Bar Container -->
                                <div id="zipUploadProgressContainer"
                                    class="hidden mt-4 bg-slate-950/40 border border-slate-800/60 rounded-xl p-4 shadow-inner">
                                    <div class="flex justify-between text-xs font-semibold text-slate-400 mb-2">
                                        <span>Uploading &amp; Unzipping Package...</span>
                                        <span id="zipUploadPercentage" class="text-indigo-400 font-bold">0%</span>
                                    </div>
                                    <div
                                        class="w-full bg-slate-900 rounded-full h-2 overflow-hidden border border-slate-800/40">
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
                                    class="w-full py-4 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 hover:shadow-indigo-500/20 text-white font-bold rounded-lg shadow-lg flex items-center justify-center gap-2"
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