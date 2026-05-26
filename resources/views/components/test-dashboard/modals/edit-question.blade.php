<x-ui.modal id="editQuestionModal" max-width="80%">
    <x-slot:title>
        <div class="flex items-center gap-2">
            <i class="bi bi-pencil-square text-indigo-400"></i> Edit Question #<span id="editQuestionIdDisplay"></span>
        </div>
    </x-slot:title>

    <div class="relative min-h-[500px]">
        <div id="editQuestionModalLoader"
            class="absolute inset-0 bg-slate-800/95 z-50 flex flex-col items-center justify-center rounded-xl transition-all duration-300">
            <div class="flex flex-col items-center gap-4">
                <div
                    class="w-12 h-12 border-4 border-indigo-500/20 border-t-indigo-500 rounded-full animate-spin shadow-lg shadow-indigo-500/20">
                </div>
                <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">Initialising Editor...
                </div>
            </div>
        </div>

        <form id="editQuestionForm" class="m-0">
            @csrf
            @method('PUT')
            <input type="hidden" id="editQuestionId" name="id">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-0 edit-question-grid">

                <!-- Left Side: Form (Scrollable) -->
                <div
                    class="lg:col-span-7 p-6 border-r border-slate-800/80 overflow-y-auto h-full space-y-4 text-slate-200">
                    <div id="editPassageContainer" class="hidden">
                        <label for="editPassageContent" class="block text-xs font-bold text-slate-300 mb-1.5">Passage
                            Content (Reading & Writing)</label>
                        <textarea
                            class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                            id="editPassageContent" name="passage_content" rows="6"></textarea>
                    </div>
                    <div>
                        <label for="editQuestionStem" class="block text-xs font-bold text-slate-300 mb-1.5">Question
                            Stem / Prompt</label>
                        <textarea
                            class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                            id="editQuestionStem" name="stem" rows="4"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="editQuestionType" class="block text-xs font-bold text-slate-300 mb-1.5">Question
                                Type</label>
                            <select
                                class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                id="editQuestionType" name="question_type" required>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="student_produced_response">Student Produced (SPR)</option>
                            </select>
                        </div>
                        <div>
                            <label for="editDifficulty"
                                class="block text-xs font-bold text-slate-300 mb-1.5">Difficulty</label>
                            <select
                                class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                id="editDifficulty" name="difficulty">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="editSkillDomain" class="block text-xs font-bold text-slate-300 mb-1.5">Skill
                                Domain</label>
                            <select
                                class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                id="editSkillDomain" name="skill_domain"></select>
                        </div>
                        <div>
                            <label for="editSkillSubdomain" class="block text-xs font-bold text-slate-300 mb-1.5">Skill
                                Subdomain</label>
                            <input type="text"
                                class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                id="editSkillSubdomain" name="skill_subdomain">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                        <div class="md:col-span-6" id="editSprHintContainer">
                            <label for="editSprHint" class="block text-xs font-bold text-slate-300 mb-1.5">SPR
                                Hint</label>
                            <input type="text"
                                class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                id="editSprHint" name="spr_hint">
                        </div>
                        <div class="md:col-span-3 pb-2">
                            <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-400">
                                <input type="checkbox"
                                    class="w-4 h-4 text-indigo-500 border-slate-800 bg-slate-900/60 rounded focus:ring-indigo-500 focus:ring-2 focus:ring-offset-slate-950"
                                    id="editIsPretest" name="is_pretest" value="1">
                                <span>Pretest?</span>
                            </label>
                        </div>
                        <div class="md:col-span-3 pb-2">
                            <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-400">
                                <input type="checkbox"
                                    class="w-4 h-4 text-indigo-500 border-slate-800 bg-slate-900/60 rounded focus:ring-indigo-500 focus:ring-2 focus:ring-offset-slate-950"
                                    id="editCalculatorAllowed" name="calculator_allowed" value="1">
                                <span>Calculator?</span>
                            </label>
                        </div>
                    </div>

                    <div class="border-t border-slate-800/60 pt-4">
                        <div id="editMcqChoicesContainer" class="space-y-3">
                            <h6 class="text-sm font-bold text-white flex items-center gap-2 mb-3">
                                <i class="bi bi-list-ol text-indigo-400"></i> Answer Choices (MCQ)
                            </h6>
                            @foreach(['A', 'B', 'C', 'D'] as $choiceIndex => $label)
                                <div class="grid grid-cols-12 gap-3 items-center">
                                    <div class="col-span-1 text-center font-bold text-slate-400">{{ $label }}</div>
                                    <input type="hidden" name="choices[{{ $choiceIndex }}][label]" value="{{ $label }}">
                                    <div class="col-span-8">
                                        <input type="text"
                                            class="w-full px-3 py-1.5 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 edit-choice-input"
                                            name="choices[{{ $choiceIndex }}][content]" id="editChoice{{ $label }}Content"
                                            placeholder="Option content">
                                    </div>
                                    <div class="col-span-3 pl-2">
                                        <label
                                            class="flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-slate-300">
                                            <input
                                                class="w-4 h-4 text-indigo-500 border-slate-800 bg-slate-900/60 focus:ring-indigo-500 focus:ring-2 edit-choice-radio"
                                                type="radio" name="correct_choice" value="{{ $label }}"
                                                id="editChoice{{ $label }}Correct">
                                            <span>Correct</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div id="editSprAnswersContainer" class="hidden">
                            <h6 class="text-sm font-bold text-white flex items-center gap-2 mb-3">
                                <i class="bi bi-check-circle text-indigo-400"></i> Correct Answers (SPR)
                            </h6>
                            <div>
                                <label for="editSprAnswers"
                                    class="block text-xs font-bold text-slate-300 mb-1.5">Comma-separated accepted
                                    values</label>
                                <input type="text"
                                    class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                    id="editSprAnswers" name="spr_answers" placeholder="e.g. 12, 12.0, 24/2">
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-800/60 pt-4 space-y-4">
                        <div>
                            <label for="editExplanation" class="block text-xs font-bold text-slate-300 mb-1.5">Correct
                                Rationale (Explanation)</label>
                            <textarea
                                class="w-full px-3 py-2 text-sm text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                id="editExplanation" name="explanation" rows="3"></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="editRationaleA"
                                    class="block text-xs font-semibold text-slate-400 mb-1">Rationale A</label>
                                <textarea
                                    class="w-full px-3 py-1.5 text-xs text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                    id="editRationaleA" name="rationale_a" rows="1"></textarea>
                            </div>
                            <div>
                                <label for="editRationaleB"
                                    class="block text-xs font-semibold text-slate-400 mb-1">Rationale B</label>
                                <textarea
                                    class="w-full px-3 py-1.5 text-xs text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                    id="editRationaleB" name="rationale_b" rows="1"></textarea>
                            </div>
                            <div>
                                <label for="editRationaleC"
                                    class="block text-xs font-semibold text-slate-400 mb-1">Rationale C</label>
                                <textarea
                                    class="w-full px-3 py-1.5 text-xs text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                    id="editRationaleC" name="rationale_c" rows="1"></textarea>
                            </div>
                            <div>
                                <label for="editRationaleD"
                                    class="block text-xs font-semibold text-slate-400 mb-1">Rationale D</label>
                                <textarea
                                    class="w-full px-3 py-1.5 text-xs text-white bg-slate-900/60 border border-slate-800/80 rounded-lg placeholder-slate-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                    id="editRationaleD" name="rationale_d" rows="1"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-800/60 pt-4" id="editMediaManagementContainer">
                        <div class="flex justify-between items-center mb-3">
                            <h6 class="text-sm font-bold text-white flex items-center gap-2 mb-0">
                                <i class="bi bi-image text-indigo-400"></i> Media Management
                            </h6>
                            <button type="button"
                                class="px-2.5 py-1 text-xs bg-slate-900/40 border border-slate-800/80 hover:bg-slate-900/80 text-slate-300 hover:text-white font-semibold rounded-lg flex items-center gap-1.5 cursor-pointer"
                                onclick="refreshEditMediaList()" title="Refresh media list from text fields">
                                <i class="bi bi-arrow-clockwise"></i> Refresh List
                            </button>
                        </div>
                        <div id="editMediaList" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <!-- Existing media items will be listed here -->
                        </div>
                        <p class="text-slate-500 text-[11px] flex items-center gap-1.5 mt-2.5 mb-0">
                            <i class="bi bi-info-circle text-indigo-400 animate-pulse"></i> Media is managed via
                            Markdown <code>![](...)</code> inside text fields.
                        </p>
                    </div>
                </div>

                <!-- Right Side: Real-time Live Preview (Sticky) -->
                <div class="lg:col-span-5 p-6 bg-slate-900/20 flex flex-col h-full overflow-y-auto">
                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-800/60">
                        <span class="text-sm font-bold text-white flex items-center gap-2">
                            <i class="bi bi-file-earmark-richtext text-amber-400 animate-pulse"></i> Real-time Question
                            Preview
                        </span>
                        <span
                            class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase bg-slate-800 text-slate-400 rounded-md font-mono"
                            id="editPreviewTypeBadge">MCQ</span>
                    </div>
                    <div class="flex-1 pr-1 passage-preview" id="editQuestionPreviewContent"
                        style="padding-bottom: 20px;">
                        <!-- Compiled real-time preview -->
                    </div>
                </div>

            </div>
            <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-slate-800/80">
                <button type="button"
                    class="px-4 py-2 bg-slate-900/40 border border-slate-800/80 text-slate-300 hover:text-white font-semibold text-sm rounded-lg hover:bg-slate-900/80"
                    x-on:click="$dispatch('close-modal', 'editQuestionModal')">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 bg-linear-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 hover:shadow-indigo-500/20 text-white font-semibold text-sm rounded-lg shadow-lg">Update
                    Question</button>
            </div>
        </form>
    </div>
</x-ui.modal>