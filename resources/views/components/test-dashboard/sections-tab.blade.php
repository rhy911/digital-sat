@props(['tests'])

<div x-show="activeTab === 'sections'" id="sections" role="tabpanel" style="display: none;">
    <!-- Header Section -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 px-4">
        <div>
            <h3 class="text-xl font-extrabold text-white tracking-tight">Sections</h3>
            <p class="text-base text-slate-400 font-medium">Organize your tests into Reading & Writing and Math sections.</p>
        </div>
        <div class="flex gap-3">
            <button
                class="px-5 py-3 bg-linear-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-600/20 hover:shadow-emerald-600/35 flex items-center gap-2.5 cursor-pointer"
                x-on:click="$dispatch('open-offcanvas', 'createSectionOffcanvas')">
                <i class="bi bi-plus-lg text-xs"></i> Create Section
            </button>
        </div>
    </div>

    <div class="px-4 space-y-8">
        @php
            $hasSections = $tests->flatMap->sections->count() > 0;
        @endphp

        <!-- Empty State -->
        <div id="sectionsEmptyState"
            class="max-w-4xl mx-auto rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden {{ $hasSections ? 'hidden' : '' }} glass-panel">
            <div class="p-16 text-center">
                <div
                    class="mx-auto w-24 h-24 bg-emerald-500/10 border border-emerald-500/20 rounded-full flex items-center justify-center mb-6">
                    <i class="bi bi-puzzle-fill text-4xl text-emerald-400"></i>
                </div>
                <h3 class="text-xl font-extrabold text-white mb-2">No sections created yet</h3>
                <p class="text-slate-400 mb-8 max-w-sm mx-auto text-sm leading-relaxed">Create a section within your test to hold modules and questions.</p>
                <button type="button"
                    class="px-6 py-3 bg-linear-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold rounded-xl shadow-lg shadow-emerald-600/20 hover:shadow-emerald-600/35 text-xs uppercase tracking-wider"
                    x-on:click="$dispatch('open-offcanvas', 'createSectionOffcanvas')">
                    Create Your First Section
                </button>
            </div>
        </div>

        <!-- Table Container -->
        <div id="sectionsTableContainer"
            class="relative rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden {{ $hasSections ? '' : 'hidden' }} glass-panel">
            <div
                class="px-6 py-4 border-b border-slate-800/80 bg-slate-950/40 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h5 class="font-extrabold text-white flex items-center gap-3 mb-0 text-base">
                    <div
                        class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center">
                        <i class="bi bi-list-ul text-emerald-400"></i>
                    </div>
                    Existing Sections
                </h5>
                <div class="flex items-center gap-4 flex-wrap md:flex-nowrap">
                    @if(auth()->user()->role === 'teacher')
                        <div class="flex items-center gap-2 bg-slate-900/40 px-3 py-1.5 rounded-xl border border-slate-800/80">
                            <label for="sectionsShowSharedToggle" class="text-xs font-extrabold text-slate-400 cursor-pointer select-none uppercase tracking-wider">Show Shared</label>
                            <input type="checkbox" id="sectionsShowSharedToggle" class="w-4 h-4 text-emerald-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer sections-show-shared-toggle">
                        </div>
                    @endif
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                                class="bi bi-search text-xs"></i></span>
                        <input type="text"
                            class="pl-9 pr-4 py-2.5 w-full md:w-72 text-sm rounded-xl border border-slate-800/80 bg-slate-900/60 text-white placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200"
                            id="sectionsTableSearch" placeholder="Search sections...">
                    </div>
                </div>
            </div>
            <div class="p-0">
                <div id="sectionsTabulatorTable" class="w-full transition-opacity duration-200 opacity-0"></div>
                @php
                    $sectionsData = $tests->flatMap(function ($test) {
                        return $test->sections->map(function ($section) use ($test) {
                            return [
                                'id' => $section->id,
                                'test_title' => $test->title,
                                'name' => $section->name,
                                'type' => ucfirst(str_replace('_', ' ', $section->type)),
                                'order' => $section->order,
                                'created_by' => $section->created_by,
                                'created_by_name' => $section->creator ? ($section->creator->username ?? $section->creator->email) : 'Admin',
                                'created_at' => $section->created_at ? $section->created_at->format('d/m/y') : null,
                                'is_public' => (bool) $section->is_public,
                                'is_owner' => $section->created_by === auth()->id() || auth()->user()->role === 'admin',
                            ];
                        });
                    })->values();
                @endphp
                <script>
                    window.__tdSectionsData = @json($sectionsData);
                </script>
            </div>
        </div>
    </div>

    <!-- Create Section Offcanvas -->
    <x-ui.offcanvas id="createSectionOffcanvas" width="w-[480px]">
        <x-slot:titleContent>
            <i class="bi bi-puzzle-fill text-emerald-400 mr-3 text-xl"></i> Create New Section
        </x-slot:titleContent>
        <form id="sectionForm" class="space-y-6">
            @csrf
            <div>
                <label for="sectionTest"
                    class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Parent test <span class="text-rose-500">*</span></label>
                <select
                    class="form-select tom-select w-full bg-slate-900/60 border border-slate-800/80 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 rounded-xl"
                    id="sectionTest" name="test_id" required>
                    <option value="">Search test...</option>
                    @foreach($tests as $test)
                        <option value="{{ $test->id }}">{{ $test->title }} (ID:{{ $test->id }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="sectionType"
                    class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Section type <span class="text-rose-500">*</span></label>
                <select
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                    id="sectionType" name="type" required onchange="updateSectionName(this)">
                    <option value="">Select type...</option>
                    <option value="reading_writing">Reading & Writing</option>
                    <option value="math">Math</option>
                </select>
            </div>

            <div class="flex items-center ml-2">
                <label class="flex items-center group cursor-pointer">
                    <input type="checkbox" name="is_public" value="1" class="w-4 h-4 text-emerald-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer">
                    <span class="ml-2.5 text-xs font-extrabold text-slate-400 group-hover:text-emerald-400 uppercase tracking-wider">Public visibility</span>
                </label>
            </div>

            <div class="bg-amber-500/5 border border-amber-500/15 rounded-xl p-5 flex gap-4 items-start shadow-xl">
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0">
                    <i class="bi bi-info-circle-fill text-amber-400 text-lg"></i>
                </div>
                <div>
                    <h6 class="text-xs font-extrabold text-amber-400 uppercase tracking-wider mb-1">Standardized SAT Structure</h6>
                    <p class="text-xs text-amber-400/90 leading-relaxed mb-0 font-medium">
                        Section order is fixed for Digital SAT: <strong class="text-amber-400 font-bold">Reading & Writing</strong> (order 1), <strong class="text-amber-400 font-bold">Math</strong> (order 2). You can only add one section of each type per test.
                    </p>
                </div>
            </div>

            <div class="pt-4">
                <input type="hidden" id="sectionName" name="name" value="">
                <button type="submit"
                    class="w-full py-3.5 bg-linear-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold rounded-xl shadow-lg shadow-emerald-600/25 text-xs uppercase tracking-wider cursor-pointer">
                    Create Section
                </button>
            </div>
        </form>
    </x-ui.offcanvas>
</div>