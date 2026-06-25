@props(['tests'])

<div x-show="activeTab === 'tests'" id="tests" role="tabpanel" aria-labelledby="tests-tab"
    :aria-hidden="activeTab === 'tests' ? 'false' : 'true'" :class="{ 'active': activeTab === 'tests' }"
    class="tab-pane" style="display: none;">
    <div class="space-y-5">
        <!-- Empty State -->
        <div id="testsEmptyState"
            class="max-w-4xl mx-auto rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden {{ $tests->isEmpty() ? '' : 'hidden' }}"
            aria-hidden="{{ $tests->isEmpty() ? 'false' : 'true' }}">
            <div class="p-16 text-center">
                <div
                    class="mx-auto w-20 h-20 bg-indigo-50 border border-indigo-100 rounded-full flex items-center justify-center mb-6">
                    <i class="bi bi-journal-text text-3xl text-indigo-600"></i>
                </div>
                <h3 class="text-xl font-extrabold text-slate-900 mb-2">No practice tests yet</h3>
                <p class="text-slate-600 mb-8 max-w-sm mx-auto text-sm leading-relaxed">Start with a draft test, then
                    add sections and modules when you are ready.</p>
                <p class="text-xs font-semibold text-slate-500">Use Create test in the page header to begin.</p>
            </div>
        </div>

        <!-- Table Container -->
        <div id="testsTableContainer"
            class="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden {{ $tests->isEmpty() ? 'hidden' : '' }}">
            <div
                class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h5 class="font-bold text-slate-800 mb-0 text-sm">All tests</h5>
                <div class="flex items-center gap-4 flex-wrap md:flex-nowrap">
                    @if (auth()->user()->role === 'teacher')
                        <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-lg border border-slate-200">
                            <label for="testsShowSharedToggle"
                                class="text-xs font-bold text-slate-600 cursor-pointer select-none">Show shared</label>
                            <input type="checkbox" id="testsShowSharedToggle"
                                class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded cursor-pointer tests-show-shared-toggle">
                        </div>
                    @endif
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                                class="bi bi-search text-xs"></i></span>
                        <input type="text"
                            class="pl-9 pr-4 py-2.5 w-full md:w-72 text-sm rounded-lg border border-slate-200 bg-white text-slate-800 placeholder-slate-400 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition-all duration-150"
                            id="testsTableSearch" placeholder="Search by title...">
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="teacher-data-table teacher-tests-table w-full text-left text-xs text-slate-600">
                    <colgroup>
                        <col class="col-id">
                        <col class="col-title">
                        <col class="col-type">
                        <col class="col-date">
                        <col class="col-owner">
                        <col class="col-public">
                        <col class="col-status">
                        <col class="col-duration">
                        <col class="col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-center">Id</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th class="text-center">Date</th>
                            <th>Owner</th>
                            <th class="text-center">Public</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Duration</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="testsTableBody" class="divide-y divide-slate-100 bg-transparent">
                        <!-- Dynamic Rows Rendered by JS -->
                    </tbody>
                </table>
            </div>
            <div id="testsPoolPagination" class="mt-0 border-t border-slate-200 bg-slate-50 p-4"></div>
            @php
                $testsData = $tests->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'title' => $t->title,
                        'type' => $t->test_type,
                        'raw_type' => $t->test_type,
                        'status' => $t->status,
                        'duration' => $t->total_duration_minutes,
                        'created_by' => $t->created_by,
                        'created_by_name' => $t->creator ? $t->creator->name ?? $t->creator->username ?? $t->creator->email : 'Admin',
                        'created_at' => $t->created_at ? $t->created_at->format('d/m/y') : null,
                        'is_public' => (bool) $t->is_public,
                        'is_owner' => $t->created_by === auth()->id() || auth()->user()->role === 'admin',
                        'is_shared' => auth()->user()->role === 'teacher' && $t->created_by !== auth()->id() && $t->shares->contains('user_id', auth()->id()),
                        'shares_count' => $t->shares_count ?? $t->shares->count(),
                        'can_convert_to_normal' => $t->test_type === 'adaptive_full_length'
                            && $t->status === 'draft'
                            && $t->user_tests_count === 0
                            && $t->sections->count() === 2
                            && $t->sections->every(fn ($section) => $section->modules->where('module_number', 1)->count() === 1
                                && $section->modules->where('module_number', 2)->count() === 1),
                    ];
                });
            @endphp
            <script>
                window.__tdTestsData = @json($testsData);
            </script>
        </div>

        <section id="scoreConversionPanel" class="hidden rounded-xl border border-slate-200 bg-white p-5 md:p-6"
            aria-labelledby="scoreConversionTitle" aria-live="polite">
            <div class="flex flex-col gap-2 border-b border-slate-200 pb-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 id="scoreConversionTitle" class="text-base font-extrabold text-slate-900">Score conversion</h3>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Normal Full tests use a built-in route-neutral table. Import a reviewed form table when you need a more accurate form-specific conversion. Adaptive Full tests use the system IRT mapping.</p>
                </div>
                <button type="button" id="scoreConversionClose" class="min-h-11 rounded-lg px-3 text-sm font-bold text-slate-600 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Close</button>
            </div>

            <form id="scoreConversionForm" class="mt-5 space-y-4">
                <input type="hidden" id="scoreConversionTestId">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">Source name
                        <input id="scoreConversionSource" required maxlength="255" class="min-h-11 rounded-lg border border-slate-300 px-3 font-normal text-slate-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20" placeholder="Reviewed practice estimate v1">
                    </label>
                    <label class="grid gap-1.5 text-sm font-bold text-slate-700">Source URL <span class="font-normal text-slate-500">Optional</span>
                        <input id="scoreConversionSourceUrl" type="url" maxlength="2048" class="min-h-11 rounded-lg border border-slate-300 px-3 font-normal text-slate-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20" placeholder="https://...">
                    </label>
                </div>
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Conversion rows (JSON)
                    <textarea id="scoreConversionRows" required rows="9" spellcheck="false" class="rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs leading-5 text-slate-900 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/20" placeholder='[{"section_type":"math","raw_score":0,"scaled_score":200}]'></textarea>
                </label>
                <div id="scoreConversionStatus" class="hidden rounded-lg bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700" role="status"></div>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="submit" id="scoreConversionImport" class="min-h-11 rounded-lg border border-indigo-600 bg-white px-4 text-sm font-extrabold text-indigo-700 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50">Import draft</button>
                    <button type="button" id="scoreConversionApprove" disabled class="min-h-11 rounded-lg bg-indigo-700 px-4 text-sm font-extrabold text-white hover:bg-indigo-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50">Approve conversion</button>
                </div>
            </form>
        </section>
    </div>

</div>
