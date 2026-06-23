<x-ui.modal id="createTestWizardModal" max-width="4xl">
    <x-slot:title>
        <div>
            <h4 class="text-base font-extrabold leading-tight text-slate-900">Create test</h4>
            <p class="mt-1 text-xs font-medium text-slate-600">Start from a trusted SAT structure, then adjust only what
                you need.</p>
        </div>
    </x-slot:title>

    <div class="create-test-wizard">
        <div id="wizard-options" class="space-y-5">
            <div>
                <h5 class="text-sm font-extrabold text-slate-900">Choose a starting structure</h5>
                <p class="mt-1 text-sm text-slate-600">Every test starts as a private draft. You can publish it when
                    content is ready.</p>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2" role="list">
                <button type="button" id="wizard-btn-full-sat" data-dialog-initial-focus
                    class="wizard-template-option group flex min-h-24 items-center gap-4 rounded-xl border-2 border-indigo-300 bg-indigo-50/60 p-4 text-left outline-none transition-colors hover:bg-indigo-50 focus-visible:ring-4 focus-visible:ring-indigo-500/20">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white"><i
                            class="bi bi-journal-text text-lg" aria-hidden="true"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2"><strong class="text-sm text-slate-900">Normal Full
                                Test</strong><span
                                class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-indigo-700 ring-1 ring-inset ring-indigo-200">Recommended</span></span>
                        <span class="mt-1 block text-xs font-medium leading-relaxed text-slate-600">Four fixed modules with calculator-style raw conversion. No adaptive route bonus.</span>
                    </span>
                    <i class="bi bi-arrow-right text-slate-400 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"></i>
                </button>

                <button type="button" id="wizard-btn-adaptive-sat"
                    class="wizard-template-option group flex min-h-24 items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left outline-none transition-colors hover:border-indigo-300 hover:bg-slate-50 focus-visible:ring-4 focus-visible:ring-indigo-500/20">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-indigo-700"><i
                            class="bi bi-diagram-3 text-lg" aria-hidden="true"></i></span>
                    <span class="min-w-0 flex-1"><strong class="text-sm text-slate-900">Adaptive Full Test</strong><span
                            class="mt-1 block text-xs font-medium leading-relaxed text-slate-600">Six modules with EAP IRT routing, provisional scaled estimate, and uncertainty range.</span></span>
                    <i class="bi bi-arrow-right text-slate-400 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"></i>
                </button>

                <button type="button" id="wizard-btn-short-test"
                    class="wizard-template-option group flex min-h-24 items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left outline-none transition-colors hover:border-indigo-300 hover:bg-slate-50 focus-visible:ring-4 focus-visible:ring-indigo-500/20">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><i
                            class="bi bi-lightning-charge text-lg" aria-hidden="true"></i></span>
                    <span class="min-w-0 flex-1"><strong class="text-sm text-slate-900">Short test</strong><span
                            class="mt-1 block text-xs font-medium leading-relaxed text-slate-600">One Reading &amp;
                            Writing and one Math module.</span></span>
                    <i class="bi bi-arrow-right text-slate-400 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"></i>
                </button>

                <button type="button" id="wizard-btn-module-only"
                    class="wizard-template-option group flex min-h-24 items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left outline-none transition-colors hover:border-indigo-300 hover:bg-slate-50 focus-visible:ring-4 focus-visible:ring-indigo-500/20">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><i
                            class="bi bi-box-seam text-lg" aria-hidden="true"></i></span>
                    <span class="min-w-0 flex-1"><strong class="text-sm text-slate-900">Single module</strong><span
                            class="mt-1 block text-xs font-medium leading-relaxed text-slate-600">Start with one
                            reusable Reading &amp; Writing module.</span></span>
                    <i class="bi bi-arrow-right text-slate-400 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"></i>
                </button>

                <button type="button" id="wizard-btn-custom"
                    class="wizard-template-option group flex min-h-24 items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left outline-none transition-colors hover:border-indigo-300 hover:bg-slate-50 focus-visible:ring-4 focus-visible:ring-indigo-500/20">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><i
                            class="bi bi-sliders text-lg" aria-hidden="true"></i></span>
                    <span class="min-w-0 flex-1"><strong class="text-sm text-slate-900">Custom structure</strong><span
                            class="mt-1 block text-xs font-medium leading-relaxed text-slate-600">Begin with one module
                            and define your own blueprint.</span></span>
                    <i class="bi bi-arrow-right text-slate-400 transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"></i>
                </button>
            </div>

            <section class="rounded-xl border border-slate-200 bg-slate-50 p-4" aria-labelledby="wizard-reuse-heading">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h5 id="wizard-reuse-heading" class="text-sm font-extrabold text-slate-900">Build from trusted
                            content</h5>
                        <p class="mt-1 max-w-2xl text-xs font-medium leading-relaxed text-slate-600">Copy an existing
                            section or module into an independent private draft. Questions stay linked to your item
                            bank.</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button type="button" id="wizard-btn-from-section"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 transition-colors hover:border-indigo-300 hover:bg-indigo-50 focus-visible:ring-4 focus-visible:ring-indigo-500/20"><i
                                class="bi bi-layout-text-window" aria-hidden="true"></i>Use a section</button>
                        <button type="button" id="wizard-btn-from-module"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 transition-colors hover:border-indigo-300 hover:bg-indigo-50 focus-visible:ring-4 focus-visible:ring-indigo-500/20"><i
                                class="bi bi-box-seam" aria-hidden="true"></i>Use a module</button>
                    </div>
                </div>
            </section>
        </div>

        <div id="wizard-reuse-flow" class="hidden space-y-5" aria-live="polite">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h5 id="wizard-reuse-title" class="text-sm font-extrabold text-slate-900">Create from existing
                        content</h5>
                    <p id="wizard-reuse-help" class="mt-1 max-w-2xl text-sm text-slate-600">Choose a source test, then
                        select content to copy.</p>
                </div>
                <span id="wizard-reuse-kind"
                    class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 ring-1 ring-inset ring-indigo-200">Section</span>
            </div>

            <div id="wizard-reuse-skeleton" class="space-y-3" role="status"><span class="sr-only">Loading reusable
                    content</span>
                <div class="h-11 animate-pulse rounded-lg bg-slate-100"></div>
                <div class="h-11 animate-pulse rounded-lg bg-slate-100"></div>
                <div class="h-24 animate-pulse rounded-xl bg-slate-100"></div>
            </div>

            <div id="wizard-reuse-fields" class="hidden space-y-4">
                <label class="block text-sm font-bold text-slate-700">Source test<select id="wizard-source-test"
                        class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/15"></select></label>
                <label class="block text-sm font-bold text-slate-700">Content to copy<select id="wizard-source-item"
                        class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/15"></select></label>
                <label id="wizard-destination-wrap" class="hidden text-sm font-bold text-slate-700">Destination
                    draft<select id="wizard-destination-test"
                        class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/15"></select></label>
                <label id="wizard-derived-title-wrap" class="block text-sm font-bold text-slate-700">New test
                    title<input id="wizard-derived-title" maxlength="255"
                        class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm font-semibold text-slate-900 placeholder-slate-500 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/15"
                        placeholder="Enter a clear test title"></label>
                <div id="wizard-source-summary"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>
            </div>

            <div id="wizard-reuse-empty"
                class="hidden rounded-xl border border-slate-200 bg-slate-50 p-6 text-center">
                <h6 class="font-extrabold text-slate-900">No reusable content found</h6>
                <p class="mt-1 text-sm text-slate-600">Create or publish content in your item bank, then try again.</p>
                <button type="button" id="wizard-reuse-retry"
                    class="mt-4 min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-800 hover:bg-slate-100">Retry</button>
            </div>
            <div id="wizard-reuse-error"
                class="hidden rounded-lg bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800 ring-1 ring-inset ring-rose-200"
                role="alert" tabindex="-1"></div>

            <div
                class="wizard-actions -mx-4 flex flex-col-reverse gap-3 border-t border-slate-200 bg-white px-4 py-4 sm:-mx-6 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <button type="button" id="wizard-reuse-back"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"><i
                        class="bi bi-arrow-left" aria-hidden="true"></i>Back</button>
                <button type="button" id="wizard-reuse-submit"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">Create
                    independent copy <i class="bi bi-copy" aria-hidden="true"></i></button>
            </div>
        </div>

        <div id="wizard-config-flow" class="hidden space-y-5">
            <section class="rounded-xl bg-indigo-50/70 p-4 ring-1 ring-inset ring-indigo-200 sm:p-5"
                aria-labelledby="wizard-title-heading">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h5 id="wizard-title-heading" class="text-sm font-extrabold text-slate-900">Name your test
                            </h5>
                            <span
                                class="rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-slate-600 ring-1 ring-inset ring-indigo-200">Draft</span>
                        </div>
                        <p id="wizard-title-help" class="mt-1 text-xs font-medium leading-relaxed text-slate-600">
                            Leave blank to use the suggested title. Drafts are not visible to students.</p>
                    </div>
                    <span id="wizard-config-label"
                        class="self-start rounded-full bg-white px-2.5 py-1 text-xs font-bold text-indigo-700 ring-1 ring-inset ring-indigo-200">Full
                        SAT</span>
                </div>
                <label for="wizard-config-title" class="mt-4 block text-xs font-bold text-slate-700">Test title <span
                        class="font-medium text-slate-500">(optional)</span></label>
                <input type="text" id="wizard-config-title" maxlength="255" autocomplete="off"
                    aria-describedby="wizard-title-help"
                    class="mt-2 w-full rounded-lg border border-indigo-300 bg-white px-4 py-3 text-base font-semibold text-slate-900 placeholder-slate-500 outline-none transition-colors focus:border-indigo-600 focus:ring-4 focus:ring-indigo-500/15">
            </section>

            <section aria-labelledby="wizard-structure-heading">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h5 id="wizard-structure-heading" class="text-sm font-extrabold text-slate-900">Structure
                            summary</h5>
                        <p class="mt-1 text-xs font-medium text-slate-600">Review the blueprint now. Detailed controls
                            stay out of the way until requested.</p>
                    </div>
                    <div id="wizard-summary-totals" class="flex flex-wrap gap-2 text-xs font-bold text-slate-600"
                        aria-live="polite"></div>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <button type="button" id="wizard-toggle-customize" aria-expanded="false"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:ring-4 focus-visible:ring-indigo-500/20">
                        <i class="bi bi-sliders" aria-hidden="true"></i><span>Customize modules</span>
                    </button>
                    <label id="wizard-populate-control"
                        class="hidden min-h-11 items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" id="wizard-populate-pool"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Fill modules from question bank
                    </label>
                </div>

                <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div id="wizard-module-summary" class="divide-y divide-slate-100"></div>
                </div>
            </section>

            <section id="wizard-customize-panel" class="hidden space-y-3 border-t border-slate-200 pt-5"
                aria-labelledby="wizard-customize-heading">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h5 id="wizard-customize-heading" class="text-sm font-extrabold text-slate-900">Module
                            settings</h5>
                        <p class="mt-1 text-xs text-slate-600">Duration is in minutes. Question count must match
                            available content when filling from the bank.</p>
                    </div>
                    <button type="button" id="wizard-btn-add-row"
                        class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"><i
                            class="bi bi-plus-lg" aria-hidden="true"></i>Add module</button>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600">
                            <tr>
                                <th class="px-3 py-3">Section</th>
                                <th class="px-3 py-3">Module</th>
                                <th class="px-3 py-3">Difficulty</th>
                                <th class="px-3 py-3">Minutes</th>
                                <th class="px-3 py-3">Questions</th>
                                <th class="px-3 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="wizard-module-rows" class="divide-y divide-slate-100 bg-white"></tbody>
                    </table>
                </div>
                <div id="wizard-row-feedback"
                    class="hidden items-center justify-between gap-3 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700"
                    role="status">
                    <span>Module removed.</span><button type="button" id="wizard-undo-remove"
                        class="min-h-9 rounded-md px-3 font-bold text-indigo-700 hover:bg-white">Undo</button>
                </div>
            </section>

            <div id="wizard-form-error"
                class="hidden rounded-lg bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800 ring-1 ring-inset ring-rose-200"
                role="alert"></div>

            <div
                class="wizard-actions -mx-4 flex flex-col-reverse gap-3 border-t border-slate-200 bg-white px-4 py-4 sm:-mx-6 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <button type="button" id="wizard-btn-back"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"><i
                        class="bi bi-arrow-left" aria-hidden="true"></i>Choose another structure</button>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" data-modal-close
                        x-on:click="$dispatch('close-modal', 'createTestWizardModal')"
                        class="min-h-11 rounded-lg px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button type="button" id="wizard-btn-create-configured"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-bold text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">Create
                        draft <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>

        <div id="wizard-loading" class="hidden min-h-80 flex-col items-center justify-center text-center"
            role="status" aria-live="polite">
            <div class="w-full max-w-md space-y-3" aria-hidden="true">
                <div class="h-5 w-2/3 animate-pulse rounded bg-slate-100"></div>
                <div class="h-11 animate-pulse rounded-lg bg-slate-100"></div>
                <div class="h-11 animate-pulse rounded-lg bg-slate-100"></div>
            </div>
            <h4 class="mt-5 text-sm font-extrabold text-slate-900">Creating your draft</h4>
            <p class="mt-2 text-xs font-medium text-slate-600">Building sections and modules, then opening the first
                module.</p>
        </div>
    </div>
</x-ui.modal>
