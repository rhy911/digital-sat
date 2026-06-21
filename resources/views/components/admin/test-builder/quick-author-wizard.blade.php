<x-ui.modal id="quickAuthorWizardModal" max-width="3xl">
    <x-slot:title>
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center">
                <i class="bi bi-magic text-indigo-600 text-lg"></i>
            </div>
            <div>
                <h4 class="text-base font-extrabold text-slate-900 leading-none">Quick Author</h4>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-none">Choose a starting structure for a new test.</p>
            </div>
        </div>
    </x-slot:title>

    <div class="space-y-6 py-1">
        <!-- Recent Continuation Section -->
        <div id="wizard-recent-work-container" class="hidden">
            <div class="flex items-center justify-between mb-3 px-1">
                <h6 class="text-xs font-bold text-slate-600 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Continue recent work
                </h6>
            </div>
            <div class="flex flex-wrap gap-2.5" id="wizard-recent-work-list">
                <!-- Populated via JS -->
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Option 1: Full SAT -->
            <button type="button"
                class="group relative rounded-lg border border-slate-200 bg-white p-4 text-left cursor-pointer hover:border-indigo-300 hover:bg-slate-50 transition-colors duration-150 flex flex-col gap-3 outline-none focus:ring-2 focus:ring-indigo-500/40"
                id="wizard-btn-full-sat">
                <div class="flex items-center justify-between gap-3">
                    <div class="w-9 h-9 bg-indigo-50 text-indigo-600 border border-indigo-100 rounded-lg flex items-center justify-center">
                        <i class="bi bi-journal-text text-lg leading-none"></i>
                    </div>
                    <span class="text-[11px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-full px-2 py-0.5">Standard</span>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1 leading-tight">Full SAT</h4>
                    <p class="text-slate-500 text-xs leading-snug font-medium">Create the standard six-module test.</p>
                </div>
            </button>

            <!-- Option 1b: Short Test -->
            <button type="button"
                class="group relative rounded-lg border border-slate-200 bg-white p-4 text-left cursor-pointer hover:border-indigo-300 hover:bg-slate-50 transition-colors duration-150 flex flex-col gap-3 outline-none focus:ring-2 focus:ring-indigo-500/40"
                id="wizard-btn-short-test">
                <div class="w-9 h-9 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg flex items-center justify-center">
                    <i class="bi bi-lightning-charge text-lg leading-none"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1 leading-tight">Short test</h4>
                    <p class="text-slate-500 text-xs leading-snug font-medium">Start with fewer custom modules.</p>
                </div>
            </button>

            <!-- Option 1c: Module Only -->
            <button type="button"
                class="group relative rounded-lg border border-slate-200 bg-white p-4 text-left cursor-pointer hover:border-indigo-300 hover:bg-slate-50 transition-colors duration-150 flex flex-col gap-3 outline-none focus:ring-2 focus:ring-indigo-500/40"
                id="wizard-btn-module-only">
                <div class="w-9 h-9 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg flex items-center justify-center">
                    <i class="bi bi-box-seam text-lg leading-none"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1 leading-tight">Module</h4>
                    <p class="text-slate-500 text-xs leading-snug font-medium">Create one reusable module.</p>
                </div>
            </button>

            <!-- Option 2: Custom Content -->
            <button type="button"
                class="group relative rounded-lg border border-slate-200 bg-white p-4 text-left cursor-pointer hover:border-indigo-300 hover:bg-slate-50 transition-colors duration-150 flex flex-col gap-3 outline-none focus:ring-2 focus:ring-indigo-500/40"
                id="wizard-btn-custom">
                <div class="w-9 h-9 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg flex items-center justify-center">
                    <i class="bi bi-puzzle text-lg leading-none"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-slate-900 mb-1 leading-tight">Custom</h4>
                    <p class="text-slate-500 text-xs leading-snug font-medium">Assemble your own SAT parts.</p>
                </div>
            </button>
        </div>

        <!-- Configurable Flow -->
        <div id="wizard-config-flow" class="hidden border-t border-slate-200 pt-5 space-y-5">
            <section class="rounded-xl bg-indigo-50/70 border border-indigo-200 p-4 sm:p-5" aria-labelledby="wizard-title-heading">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-extrabold text-white" aria-hidden="true">1</span>
                        <div>
                            <h5 id="wizard-title-heading" class="text-sm font-extrabold text-slate-900">Name your test</h5>
                            <p id="wizard-title-help" class="mt-1 text-xs font-medium leading-relaxed text-slate-600">Add your own title, or leave blank to use the suggested title.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:pt-0.5">
                        <span class="text-[11px] font-semibold text-slate-500">Template</span>
                        <span id="wizard-config-label" class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-indigo-700 ring-1 ring-inset ring-indigo-200">Full SAT</span>
                    </div>
                </div>

                <div class="mt-4">
                    <label for="wizard-config-title" class="mb-2 flex items-center gap-2 text-sm font-bold text-slate-800">
                        Test title
                        <span class="rounded bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-500 ring-1 ring-inset ring-indigo-200">Optional</span>
                    </label>
                    <input type="text" id="wizard-config-title" maxlength="255" autocomplete="off"
                        aria-describedby="wizard-title-help"
                        class="w-full rounded-lg border-2 border-indigo-300 bg-white px-4 py-3 text-base font-semibold text-slate-900 placeholder-slate-400 outline-none transition-colors focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/15"
                        placeholder="Example: Grade 11 Full Practice Test">
                </div>
            </section>

            <div class="flex items-center gap-3" aria-hidden="true">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-extrabold text-slate-700">2</span>
                <div>
                    <h5 class="text-sm font-extrabold text-slate-900">Configure modules</h5>
                    <p class="mt-0.5 text-xs font-medium text-slate-500">Review the preset structure and adjust it as needed.</p>
                </div>
            </div>

            <div id="wizard-short-counts" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-600">Reading &amp; Writing modules</label>
                    <input type="number" min="0" max="10" value="1" id="wizard-short-rw-count"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none text-sm transition-all">
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-600">Math modules</label>
                    <input type="number" min="0" max="10" value="1" id="wizard-short-math-count"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-white text-slate-900 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none text-sm transition-all">
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-slate-400 border-b border-slate-200 font-semibold uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="px-3 py-2.5 font-semibold text-slate-400 tracking-wider">Section</th>
                            <th class="px-3 py-2.5 font-semibold text-slate-400 tracking-wider">Module</th>
                            <th class="px-3 py-2.5 font-semibold text-slate-400 tracking-wider">Difficulty</th>
                            <th class="px-3 py-2.5 font-semibold text-slate-400 tracking-wider">Duration</th>
                            <th class="px-3 py-2.5 font-semibold text-slate-400 tracking-wider">Questions</th>
                            <th class="px-3 py-2.5 font-semibold text-slate-400 text-right tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody id="wizard-module-rows" class="divide-y divide-slate-100 bg-white"></tbody>
                </table>
            </div>

            <div class="flex flex-wrap justify-between items-center gap-3 pt-2">
                <button type="button"
                    class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 font-semibold text-sm rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2 cursor-pointer"
                    id="wizard-btn-add-row">
                    <i class="bi bi-plus-lg"></i> Add module
                </button>
                <div class="flex gap-3">
                    <button type="button"
                        class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 font-semibold text-sm rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2 cursor-pointer"
                        id="wizard-btn-back">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button type="button"
                        class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg transition-colors duration-150 flex items-center gap-2 cursor-pointer"
                        id="wizard-btn-create-configured">
                        Create test <i class="bi bi-check2-circle"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div id="wizard-loading" class="text-center py-16 hidden flex flex-col items-center justify-center">
            <div class="relative w-16 h-16">
                <div class="absolute inset-0 border-4 border-indigo-500/20 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin">
                </div>
            </div>
            <h4 class="mt-6 text-slate-900 font-bold text-sm">Generating</h4>
            <p class="mt-2 text-slate-500 text-xs font-medium">Building your SAT structure...</p>
        </div>
    </div>
</x-ui.modal>
