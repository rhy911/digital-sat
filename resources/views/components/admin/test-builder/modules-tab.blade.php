@props(['tests', 'allModules'])

<div x-show="activeTab === 'modules'" id="modules" role="tabpanel" style="display: none;">
    <!-- Header Actions -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6">
        <div>
            <h3 class="text-xl font-extrabold text-white tracking-tight">Reusable Modules</h3>
            <p class="text-base text-slate-400 font-medium">Manage and link your question modules across different
                tests.
            </p>
        </div>
        <div class="flex gap-3">
            <button
                class="px-5 py-3 bg-slate-900/60 border border-slate-800/80 text-slate-200 font-extrabold text-xs uppercase tracking-wider rounded-xl hover:bg-slate-800 hover:text-white shadow-lg flex items-center gap-2.5 cursor-pointer"
                x-on:click="$dispatch('open-offcanvas', 'linkModuleOffcanvas')">
                <i class="bi bi-link-45deg text-sm"></i> Link Module
            </button>
            <button
                class="px-5 py-3 bg-linear-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-indigo-600/20 hover:shadow-indigo-600/35 flex items-center gap-2.5 cursor-pointer"
                x-on:click="$dispatch('open-offcanvas', 'createModuleOffcanvas')">
                <i class="bi bi-plus-lg text-xs"></i> Create Module
            </button>
        </div>
    </div>

    <!-- Existing Modules Listing -->
    <div id="modulesTableContainer"
        class="relative rounded-2xl border border-slate-800/60 bg-slate-900/20 shadow-2xl overflow-hidden glass-panel">
        <div
            class="px-6 py-4 border-b border-slate-800/80 bg-slate-950/40 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h5 class="font-extrabold text-white flex items-center gap-3 mb-0 text-base">
                <div
                    class="w-8 h-8 rounded-lg bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center">
                    <i class="bi bi-list-ul text-indigo-400"></i>
                </div>
                Existing Reusable Modules
            </h5>
            <div class="flex items-center gap-4 flex-wrap md:flex-nowrap">
                @if(auth()->user()->role === 'teacher')
                    <div class="flex items-center gap-2 bg-slate-900/40 px-3 py-1.5 rounded-xl border border-slate-800/80">
                        <label for="modulesShowSharedToggle"
                            class="text-xs font-extrabold text-slate-400 cursor-pointer select-none uppercase tracking-wider">Show
                            Shared</label>
                        <input type="checkbox" id="modulesShowSharedToggle"
                            class="w-4 h-4 text-indigo-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer modules-show-shared-toggle">
                    </div>
                @endif
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                            class="bi bi-search text-xs"></i></span>
                    <input type="text"
                        class="pl-9 pr-4 py-2.5 w-full md:w-64 text-sm rounded-xl border border-slate-800/80 bg-slate-900/60 text-white placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200"
                        id="modulesTableSearch" placeholder="Search modules...">
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-400">
                <thead
                    class="bg-slate-950/50 text-slate-400 border-b border-slate-800/80 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400">Id</th>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400">Key / Code</th>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400">Target</th>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400">Type</th>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400">Difficulty</th>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400">Created By</th>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400">Public</th>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400">Time</th>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400">Q's</th>
                        <th class="px-6 py-3.5 font-extrabold text-[10px] text-slate-400 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="modulesTableBody" class="divide-y divide-slate-800/40 bg-transparent">
                    @forelse($allModules as $module)
                        @php
                            $isOwner = $module->created_by === auth()->id() || auth()->user()->role === 'admin';
                            if (auth()->user()->role === 'teacher' && !$isOwner) {
                                continue;
                            }
                            $creatorName = $module->creator ? ($module->creator->username ?? $module->creator->email) : 'Admin';
                        @endphp
                        <tr
                            class="hover:bg-indigo-500/5 border-b border-slate-800/40 {{ !$isOwner ? 'row-shared opacity-80 border-dashed' : '' }}">
                            <td class="px-6 py-4 font-semibold text-slate-500">{{ $module->id }}</td>
                            <td class="px-6 py-4">
                                <code
                                    class="font-mono text-xs bg-slate-950/65 px-2.5 py-1.5 rounded-lg text-indigo-400 border border-slate-800/80 font-bold tracking-wide">{{ $module->key ?? 'N/A' }}</code>
                            </td>
                            <td class="px-6 py-4">
                                @if ($module->sections->isEmpty())
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-amber-500/10 text-amber-400 border border-amber-500/20 uppercase tracking-wide">
                                        <i class="bi bi-unlock mr-1.5"></i> Standalone
                                    </span>
                                @else
                                    <div class="flex flex-col gap-1.5">
                                        @foreach ($module->sections as $sec)
                                            <div>
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase tracking-wide">
                                                    <i class="bi bi-tag mr-1.5"></i> {{ $sec->test->title ?? 'Test' }}
                                                    &raquo; <span
                                                        class="ml-1 opacity-90 text-white font-medium normal-case">{{ $sec->name }}</span>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-slate-800/60 text-slate-400 border border-slate-700/60 uppercase tracking-wide">Module
                                    {{ $module->module_number }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $diffColors = [
                                        'hard' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                        'easy' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        'standard' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                    ];
                                    $colorClass =
                                        $diffColors[$module->difficulty_level] ??
                                        'bg-slate-800/60 text-slate-400 border-slate-700/60';
                                @endphp
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-extrabold border uppercase tracking-wide {{ $colorClass }}">
                                    {{ ucfirst($module->difficulty_level) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-semibold text-slate-350 truncate max-w-[110px] block"
                                    title="{{ $creatorName }}">{{ $creatorName }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if($isOwner)
                                    <div class="flex items-center">
                                        <input type="checkbox" data-id="{{ $module->id }}"
                                            class="w-4 h-4 text-indigo-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer module-public-toggle"
                                            {{ $module->is_public ? 'checked' : '' }}>
                                    </div>
                                @else
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wider">
                                        <i class="bi bi-globe mr-1"></i> Shared
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-200">{{ $module->duration_minutes }}<span
                                    class="text-[10px] ml-0.5 opacity-50 uppercase tracking-tighter">min</span></td>
                            <td class="px-6 py-4 font-extrabold text-white text-sm">{{ $module->total_questions }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-1">
                                    @if($isOwner)
                                        <button
                                            class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-xl clone-module-btn cursor-pointer"
                                            data-id="{{ $module->id }}" title="Clone Module">
                                            <i class="bi bi-copy"></i>
                                        </button>
                                        <button
                                            class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl delete-module-btn cursor-pointer"
                                            data-id="{{ $module->id }}" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div
                                        class="w-20 h-20 rounded-full bg-slate-900/40 border border-slate-800/60 flex items-center justify-center mb-6">
                                        <i class="bi bi-inbox text-3xl text-slate-400"></i>
                                    </div>
                                    <h4 class="text-base font-extrabold text-white">No modules found</h4>
                                    <p class="text-xs text-slate-400 mt-1 max-w-xs mx-auto leading-relaxed">You haven't
                                        created any reusable modules yet. Create one to start building your tests.</p>
                                    <button
                                        class="mt-6 px-5 py-3 bg-linear-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-indigo-600/20 cursor-pointer"
                                        x-on:click="$dispatch('open-offcanvas', 'createModuleOffcanvas')">
                                        Create Your First Module
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="modulesPoolPagination" class="mt-0 border-t border-slate-800/80 bg-slate-950/40 p-4"></div>
    </div>

    <!-- Create Module Offcanvas -->
    <x-ui.offcanvas id="createModuleOffcanvas" width="w-[480px]">
        <x-slot:titleContent>
            <i class="bi bi-folder-plus text-indigo-400 mr-3 text-xl"></i> Create Module
        </x-slot:titleContent>
        <form id="moduleForm" class="space-y-6">
            @csrf
            <div>
                <label for="moduleTest"
                    class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Target Test <span
                        class="text-slate-500 font-normal normal-case">(Optional)</span></label>
                <select class="form-select tom-select w-full bg-slate-900/60 border border-slate-800/80 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 rounded-xl"
                    id="moduleTest" name="test_id">
                    <option value="">No test (Standalone reusable module)</option>
                    @foreach ($tests as $test)
                        <option value="{{ $test->id }}">{{ $test->title }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-slate-500 mt-2 italic flex items-center gap-1.5 font-medium">
                    <i class="bi bi-info-circle text-indigo-400"></i> Section will be auto-generated inside the test
                </p>
            </div>

            <div>
                <label for="moduleSectionType"
                    class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Section Type <span
                        class="text-rose-500">*</span></label>
                <select
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                    id="moduleSectionType" name="section_type" required onchange="applyModuleDefaults(this)">
                    <option value="reading_writing" data-type="reading_writing">Reading and Writing</option>
                    <option value="math" data-type="math">Math</option>
                </select>
            </div>

            <div>
                <label for="moduleKey"
                    class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Module Key /
                    Unique Code <span class="text-slate-500 font-normal normal-case">(Optional)</span></label>
                <input type="text"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 font-mono font-bold"
                    id="moduleKey" name="key" placeholder="e.g. RW_M1_STANDARD_01">
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label for="moduleNumber"
                        class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Module Type #
                        <span class="text-rose-500">*</span></label>
                    <select
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                        id="moduleNumber" name="module_number" required>
                        <option value="1">1 (Standard)</option>
                        <option value="2">2 (Adaptive)</option>
                    </select>
                </div>
                <div>
                    <label for="difficultyLevel"
                        class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Difficulty
                        <span class="text-rose-500">*</span></label>
                    <select
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
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
                        class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Duration
                        (min) <span class="text-rose-500">*</span></label>
                    <input type="number"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200"
                        id="moduleDuration" name="duration_minutes" value="32" required>
                </div>
                <div>
                    <label for="totalQuestions"
                        class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Questions
                        <span class="text-rose-500">*</span></label>
                    <input type="number"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200"
                        id="totalQuestions" name="total_questions" value="27" required>
                </div>
            </div>

            <div class="flex items-center">
                <label class="flex items-center group cursor-pointer">
                    <input type="checkbox" name="is_public" value="1"
                        class="w-4 h-4 text-indigo-600 border-slate-800 bg-slate-400/60 rounded-xs cursor-pointer">
                    <span
                        class="ml-2.5 text-xs font-extrabold text-slate-400 group-hover:text-indigo-400 uppercase tracking-wider">Public
                        visibility</span>
                </label>
            </div>

            <div class="bg-indigo-500/5 border border-indigo-500/15 rounded-xl p-4 flex gap-3 shadow-xl">
                <i class="bi bi-info-circle-fill text-indigo-400 text-base shrink-0 mt-0.5"></i>
                <p class="text-[11px] text-indigo-300 leading-relaxed mb-0 font-medium">Standalone modules are reusable
                    and can be associated with multiple test sections later.</p>
            </div>

            <div class="pt-4">
                <button type="submit"
                    class="w-full py-3.5 bg-linear-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold rounded-xl shadow-lg shadow-indigo-600/25 text-xs uppercase tracking-wider cursor-pointer">
                    Create Module
                </button>
            </div>
        </form>
    </x-ui.offcanvas>

    <!-- Link Module Offcanvas -->
    <x-ui.offcanvas id="linkModuleOffcanvas" width="w-[480px]">
        <x-slot:titleContent>
            <i class="bi bi-link-45deg text-indigo-400 mr-3 text-xl"></i> Link Module
        </x-slot:titleContent>
        <form id="linkModuleForm" class="space-y-6" x-data="{ linkTargetType: 'section' }">
            @csrf
            <div>
                <label class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Link Target
                    <span class="text-rose-500">*</span></label>
                <div class="flex gap-6 mt-1">
                    <label class="flex items-center group cursor-pointer">
                        <input
                            class="w-4 h-4 text-indigo-600 border-slate-800 bg-slate-400/60 focus:ring-indigo-500/20 cursor-pointer rounded-full"
                            type="radio" name="link_target_type" id="targetTypeSection" value="section"
                            x-model="linkTargetType">
                        <span
                            class="ml-2.5 text-xs font-extrabold text-slate-400 group-hover:text-indigo-400 uppercase tracking-wider">Existing
                            Section</span>
                    </label>
                    <label class="flex items-center group cursor-pointer">
                        <input
                            class="w-4 h-4 text-indigo-600 border-slate-800 bg-slate-400/60 focus:ring-indigo-500/20 cursor-pointer rounded-full"
                            type="radio" name="link_target_type" id="targetTypeTest" value="test"
                            x-model="linkTargetType">
                        <span
                            class="ml-2.5 text-xs font-extrabold text-slate-400 group-hover:text-indigo-400 uppercase tracking-wider">Test
                            &amp; Auto-Create</span>
                    </label>
                </div>
            </div>

            <div id="linkSectionContainer" x-show="linkTargetType === 'section'">
                <label for="linkSection"
                    class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Target Section
                    <span class="text-rose-500">*</span></label>
                <select class="form-select tom-select w-full bg-slate-900/60 border border-slate-800/80 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 rounded-xl" id="linkSection" name="section_id"
                    x-bind:required="linkTargetType === 'section'">
                    <option value="">Select section...</option>
                    @foreach ($tests as $test)
                        @foreach ($test->sections as $section)
                            <option value="{{ $section->id }}">{{ $test->title }} - {{ $section->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div id="linkTestFieldsContainer" x-show="linkTargetType === 'test'" class="space-y-6">
                <div>
                    <label for="linkTest"
                        class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Target Test
                        <span class="text-rose-500">*</span></label>
                    <select class="form-select tom-select w-full bg-slate-900/60 border border-slate-800/80 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 rounded-xl" id="linkTest" name="test_id"
                        x-bind:required="linkTargetType === 'test'">
                        <option value="">Select test...</option>
                        @foreach ($tests as $test)
                            <option value="{{ $test->id }}">{{ $test->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="linkSectionType"
                        class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Section Type
                        <span class="text-rose-500">*</span></label>
                    <select
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-800/80 bg-slate-900/60 text-white text-sm hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 appearance-none bg-no-repeat bg-position-[right_1rem_center] bg-size-[1em_1em]"
                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')"
                        id="linkSectionType" name="section_type" x-bind:required="linkTargetType === 'test'">
                        <option value="">Select type...</option>
                        <option value="reading_writing">Reading and Writing</option>
                        <option value="math">Math</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="linkModule"
                    class="text-xs font-extrabold text-slate-400 tracking-wider uppercase mb-2 block">Reusable Module
                    <span class="text-rose-500">*</span></label>
                <select class="form-select tom-select w-full bg-slate-900/60 border border-slate-800/80 text-white text-sm placeholder-slate-500 hover:border-indigo-500/40 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 focus:outline-hidden transition-all duration-200 rounded-xl" id="linkModule" name="module_id" required>
                    <option value="">Select module by key/ID...</option>
                    @foreach ($allModules as $mod)
                        <option value="{{ $mod->id }}">[{{ $mod->key ?? 'ID: ' . $mod->id }}] - Mod
                            {{ $mod->module_number }} ({{ ucfirst($mod->difficulty_level) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="pt-4">
                <button type="submit"
                    class="w-full py-3.5 bg-linear-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold rounded-xl shadow-lg shadow-indigo-600/25 text-xs uppercase tracking-wider cursor-pointer">
                    Associate Module
                </button>
            </div>
        </form>
    </x-ui.offcanvas>
</div>