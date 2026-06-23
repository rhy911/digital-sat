@props(['tests'])

<div x-show="activeTab === 'sections'" id="sections" role="tabpanel" aria-labelledby="sections-tab"
    :aria-hidden="activeTab === 'sections' ? 'false' : 'true'" :class="{ 'active': activeTab === 'sections' }"
    class="tab-pane" style="display: none;">
    <div class="space-y-5">
        @php
            $hasSections = $tests->getCollection()->flatMap->sections->count() > 0;
        @endphp

        <!-- Empty State -->
        <div id="sectionsEmptyState"
            class="max-w-4xl mx-auto rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden {{ $hasSections ? 'hidden' : '' }}"
            aria-hidden="{{ $hasSections ? 'true' : 'false' }}">
            <div class="p-16 text-center">
                <div
                    class="mx-auto w-20 h-20 bg-indigo-50 border border-indigo-100 rounded-full flex items-center justify-center mb-6">
                    <i class="bi bi-puzzle-fill text-3xl text-indigo-600"></i>
                </div>
                <h3 class="text-xl font-extrabold text-slate-900 mb-2">No sections yet</h3>
                <p class="text-slate-600 mb-8 max-w-sm mx-auto text-sm leading-relaxed">Add a section to a test, then
                    attach the modules students will take.</p>
                <button type="button"
                    class="px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-sm text-sm transition-colors duration-150"
                    x-on:click="$dispatch('open-offcanvas', 'createSectionOffcanvas')">
                    Create section
                </button>
            </div>
        </div>

        <!-- Table Container -->
        <div id="sectionsTableContainer"
            class="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden {{ $hasSections ? '' : 'hidden' }}">
            <div
                class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h5 class="font-bold text-slate-800 mb-0 text-sm">All sections</h5>
                <div class="flex items-center gap-4 flex-wrap md:flex-nowrap">
                    <button
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700"
                        x-on:click="$dispatch('open-offcanvas', 'createSectionOffcanvas')"><i class="bi bi-plus-lg"
                            aria-hidden="true"></i>Create section</button>
                    @if (auth()->user()->role === 'teacher')
                        <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-lg border border-slate-200">
                            <label for="sectionsShowSharedToggle"
                                class="text-xs font-bold text-slate-600 cursor-pointer select-none">Show shared</label>
                            <input type="checkbox" id="sectionsShowSharedToggle"
                                class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded cursor-pointer sections-show-shared-toggle">
                        </div>
                    @endif
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                                class="bi bi-search text-xs"></i></span>
                        <input type="text"
                            class="pl-9 pr-4 py-2.5 w-full md:w-72 text-sm rounded-lg border border-slate-200 bg-white text-slate-800 placeholder-slate-400 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition-all duration-150"
                            id="sectionsTableSearch" placeholder="Search by section or test...">
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="teacher-data-table teacher-sections-table w-full text-left text-xs text-slate-600">
                    <colgroup>
                        <col class="col-id">
                        <col class="col-test">
                        <col class="col-name">
                        <col class="col-date">
                        <col class="col-owner">
                        <col class="col-public">
                        <col class="col-order">
                        <col class="col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-center">Id</th>
                            <th>Test</th>
                            <th>Section Name</th>
                            <th class="text-center">Date</th>
                            <th>Owner</th>
                            <th class="text-center">Public</th>
                            <th class="text-center">Order</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sectionsTableBody" class="divide-y divide-slate-100 bg-transparent">
                        <!-- Dynamic Rows Rendered by JS -->
                    </tbody>
                </table>
            </div>
            <div id="sectionsPoolPagination" class="mt-0 border-t border-slate-200 bg-slate-50 p-4"></div>
            @php
                $sectionsData = $tests
                    ->flatMap(function ($test) {
                        return $test->sections->map(function ($section) use ($test) {
                            return [
                                'id' => $section->id,
                                'test_title' => $test->title,
                                'name' => $section->name,
                                'type' => ucfirst(str_replace('_', ' ', $section->type)),
                                'order' => $section->order,
                                'created_by' => $section->created_by,
                                'created_by_name' => $section->creator
                                    ? $section->creator->name ?? $section->creator->username ?? $section->creator->email
                                    : 'Admin',
                                'created_at' => $section->created_at ? $section->created_at->format('d/m/y') : null,
                                'is_public' => (bool) $section->is_public,
                                'is_owner' => $section->created_by === auth()->id() || auth()->user()->role === 'admin',
                            ];
                        });
                    })
                    ->values();
            @endphp
            <script>
                window.__tdSectionsData = @json($sectionsData);
            </script>
        </div>
    </div>

    <!-- Create Section Offcanvas -->
    <x-ui.offcanvas id="createSectionOffcanvas" width="w-[480px]">
        <x-slot:titleContent>
            <i class="bi bi-puzzle-fill text-indigo-600 mr-3 text-xl"></i> Create section
        </x-slot:titleContent>
        <form id="sectionForm" class="space-y-6">
            @csrf
            <div>
                <label for="sectionTest"
                    class="text-xs font-extrabold text-slate-500 tracking-wider uppercase mb-2 block">Parent test <span
                        class="text-rose-500">*</span></label>
                <select
                    class="form-select tom-select w-full bg-white border border-slate-200 text-slate-800 text-sm placeholder-slate-400 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 rounded-xl"
                    id="sectionTest" name="test_id" required>
                    <option value="">Search test...</option>
                    @foreach ($tests as $test)
                        <option value="{{ $test->id }}">{{ $test->title }} (ID:{{ $test->id }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="sectionType"
                    class="text-xs font-extrabold text-slate-500 tracking-wider uppercase mb-2 block">Section type <span
                        class="text-rose-500">*</span></label>
                <select
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                    id="sectionType" name="type" required onchange="updateSectionName(this)">
                    <option value="">Select type...</option>
                    <option value="reading_writing">Reading & Writing</option>
                    <option value="math">Math</option>
                </select>
            </div>

            <div class="flex items-center ml-2">
                <label class="flex items-center group cursor-pointer">
                    <input type="checkbox" name="is_public" value="1"
                        class="w-4 h-4 text-emerald-600 border-slate-300 bg-white rounded-md cursor-pointer">
                    <span
                        class="ml-2.5 text-xs font-extrabold text-slate-500 group-hover:text-emerald-600 uppercase tracking-wider">Public
                        visibility</span>
                </label>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex gap-4 items-start shadow-xs">
                <div
                    class="w-10 h-10 rounded-lg bg-amber-100 border border-amber-200 flex items-center justify-center shrink-0">
                    <i class="bi bi-info-circle-fill text-amber-600 text-lg"></i>
                </div>
                <div>
                    <h6 class="text-xs font-extrabold text-amber-800 uppercase tracking-wider mb-1">Standardized SAT
                        Structure</h6>
                    <p class="text-xs text-amber-700 leading-relaxed mb-0 font-medium">
                        Section order is fixed for Digital SAT: <strong class="text-amber-800 font-bold">Reading &
                            Writing</strong> (order 1), <strong class="text-amber-800 font-bold">Math</strong> (order
                        2). You can only add one section of each type per test.
                    </p>
                </div>
            </div>

            <div class="pt-4">
                <input type="hidden" id="sectionName" name="name" value="">
                <button type="submit"
                    class="w-full py-3.5 bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider cursor-pointer">
                    Create section
                </button>
            </div>
        </form>
    </x-ui.offcanvas>
</div>
