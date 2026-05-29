@props(['tests'])

<div x-show="activeTab === 'tests'" id="tests" role="tabpanel" style="display: none;">
    <!-- Header Section -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 px-4">
        <div>
            <h3 class="text-xl font-extrabold text-white tracking-tight">Practice Tests</h3>
            <p class="text-base text-slate-400 font-medium">Manage your SAT tests and their configurations.</p>
        </div>
        <div class="flex gap-3">
            <button
                class="px-5 py-3 bg-linear-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-indigo-600/20 hover:shadow-indigo-600/35 flex items-center gap-2.5 cursor-pointer"
                x-on:click="$dispatch('open-offcanvas', 'createTestOffcanvas')">
                <i class="bi bi-plus-lg text-xs"></i> Create Test
            </button>
        </div>
    </div>

    <div class="px-4 space-y-8">
        <!-- Empty State -->
        <div id="testsEmptyState"
            class="max-w-4xl mx-auto rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden {{ $tests->isEmpty() ? '' : 'hidden' }} glass-panel">
            <div class="p-16 text-center">
                <div
                    class="mx-auto w-24 h-24 bg-indigo-500/10 border border-indigo-500/20 rounded-full flex items-center justify-center mb-6">
                    <i class="bi bi-journal-text text-4xl text-indigo-400"></i>
                </div>
                <h3 class="text-xl font-extrabold text-white mb-2">No tests created yet</h3>
                <p class="text-slate-400 mb-8 max-w-sm mx-auto text-sm leading-relaxed">Get started by creating your
                    first full-length Digital SAT practice test to begin building your content.</p>
                <button type="button"
                    class="px-6 py-3 bg-linear-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold rounded-xl shadow-lg shadow-indigo-600/20 hover:shadow-indigo-600/35 text-xs uppercase tracking-wider"
                    x-on:click="$dispatch('open-offcanvas', 'createTestOffcanvas')">
                    Create Your First Test
                </button>
            </div>
        </div>

        <!-- Table Container -->
        <div id="testsTableContainer"
            class="relative rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden {{ $tests->isEmpty() ? 'hidden' : '' }} glass-panel">
            <div
                class="px-6 py-4 border-b border-slate-800/80 bg-slate-950/40 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h5 class="font-extrabold text-white flex items-center gap-3 mb-0 text-base">
                    <div
                        class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                        <i class="bi bi-list-ul text-indigo-400"></i>
                    </div>
                    Existing Practice Tests
                </h5>
                <div class="flex items-center gap-4 flex-wrap md:flex-nowrap">
                    @if(auth()->user()->role === 'teacher')
                        <div
                            class="flex items-center gap-2 bg-slate-900/40 px-3 py-1.5 rounded-xl border border-slate-800/80">
                            <label for="testsShowSharedToggle"
                                class="text-xs font-extrabold text-slate-400 cursor-pointer select-none uppercase tracking-wider">Show
                                Shared</label>
                            <input type="checkbox" id="testsShowSharedToggle"
                                class="w-4 h-4 text-indigo-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer tests-show-shared-toggle">
                        </div>
                    @endif
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                                class="bi bi-search text-xs"></i></span>
                        <input type="text"
                            class="pl-9 pr-4 py-2.5 w-full md:w-72 text-sm rounded-xl border border-slate-800/80 bg-slate-900/60 text-white placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200"
                            id="testsTableSearch" placeholder="Search tests...">
                    </div>
                </div>
            </div>
            <div class="p-0">
                <div id="testsTabulatorTable" class="w-full transition-opacity duration-200 opacity-0"></div>
                @php
                    $testsData = $tests->map(function ($t) {
                        return [
                            'id' => $t->id,
                            'title' => $t->title,
                            'type' => ucfirst(str_replace('_', ' ', $t->test_type)),
                            'status' => $t->status,
                            'duration' => $t->total_duration_minutes,
                            'created_by' => $t->created_by,
                            'created_by_name' => $t->creator ? ($t->creator->username ?? $t->creator->email) : 'Admin',
                            'created_at' => $t->created_at ? $t->created_at->format('d/m/y') : null,
                            'is_public' => (bool) $t->is_public,
                            'is_owner' => $t->created_by === auth()->id() || auth()->user()->role === 'admin',
                        ];
                    });
                @endphp
                <script>
                    window.__tdTestsData = @json($testsData);
                </script>
            </div>
        </div>
    </div>

    <!-- Create Test Offcanvas -->
    <x-ui.offcanvas id="createTestOffcanvas" width="w-[480px]">
        <x-slot:titleContent>
            <i class="bi bi-journal-plus text-indigo-400 mr-3 text-xl"></i> Create New Test
        </x-slot:titleContent>
        <form id="testForm" class="space-y-6">
            @csrf
            <div>
                <label for="testTitle"
                    class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Test
                    title <span class="text-rose-500">*</span></label>
                <input type="text"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200"
                    id="testTitle" name="title" placeholder="e.g. Digital SAT Practice Test 1" required>
            </div>

            <div class="grid grid-cols-[6fr_4fr] gap-6">
                <div>
                    <label for="testType"
                        class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Test
                        type <span class="text-rose-500">*</span></label>
                    <select
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                        id="testType" name="test_type" required>
                        <option value="full_length" selected>Full Length (Standard)</option>
                        <option value="short_test">Short Test</option>
                        <option value="section_only">Section Only</option>
                        <option value="module_only">Module Only</option>
                    </select>
                </div>
                <div>
                    <label for="breakDuration"
                        class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Break
                        duration (min) <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <input type="number"
                            class="w-full pl-4 pr-12 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200"
                            id="breakDuration" name="break_duration_minutes" value="10" required>
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"><i
                                class="bi bi-clock"></i></span>
                    </div>
                </div>
            </div>

            <div>
                <label for="testStatus"
                    class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Initial
                    status <span class="text-rose-500">*</span></label>
                <select
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                    id="testStatus" name="status" required>
                    <option value="active" selected>Active</option>
                    <option value="draft">Draft</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            <div class="flex items-center ml-2">
                <label class="flex items-center group cursor-pointer">
                    <input type="checkbox" name="is_public" value="1"
                        class="w-4 h-4 text-indigo-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer">
                    <span
                        class="ml-2.5 text-xs font-extrabold text-slate-400 group-hover:text-indigo-400 uppercase tracking-wider">Public
                        visibility</span>
                </label>
            </div>

            <input type="hidden" id="totalDuration" name="total_duration_minutes" value="0">

            <div class="pt-4">
                <button type="submit"
                    class="w-full py-3.5 bg-linear-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold rounded-xl shadow-lg shadow-indigo-600/25 text-xs uppercase tracking-wider cursor-pointer">
                    Create Practice Test
                </button>
            </div>
        </form>
    </x-ui.offcanvas>
</div>