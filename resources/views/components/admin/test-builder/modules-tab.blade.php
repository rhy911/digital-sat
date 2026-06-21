@props(['tests', 'allModules'])

<div x-show="activeTab === 'modules'" id="modules" role="tabpanel" aria-labelledby="modules-tab"
    :aria-hidden="activeTab === 'modules' ? 'false' : 'true'" :class="{ 'active': activeTab === 'modules' }"
    class="tab-pane" style="display: none;">
    <!-- Existing Modules Listing -->
    <div id="modulesTableContainer"
        class="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div
            class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h5 class="font-bold text-slate-800 mb-0 text-sm">All modules</h5>
            <div class="flex items-center gap-4 flex-wrap md:flex-nowrap">
                <button class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700" x-on:click="$dispatch('open-offcanvas', 'createModuleOffcanvas')"><i class="bi bi-plus-lg" aria-hidden="true"></i>Create module</button>
                @if(auth()->user()->role === 'teacher')
                    <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-lg border border-slate-200">
                        <label for="modulesShowSharedToggle"
                            class="text-xs font-bold text-slate-600 cursor-pointer select-none">Show shared</label>
                        <input type="checkbox" id="modulesShowSharedToggle"
                            class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded cursor-pointer modules-show-shared-toggle">
                    </div>
                @endif
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                            class="bi bi-search text-xs"></i></span>
                    <input type="text"
                        class="pl-9 pr-4 py-2 w-full md:w-64 text-sm rounded-lg border border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition-all duration-150"
                        id="modulesTableSearch" placeholder="Search by module code...">
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="teacher-data-table teacher-modules-table w-full text-left text-xs text-slate-600">
                <colgroup>
                    <col class="col-id">

                    <col class="col-target">
                    <col class="col-type">
                    <col class="col-difficulty">
                    <col class="col-owner">
                    <col class="col-public">
                    <col class="col-time">
                    <col class="col-count">
                    <col class="col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th class="text-center">Id</th>

                        <th>Target</th>
                        <th class="text-center">Type</th>
                        <th class="text-center">Difficulty</th>
                        <th>Owner</th>
                        <th class="text-center">Public</th>
                        <th class="text-center">Time</th>
                        <th class="text-center">Q's</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="modulesTableBody" class="divide-y divide-slate-100 bg-transparent">
                    @forelse($allModules as $module)
                        @php
                            $isOwner = $module->created_by === auth()->id() || auth()->user()->role === 'admin';
                            if (auth()->user()->role === 'teacher' && !$isOwner) {
                                continue;
                            }
                            $creatorName = $module->creator ? ($module->creator->username ?? $module->creator->email) : 'Admin';
                        @endphp
                        <tr
                            class="{{ !$isOwner ? 'row-shared' : '' }}">
                            <td class="px-6 py-4 font-normal text-slate-400">{{ $module->id }}</td>

                            <td class="px-6 py-4">
                                @if ($module->sections->isEmpty())
                                    <span class="status-chip status-chip-readonly">
                                        <i class="bi bi-unlock mr-1.5"></i> Standalone
                                    </span>
                                @else
                                    <div class="flex flex-col gap-1.5">
                                        @foreach ($module->sections as $sec)
                                            <div>
                                                <span class="status-chip status-chip-shared">
                                                    <i class="bi bi-tag mr-1.5"></i> {{ $sec->test->title ?? 'Test' }}
                                                    &raquo; <span class="ml-1 opacity-90 text-slate-800 font-medium normal-case">{{ $sec->name }}</span>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="status-chip status-chip-readonly">Module {{ $module->module_number }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $diffColors = [
                                        'hard' => 'status-chip-archived text-rose-700 bg-rose-50 border-rose-100',
                                        'easy' => 'status-chip-active text-emerald-700 bg-emerald-50 border-emerald-100',
                                        'standard' => 'status-chip-shared text-indigo-700 bg-indigo-50 border-indigo-100',
                                    ];
                                    $colorClass = $diffColors[$module->difficulty_level] ?? 'status-chip-readonly';
                                @endphp
                                <span class="status-chip border {{ $colorClass }}">
                                    {{ ucfirst($module->difficulty_level) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-medium text-slate-600 truncate max-w-[110px] block"
                                    title="{{ $creatorName }}">{{ $creatorName }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center">
                                    @if($isOwner)
                                        @php
                                            $title = $module->is_public ? 'Public (Click to make Private)' : 'Private (Click to make Public)';
                                        @endphp
                                        <input type="checkbox" data-id="{{ $module->id }}"
                                            class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded cursor-pointer module-public-toggle"
                                            {{ $module->is_public ? 'checked' : '' }} title="{{ $title }}" aria-label="Toggle public visibility">
                                    @else
                                        <input type="checkbox" checked disabled
                                            class="w-4 h-4 text-slate-400 border-slate-200 bg-slate-100 rounded cursor-not-allowed opacity-60"
                                            title="Shared (View only)" aria-label="Shared resource">
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-700 text-center">{{ $module->duration_minutes }}<span
                                    class="text-[10px] ml-0.5 opacity-50 uppercase tracking-tighter">min</span></td>
                            <td class="px-6 py-4 font-bold text-slate-750 text-xs text-center">
                                <span class="text-slate-900 font-extrabold">{{ $module->questions_count ?? 0 }}</span>
                                <span class="text-slate-400 font-normal">/ {{ $module->total_questions }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($isOwner)
                                    <div class="actions-dropdown">
                                        <button type="button" class="px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 cursor-pointer hover:bg-slate-50 flex items-center gap-1" data-dropdown-trigger="true" aria-expanded="false" aria-label="Toggle actions menu">
                                            Actions <i class="bi bi-chevron-down text-[10px]"></i>
                                        </button>
                                        <div class="dropdown-menu hidden">
                                            <button type="button" class="dropdown-item clone-module-btn" data-id="{{ $module->id }}"><i class="bi bi-copy mr-2"></i> Clone</button>
                                            <button type="button" class="dropdown-item text-danger delete-module-btn" data-id="{{ $module->id }}"><i class="bi bi-trash mr-2"></i> Delete</button>
                                        </div>
                                    </div>
                                @else
                                    <span class="status-chip status-chip-readonly">Read-Only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div
                                        class="w-20 h-20 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mb-6">
                                        <i class="bi bi-inbox text-3xl text-slate-400"></i>
                                    </div>
                                    <h4 class="text-base font-extrabold text-slate-800">No modules found</h4>
                                    <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto leading-relaxed">Create one module, then link it to any section that should use it.</p>
                                    <button
                                        class="mt-6 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg shadow-sm cursor-pointer"
                                        x-on:click="$dispatch('open-offcanvas', 'createModuleOffcanvas')">
                                        Create module
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="modulesPoolPagination" class="mt-0 border-t border-slate-200 bg-slate-50 p-4"></div>
    </div>

    <!-- Create Module Offcanvas -->
    <x-ui.offcanvas id="createModuleOffcanvas" width="w-[480px]">
        <x-slot:titleContent>
            <i class="bi bi-folder-plus text-indigo-600 mr-3 text-xl"></i> Create module
        </x-slot:titleContent>
        <form id="moduleForm" class="space-y-6">
            @csrf
            <div>
                <label for="moduleTest"
                    class="text-xs font-extrabold text-slate-500 tracking-wider uppercase mb-2 block">Target Test <span
                        class="text-slate-500 font-normal normal-case">(Optional)</span></label>
                <select class="form-select tom-select w-full bg-white border border-slate-200 text-slate-800 text-sm placeholder-slate-400 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 rounded-xl"
                    id="moduleTest" name="test_id">
                    <option value="">No test (Standalone reusable module)</option>
                    @foreach ($tests as $test)
                        <option value="{{ $test->id }}">{{ $test->title }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-slate-550 mt-2 italic flex items-center gap-1.5 font-medium">
                    <i class="bi bi-info-circle text-indigo-500"></i> Section will be auto-generated inside the test
                </p>
            </div>

            <div>
                <label for="moduleSectionType"
                    class="text-xs font-extrabold text-slate-500 tracking-wider uppercase mb-2 block">Section Type <span
                        class="text-rose-500">*</span></label>
                <select
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                    id="moduleSectionType" name="section_type" required onchange="applyModuleDefaults(this)">
                    <option value="reading_writing" data-type="reading_writing">Reading and Writing</option>
                    <option value="math" data-type="math">Math</option>
                </select>
            </div>

            <div>
                <label for="moduleKey"
                    class="text-xs font-extrabold text-slate-500 tracking-wider uppercase mb-2 block">Module Key /
                    Unique Code <span class="text-slate-500 font-normal normal-case">(Optional)</span></label>
                <input type="text"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm placeholder-slate-400 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 font-mono font-bold"
                    id="moduleKey" name="key" placeholder="e.g. RW_M1_STANDARD_01">
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label for="moduleNumber"
                        class="text-xs font-extrabold text-slate-500 tracking-wider uppercase mb-2 block">Module Type #
                        <span class="text-rose-500">*</span></label>
                    <select
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                        id="moduleNumber" name="module_number" required>
                        <option value="1">1 (Standard)</option>
                        <option value="2">2 (Adaptive)</option>
                    </select>
                </div>
                <div>
                    <label for="difficultyLevel"
                        class="text-xs font-extrabold text-slate-500 tracking-wider uppercase mb-2 block">Difficulty
                        <span class="text-rose-500">*</span></label>
                    <select
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                        id="difficultyLevel" name="difficulty_level" required>
                        <option value="standard">Standard (M1)</option>
                        <option value="easy">Easy (M2)</option>
                        <option value="hard">Hard (M2)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label for="moduleDuration"
                        class="text-xs font-extrabold text-slate-500 tracking-wider uppercase mb-2 block">Duration
                        (min) <span class="text-rose-500">*</span></label>
                    <input type="number"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm placeholder-slate-400 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200"
                        id="moduleDuration" name="duration_minutes" value="32" required>
                </div>
                <div>
                    <label for="totalQuestions"
                        class="text-xs font-extrabold text-slate-500 tracking-wider uppercase mb-2 block">Questions
                        <span class="text-rose-500">*</span></label>
                    <input type="number"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 text-sm placeholder-slate-400 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200"
                        id="totalQuestions" name="total_questions" value="27" required>
                </div>
            </div>

            <div class="flex items-center">
                <label class="flex items-center group cursor-pointer">
                    <input type="checkbox" name="is_public" value="1"
                        class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded-md cursor-pointer">
                    <span
                        class="ml-2.5 text-xs font-extrabold text-slate-500 group-hover:text-indigo-600 uppercase tracking-wider">Public
                        visibility</span>
                </label>
            </div>

            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 flex gap-3 shadow-xs">
                <i class="bi bi-info-circle-fill text-indigo-600 text-base shrink-0 mt-0.5"></i>
                <p class="text-[11px] text-indigo-800 leading-relaxed mb-0 font-medium">Standalone modules are reusable
                    and can be associated with multiple test sections later.</p>
            </div>

            <div class="pt-4">
                <button type="submit"
                    class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider cursor-pointer">
                    Create module
                </button>
            </div>
        </form>
    </x-ui.offcanvas>


</div>
