@props(['tests', 'questions', 'questionsTotal'])

<!-- Existing Questions -->
<div id="questionsTableContainer"
    class="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-6">
    <div
        class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-wrap justify-between items-center gap-3">
        <h5 class="font-bold text-slate-800 mb-0 text-sm">All questions</h5>
        <span
            class="status-chip status-chip-readonly"
            id="questionsPoolCountBadge">{{ $questionsTotal }} questions</span>
    </div>
    <div class="p-6">
        <div
            class="questions-filter-row flex flex-wrap gap-3 items-center justify-between mb-5 bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div class="questions-filter-inputs flex flex-wrap gap-2.5 items-center flex-1">
                <div class="relative w-full max-w-xs">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="bi bi-search text-sm"></i>
                    </span>
                    <input type="text"
                        class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-200 bg-white text-slate-800 placeholder-slate-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition-all duration-150"
                        id="questionsTableFilter" placeholder="Search question text...">
                </div>

                @if(auth()->user()->role === 'teacher')
                    <div class="flex items-center gap-2 bg-white px-3 py-2 rounded-lg border border-slate-200">
                        <label for="questionsShowSharedToggle" class="text-xs font-bold text-slate-600 cursor-pointer select-none">Show shared</label>
                        <input type="checkbox" id="questionsShowSharedToggle" class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded cursor-pointer questions-show-shared-toggle">
                    </div>
                @endif

                <select
                    class="px-3 py-2 text-xs rounded-lg border border-slate-200 bg-white text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none w-full max-w-[130px]"
                    id="questionsTableSectionFilter">
                    <option value="">All sections</option>
                    <option value="reading_writing">R&amp;W</option>
                    <option value="math">Math</option>
                </select>

                <select
                    class="px-3 py-2 text-xs rounded-lg border border-slate-200 bg-white text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none w-full max-w-[130px]"
                    id="questionsTableStatusFilter">
                    <option value="">All status</option>
                    <option value="1">Complete</option>
                    <option value="0">Incomplete</option>
                </select>

                <select
                    class="text-xs rounded-lg border border-slate-200 bg-white text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none w-full max-w-xs tom-select tom-select-filter"
                    id="questionsTableModuleFilter">
                    <option value="">All modules</option>
                    @php
                        $hasModules = false;
                        foreach($tests as $test) {
                            foreach($test->sections as $section) {
                                foreach($section->modules as $module) {
                                    if (auth()->user()->role !== 'teacher' || $module->created_by === auth()->id()) {
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
                                @if(auth()->user()->role !== 'teacher' || $module->created_by === auth()->id())
                                    <option value="{{ $module->id }}">
                                        {{ $test->title }} |
                                        {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod
                                        {{ $module->module_number }}
                                    </option>
                                @endif
                            @endforeach
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="questions-filter-actions flex gap-2">
                <button type="button"
                    class="min-h-10 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 cursor-pointer transition-colors duration-150"
                    id="questionsTableFilterBtn">
                    <i class="bi bi-filter text-sm"></i> Apply filters
                </button>
                <button type="button"
                    class="min-h-10 px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 text-xs font-bold flex items-center gap-1.5 cursor-pointer transition-colors duration-150"
                    id="questionsTableFilterClearBtn">
                    <i class="bi bi-x-circle text-sm"></i> Clear
                </button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200 shadow-sm bg-white">
            <table class="teacher-data-table teacher-questions-table w-full text-left text-xs text-slate-600">
                <thead
                    class="bg-slate-50 text-slate-400 border-b border-slate-200 font-semibold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-2.5 font-semibold text-[10px] text-slate-400 w-16 text-center tracking-wider">Id</th>
                        <th class="py-2.5 font-semibold text-[10px] text-slate-400 w-24 text-center tracking-wider">Q. Number</th>
                        <th class="py-2.5 font-semibold text-[10px] text-slate-400 w-20 text-center tracking-wider">Section</th>
                        <th class="px-5 py-2.5 font-semibold text-[10px] text-slate-400 stem-column tracking-wider" title="Stem Snippet">Stem Snippet</th>
                        <th class="py-2.5 font-semibold text-[10px] text-slate-400 w-24 text-center tracking-wider">Usage</th>
                        <th class="px-5 py-2.5 font-semibold text-[10px] text-slate-400 w-44 tracking-wider">Domain</th>
                        <th class="py-2.5 font-semibold text-[10px] text-slate-400 w-28 text-center tracking-wider">Difficulty</th>
                        <th class="py-2.5 font-semibold text-[10px] text-slate-400 text-center w-36 tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="questionsTableBody" class="divide-y divide-slate-100 bg-transparent">
                    @forelse($questions as $question)
                        @php
                            $isOwner = $question->created_by === auth()->id() || auth()->user()->role === 'admin';
                        @endphp
                        <tr class="{{ !$isOwner ? 'row-shared' : '' }}">
                            <td class="px-5 py-3.5 font-mono font-normal text-slate-400 text-center">{{ $question->id }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="font-medium text-slate-600">{{ $question->question_number ?? '-' }}</span>
                                    @if (!$question->is_complete)
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-100 uppercase tracking-wide"
                                            title="Missing Domain or Difficulty">
                                            <i class="bi bi-exclamation-triangle-fill mr-1 text-[9px]"></i> Incomplete
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if ($question->section_type === 'reading_writing')
                                    <span
                                        class="status-chip status-chip-shared">R&amp;W</span>
                                @else
                                    <span
                                        class="status-chip status-chip-active">Math</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-500 font-normal stem-column"
                                title="{{ strip_tags($question->stem) }}">
                                {{ strip_tags($question->stem) }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if ($question->is_pretest)
                                    <span
                                        class="status-chip text-rose-700 bg-rose-50 border border-rose-100">
                                        Pretest
                                    </span>
                                @else
                                    <span
                                        class="status-chip status-chip-readonly">Active</span>
                                @endif
                            </td>
                             <td class="px-5 py-3.5"><span
                                    class="text-slate-600 font-medium text-[11px] block truncate" title="{{ ucwords(str_replace('_', ' ', $question->skill_domain)) }}">{{ ucwords(str_replace('_', ' ', $question->skill_domain)) }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if (strtolower($question->difficulty) === 'easy')
                                    <span
                                        class="status-chip text-emerald-700 bg-emerald-50 border border-emerald-100">{{ ucfirst($question->difficulty) }}</span>
                                @elseif(strtolower($question->difficulty) === 'medium')
                                    <span
                                        class="status-chip text-amber-700 bg-amber-50 border border-amber-100">{{ ucfirst($question->difficulty) }}</span>
                                @else
                                    <span
                                        class="status-chip text-rose-700 bg-rose-50 border border-rose-100">{{ ucfirst($question->difficulty) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <div class="flex justify-center gap-1.5">
                                    @if ($isOwner)
                                        <div class="actions-dropdown">
                                            <button type="button" class="px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 cursor-pointer hover:bg-slate-50 flex items-center gap-1" data-dropdown-trigger="true" aria-expanded="false" aria-label="Toggle actions menu">
                                                Actions <i class="bi bi-chevron-down text-[10px]"></i>
                                            </button>
                                            <div class="dropdown-menu hidden">
                                                <button type="button" class="dropdown-item edit-question-btn" data-id="{{ $question->id }}"><i class="bi bi-pencil mr-2"></i> Edit</button>
                                                <button type="button" class="dropdown-item text-danger delete-question-btn" data-id="{{ $question->id }}"><i class="bi bi-trash mr-2"></i> Delete</button>
                                            </div>
                                        </div>
                                    @else
                                        <button
                                            class="px-2.5 py-1.5 border border-slate-200 text-slate-600 bg-white hover:bg-slate-50 rounded-lg text-xs font-bold flex items-center gap-1 edit-question-btn cursor-pointer"
                                            data-id="{{ $question->id }}" aria-label="View question details">
                                            <i class="bi bi-eye text-xs leading-none"></i> View
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <div
                                        class="w-12 h-12 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mb-3">
                                        <i class="bi bi-database-fill-x text-2xl text-slate-400"></i>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-600">No questions found</p>
                                    <p class="text-xs text-slate-400 mt-1">Populate your bank by importing items above!
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="questionsPoolPagination" class="mt-5 flex justify-center"></div>
    </div>
</div>
