@props(['tests', 'questions', 'questionsTotal'])

<!-- Existing Questions -->
<div class="rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden border-l-4 border-indigo-500 mb-6 glass-panel">
    <div class="px-6 py-4 bg-slate-950/40 border-b border-slate-800/80 flex flex-wrap justify-between items-center gap-3">
        <h5 class="font-extrabold text-white flex items-center gap-3 mb-0 text-base">
            <div class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                <i class="bi bi-database-fill-gear text-indigo-400 text-sm leading-none"></i>
            </div>
            Questions Pool &amp; Bank
        </h5>
        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-[10px] font-extrabold bg-amber-500/10 text-amber-400 border border-amber-500/20 shadow-lg uppercase tracking-wide" id="questionsPoolCountBadge">{{ $questionsTotal }} Total Questions</span>
    </div>
    <div class="p-6">
        <div class="flex flex-wrap gap-4 items-center justify-between mb-5 bg-slate-950/40 p-4 rounded-xl border border-slate-800/80 shadow-inner">
            <div class="flex flex-wrap gap-2.5 items-center flex-1">
                <div class="relative max-w-xs w-full">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                        <i class="bi bi-search text-sm"></i>
                    </span>
                    <input type="text" class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-800/80 bg-slate-900/60 text-white placeholder-slate-550 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none" id="questionsTableFilter" placeholder="Search stem text...">
                </div>

                <select class="px-3 py-2 text-xs rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 max-w-[130px]" id="questionsTableSectionFilter">
                    <option value="">All Sections</option>
                    <option value="reading_writing">R&amp;W</option>
                    <option value="math">Math</option>
                </select>

                <select class="px-3 py-2 text-xs rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 max-w-[130px]" id="questionsTableStatusFilter">
                    <option value="">All Status</option>
                    <option value="1">Complete</option>
                    <option value="0">Incomplete</option>
                </select>

                <select class="px-3 py-2 text-xs rounded-xl border border-slate-800/80 bg-slate-900/60 text-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 max-w-[240px] tom-select tom-select-filter" id="questionsTableModuleFilter">
                    <option value="">All Modules</option>
                    @foreach ($tests as $test)
                        @foreach ($test->sections as $section)
                            @foreach ($section->modules as $module)
                                <option value="{{ $module->id }}">
                                    {{ $test->title }} |
                                    {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod
                                    {{ $module->module_number }}
                                </option>
                            @endforeach
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="button" class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white rounded-xl shadow-lg shadow-indigo-600/20 hover:shadow-indigo-600/35 text-xs font-extrabold uppercase tracking-wider flex items-center gap-1.5 cursor-pointer" id="questionsTableFilterBtn">
                    <i class="bi bi-filter text-sm"></i> Apply Filters
                </button>
                <button type="button" class="px-4 py-2 bg-slate-900/60 border border-slate-800/80 text-slate-200 rounded-xl hover:bg-slate-850 hover:text-white text-xs font-extrabold uppercase tracking-wider flex items-center gap-1.5 cursor-pointer" id="questionsTableFilterClearBtn">
                    <i class="bi bi-x-circle text-sm"></i> Clear
                </button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-800/80 shadow-2xl">
            <table class="w-full text-left text-xs text-slate-350">
                <thead class="bg-slate-950/50 text-slate-400 border-b border-slate-800/80 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 font-extrabold text-[10px] text-slate-400 w-80 text-center">Id</th>
                        <th class="py-3 font-extrabold text-[10px] text-slate-400 w-80 text-center">Q. Number</th>
                        <th class="py-3 font-extrabold text-[10px] text-slate-400 w-80 text-center">Section</th>
                        <th class="py-3 font-extrabold text-[10px] text-slate-400">Stem Snippet</th>
                        <th class="py-3 font-extrabold text-[10px] text-slate-400 w-110 text-center">Usage</th>
                        <th class="py-3 font-extrabold text-[10px] text-slate-400 w-180">Domain</th>
                        <th class="py-3 font-extrabold text-[10px] text-slate-400 w-100 text-center">Difficulty</th>
                        <th class="py-3 font-extrabold text-[10px] text-slate-400 text-right w-120">Actions</th>
                    </tr>
                </thead>
                <tbody id="questionsTableBody" class="divide-y divide-slate-800/40 bg-transparent">
                    @forelse($questions as $question)
                        <tr class="hover:bg-indigo-500/5 border-b border-slate-800/40">
                            <td class="px-5 py-3.5 font-mono font-bold text-slate-500 text-center">{{ $question->id }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="font-bold text-slate-200">{{ $question->question_number ?? '-' }}</span>
                                    @if (!$question->is_complete)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-extrabold bg-rose-500/10 text-rose-400 border border-rose-500/20 uppercase tracking-wide" title="Missing Domain or Difficulty">
                                            <i class="bi bi-exclamation-triangle-fill mr-1 text-[9px]"></i> Incomplete
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if ($question->section_type === 'reading_writing')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wide">R&amp;W</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase tracking-wide">Math</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-350 text-truncate font-medium max-w-280" title="{{ strip_tags($question->stem) }}">
                                {{ Str::limit(strip_tags($question->stem), 50) }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if ($question->is_pretest)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-rose-500/10 text-rose-400 border border-rose-500/20 uppercase tracking-wide">
                                        Pretest
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-slate-800/60 text-slate-350 border border-slate-700/60 uppercase tracking-wide">Active</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5"><span class="text-slate-400 font-semibold font-mono text-[11px]">{{ $question->skill_domain }}</span></td>
                            <td class="px-5 py-3.5 text-center">
                                @if (strtolower($question->difficulty) === 'easy')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase tracking-wide">{{ ucfirst($question->difficulty) }}</span>
                                @elseif(strtolower($question->difficulty) === 'medium')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase tracking-wide">{{ ucfirst($question->difficulty) }}</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-rose-500/10 text-rose-400 border border-rose-500/20 uppercase tracking-wide">{{ ucfirst($question->difficulty) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button class="px-2.5 py-1 border border-indigo-500/20 text-indigo-400 bg-indigo-500/5 hover:bg-indigo-500/15 rounded-lg text-xs font-bold flex items-center gap-1 edit-question-btn cursor-pointer" data-id="{{ $question->id }}">
                                        <i class="bi bi-pencil-square text-xs leading-none"></i> Edit
                                    </button>
                                    <button class="w-7 h-7 flex items-center justify-center border border-rose-500/20 text-rose-455 bg-rose-500/5 hover:bg-rose-500/15 rounded-full delete-question-btn cursor-pointer" data-id="{{ $question->id }}" title="Delete">
                                        <i class="bi bi-trash text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 rounded-full bg-slate-900/40 border border-slate-800/80 flex items-center justify-center mb-3">
                                        <i class="bi bi-database-fill-x text-2xl text-slate-450"></i>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-350">No questions found</p>
                                    <p class="text-xs text-slate-550 mt-1">Populate your bank by importing items above!</p>
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
