@props(['tests'])

<div x-show="activeTab === 'sections'" id="sections" role="tabpanel" style="display: none;" x-transition.opacity.duration.300ms>
    <!-- Header Section -->
    <div class="flex mb-6">
        <div>
            <h4 class="text-xl font-extrabold text-white tracking-tight">Sections</h4>
            <p class="text-xs text-slate-400 font-medium">Organize your tests into Reading & Writing and Math sections.</p>
        </div>
    </div>

    <div class="space-y-8">
        <!-- Create Form Card -->
        <div class="max-w-4xl mx-auto w-full">
            <div class="rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden glass-panel">
                <div class="px-6 py-4 border-b border-slate-800/80 bg-slate-950/40 flex items-center justify-between">
                    <h5 class="font-extrabold text-white flex items-center gap-3 mb-0 text-base">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center">
                            <i class="bi bi-plus-circle-fill text-emerald-400"></i>
                        </div>
                        Create New Section
                    </h5>
                </div>
                <form id="sectionForm">
                    @csrf
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sectionTest" class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Parent test <span class="text-rose-500">*</span></label>
                                <select class="form-select tom-select bg-slate-900/60 border border-slate-800/80 text-white rounded-xl py-2.5" id="sectionTest" name="test_id" required>
                                    <option value="">Search test...</option>
                                    @foreach($tests as $test)
                                    <option value="{{ $test->id }}">{{ $test->title }} (ID:{{ $test->id }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="sectionType" class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Section type <span class="text-rose-500">*</span></label>
                                <select class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em] text-sm" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')" id="sectionType" name="type" required onchange="updateSectionName(this)">
                                    <option value="">Select type...</option>
                                    <option value="reading_writing">Reading & Writing</option>
                                    <option value="math">Math</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="bg-amber-500/5 border border-amber-500/15 rounded-xl p-5 flex gap-4 items-start shadow-xl">
                            <div class="w-10 h-10 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0">
                                <i class="bi bi-info-circle-fill text-amber-450 text-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-xs font-extrabold text-amber-450 uppercase tracking-wider mb-1">Standardized SAT Structure</h6>
                                <p class="text-xs text-amber-350/90 leading-relaxed mb-0">
                                    Section order is fixed for Digital SAT: <strong class="text-amber-400 font-bold">Reading & Writing</strong> (order 1), <strong class="text-amber-400 font-bold">Math</strong> (order 2). You can only add one section of each type per test.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-slate-950/40 border-t border-slate-800/80 flex justify-end">
                        <input type="hidden" id="sectionName" name="name" value="">
                        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-550 hover:to-teal-550 text-white font-extrabold rounded-xl shadow-lg shadow-emerald-600/20 hover:shadow-emerald-600/35 transform flex items-center gap-2.5 text-xs uppercase tracking-wider">
                            <i class="bi bi-plus-lg"></i>
                            Create Section
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $hasSections = $tests->flatMap->sections->count() > 0;
        @endphp

        <!-- Empty State -->
        <div id="sectionsEmptyState" class="rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden {{ $hasSections ? 'hidden' : '' }} glass-panel">
            <div class="p-16 text-center">
                <div class="mx-auto w-24 h-24 bg-emerald-500/10 border border-emerald-500/20 rounded-full flex items-center justify-center mb-6">
                    <i class="bi bi-puzzle-fill text-4xl text-emerald-400"></i>
                </div>
                <h3 class="text-xl font-extrabold text-white mb-2">No sections created yet</h3>
                <p class="text-slate-400 mb-8 max-w-sm mx-auto text-sm leading-relaxed">Create a section within your test to hold modules and questions.</p>
                <button type="button" class="px-6 py-3 bg-gradient-to-r from-emerald-650 to-teal-600 hover:from-emerald-550 hover:to-teal-550 text-white font-extrabold rounded-xl shadow-lg shadow-emerald-600/20 hover:shadow-emerald-600/35 text-xs uppercase tracking-wider" onclick="document.getElementById('sectionTest').focus();">
                    Create Your First Section
                </button>
            </div>
        </div>

        <!-- Table Container -->
        <div id="sectionsTableContainer" class="rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden {{ $hasSections ? '' : 'hidden' }} glass-panel">
            <div class="px-6 py-4 border-b border-slate-800/80 bg-slate-950/40 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h5 class="font-extrabold text-white flex items-center gap-3 mb-0 text-base">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center">
                        <i class="bi bi-list-ul text-emerald-400"></i>
                    </div>
                    Existing Sections
                </h5>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-450"><i class="bi bi-search text-xs"></i></span>
                    <input type="text" class="pl-9 pr-4 py-2.5 w-full md:w-72 text-sm rounded-xl border border-slate-800/80 bg-slate-900/60 text-white placeholder-slate-550 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none" id="sectionsTableSearch" placeholder="Search sections...">
                </div>
            </div>
            <div class="p-0">
                <div id="sectionsTabulatorTable" class="w-full"></div>
                @php
                    $sectionsData = $tests->flatMap(function($test) {
                        return $test->sections->map(function($section) use ($test) {
                            return [
                                'id' => $section->id,
                                'test_title' => $test->title,
                                'name' => $section->name,
                                'type' => ucfirst(str_replace('_', ' ', $section->type)),
                                'order' => $section->order,
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
</div>
