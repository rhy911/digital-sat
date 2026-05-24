@props(['tests'])

<div x-show="activeTab === 'tests'" id="tests" role="tabpanel" style="display: none;" x-transition.opacity.duration.300ms>
    <!-- Header Section -->
    <div class="flex mb-6">
        <div>
            <h4 class="text-xl font-extrabold text-white tracking-tight">Tests</h4>
            <p class="text-xs text-slate-400 font-medium">Manage your SAT tests and their configurations.</p>
        </div>
    </div>

    <div class="space-y-8">
        <!-- Create Form Card -->
        <div>
            <div class="w-full mx-auto max-w-4xl rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden glass-panel">
                <div class="px-6 py-4 border-b border-slate-800/80 bg-slate-950/40 flex items-center">
                    <h5 class="flex items-center gap-3 mb-0 text-white font-extrabold text-base">
                        <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                            <i class="bi bi-plus-circle-fill text-indigo-400"></i>
                        </div>
                        Create New Test
                    </h5>
                </div>
                <form id="testForm">
                    @csrf
                    <div class="px-6 py-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-6">
                            <div class="md:col-span-2.5 col-span-3">
                                <label for="testTitle" class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Test title <span class="text-rose-500">*</span></label>
                                <input type="text" class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none" id="testTitle" name="title" placeholder="e.g. Digital SAT Practice Test 1" required>
                            </div>
                            <div class="md:col-span-1.5 col-span-3">
                                <label for="testType" class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Test type <span class="text-rose-500">*</span></label>
                                <select class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')" id="testType" name="test_type" required>
                                    <option value="full_length" selected>Full Length (Standard)</option>
                                    <option value="short_test">Short Test</option>
                                    <option value="section_only">Section Only</option>
                                    <option value="module_only">Module Only</option>
                                </select>
                            </div>
                            <div class="md:col-span-1.5 col-span-3">
                                <label for="breakDuration" class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Break duration (min) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <input type="number" class="w-full pl-4 pr-12 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none" id="breakDuration" name="break_duration_minutes" value="10" required>
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-450 pointer-events-none"><i class="bi bi-clock"></i></span>
                                </div>
                            </div>
                            <div class="md:col-span-1.5 col-span-3">
                                <label for="testStatus" class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Initial status <span class="text-rose-500">*</span></label>
                                <select class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')" id="testStatus" name="status" required>
                                    <option value="active" selected>Active</option>
                                    <option value="draft">Draft</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                        </div>
                        <input type="hidden" id="totalDuration" name="total_duration_minutes" value="0">
                    </div>
                    <div class="px-6 py-4 bg-slate-950/40 border-t border-slate-800/80 flex justify-end">
                        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold rounded-xl shadow-lg shadow-indigo-600/25 hover:shadow-indigo-600/40 transform flex items-center gap-2.5 text-xs uppercase tracking-wider">
                            <i class="bi bi-plus-lg"></i>
                            Create Practice Test
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Empty State -->
        <div id="testsEmptyState" class="rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden {{ $tests->isEmpty() ? '' : 'hidden' }} glass-panel">
            <div class="p-16 text-center">
                <div class="mx-auto w-24 h-24 bg-indigo-500/10 border border-indigo-500/20 rounded-full flex items-center justify-center mb-6">
                    <i class="bi bi-journal-text text-4xl text-indigo-400"></i>
                </div>
                <h3 class="text-xl font-extrabold text-white mb-2">No tests created yet</h3>
                <p class="text-slate-400 mb-8 max-w-sm mx-auto text-sm leading-relaxed">Get started by creating your first full-length Digital SAT practice test to begin building your content.</p>
                <button type="button" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold rounded-xl shadow-lg shadow-indigo-600/20 hover:shadow-indigo-600/35 text-xs uppercase tracking-wider" onclick="document.getElementById('testTitle').focus();">
                    Create Your First Test
                </button>
            </div>
        </div>

        <!-- Table Container -->
        <div id="testsTableContainer" class="rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden {{ $tests->isEmpty() ? 'hidden' : '' }} glass-panel">
            <div class="px-6 py-4 border-b border-slate-800/80 bg-slate-950/40 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h5 class="font-extrabold text-white flex items-center gap-3 mb-0 text-base">
                    <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                        <i class="bi bi-list-ul text-indigo-400"></i>
                    </div>
                    Existing Practice Tests
                </h5>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-450"><i class="bi bi-search text-xs"></i></span>
                    <input type="text" class="pl-9 pr-4 py-2.5 w-full md:w-72 text-sm rounded-xl border border-slate-800/80 bg-slate-900/60 text-white placeholder-slate-550 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none" id="testsTableSearch" placeholder="Search tests...">
                </div>
            </div>
            <div class="p-0">
                <div id="testsTabulatorTable" class="w-full"></div>
                @php
                    $testsData = $tests->map(function($t) {
                        return [
                            'id' => $t->id,
                            'title' => $t->title,
                            'type' => ucfirst(str_replace('_', ' ', $t->test_type)),
                            'status' => $t->status,
                            'duration' => $t->total_duration_minutes,
                        ];
                    });
                @endphp
                <script>
                    window.__tdTestsData = @json($testsData);
                </script>
            </div>
        </div>
    </div>
</div>
