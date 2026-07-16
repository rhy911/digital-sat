<x-ui.modal id="editQuestionModal" max-width="80%">
    <x-slot:title>
        <div class="flex items-center gap-2">
            <x-ui.icon name="pencil-square" class="w-4 h-4 text-brand" id="editQuestionModalIconEdit" />
            <x-ui.icon name="eye" class="w-4 h-4 text-slate-400 hidden" id="editQuestionModalIconView" /> <span
                id="editQuestionModalTitleText">Edit Question</span> #<span id="editQuestionIdDisplay"></span>
        </div>
    </x-slot:title>

    <div class="relative min-h-[500px] edit-question-modal">
        <div id="editQuestionModalLoader"
            class="absolute inset-0 bg-white/95 z-50 flex flex-col items-center justify-center rounded-xl transition-all duration-300">
            <div class="flex flex-col items-center gap-4">
                <div class="w-12 h-12 border-4 border-slate-200 border-t-brand rounded-full animate-spin">
                </div>
                <div class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Initialising editor...
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
                    class="lg:col-span-7 p-6 border-r border-slate-200 overflow-y-auto h-full space-y-4 text-slate-900 bg-white edit-question-form-pane">
                    <div id="editPassageContainer" class="hidden">
                        <label for="editPassageContent" class="block text-xs font-bold text-slate-700 mb-1.5">Passage
                            Content (Reading & Writing)</label>
                        <textarea
                            class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                            id="editPassageContent" name="passage_content" rows="6"></textarea>
                    </div>
                    <div>
                        <label for="editQuestionStem" class="block text-xs font-bold text-slate-700 mb-1.5">Question
                            Stem / Prompt</label>
                        <textarea
                            class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                            id="editQuestionStem" name="stem" rows="4"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="editQuestionType" class="block text-xs font-bold text-slate-700 mb-1.5">Question
                                Type</label>
                            <select
                                class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                id="editQuestionType" name="question_type" required>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="student_produced_response">Student Produced (SPR)</option>
                            </select>
                        </div>
                        <div>
                            <label for="editDifficulty"
                                class="block text-xs font-bold text-slate-700 mb-1.5">Difficulty</label>
                            <select
                                class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                id="editDifficulty" name="difficulty">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="editSkillDomain" class="block text-xs font-bold text-slate-700 mb-1.5">Skill
                                Domain</label>
                            <select
                                class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                id="editSkillDomain" name="skill_domain"></select>
                        </div>
                        <div>
                            <label for="editSkillSubdomain" class="block text-xs font-bold text-slate-700 mb-1.5">Skill
                                Subdomain</label>
                            <input type="text"
                                class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                id="editSkillSubdomain" name="skill_subdomain">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                        <div class="md:col-span-4" id="editSprHintContainer">
                            <label for="editSprHint" class="block text-xs font-bold text-slate-700 mb-1.5">SPR
                                Hint</label>
                            <input type="text"
                                class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                id="editSprHint" name="spr_hint">
                        </div>
                        <div class="md:col-span-3 pb-2">
                            <label for="editExpectedTime" class="block text-xs font-bold text-slate-700 mb-1.5">Expected Time (s)</label>
                            <input type="number"
                                class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                id="editExpectedTime" name="expected_time" min="0" placeholder="e.g. 60">
                        </div>
                        <div class="md:col-span-2 pb-2">
                            <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-600">
                                <input type="checkbox"
                                    class="w-4 h-4 text-brand border-slate-300 bg-white rounded focus:ring-brand focus:ring-2"
                                    id="editIsPretest" name="is_pretest" value="1">
                                <span>Pretest?</span>
                            </label>
                        </div>
                        <div class="md:col-span-3 pb-2">
                            <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-600">
                                <input type="checkbox"
                                    class="w-4 h-4 text-brand border-slate-300 bg-white rounded focus:ring-brand focus:ring-2"
                                    id="editCalculatorAllowed" name="calculator_allowed" value="1">
                                <span>Calculator?</span>
                            </label>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <div id="editMcqChoicesContainer" class="space-y-3">
                            <h6 class="text-sm font-bold text-slate-900 flex items-center gap-2 mb-3">
                                <x-ui.icon name="list-ol" class="w-4 h-4 text-brand" /> Answer choices (MCQ)
                            </h6>
                            @foreach (['A', 'B', 'C', 'D'] as $choiceIndex => $label)
                                <div class="grid grid-cols-12 gap-3 items-center">
                                    <div class="col-span-1 text-center font-bold text-slate-600">{{ $label }}
                                    </div>
                                    <input type="hidden" name="choices[{{ $choiceIndex }}][label]"
                                        value="{{ $label }}">
                                    <div class="col-span-8">
                                        <input type="text"
                                            class="w-full px-3 py-1.5 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand edit-choice-input"
                                            name="choices[{{ $choiceIndex }}][content]"
                                            id="editChoice{{ $label }}Content" placeholder="Option content">
                                    </div>
                                    <div class="col-span-3 pl-2">
                                        <label
                                            class="flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-slate-600">
                                            <input
                                                class="w-4 h-4 text-brand border-slate-300 bg-white focus:ring-brand focus:ring-2 edit-choice-radio"
                                                type="radio" name="correct_choice" value="{{ $label }}"
                                                id="editChoice{{ $label }}Correct">
                                            <span>Correct</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div id="editSprAnswersContainer" class="hidden">
                            <h6 class="text-sm font-bold text-slate-900 flex items-center gap-2 mb-3">
                                <x-ui.icon name="check-circle" class="w-4 h-4 text-brand" /> Correct answers (SPR)
                            </h6>
                            <div>
                                <label for="editSprAnswers"
                                    class="block text-xs font-bold text-slate-700 mb-1.5">Comma-separated accepted
                                    values</label>
                                <input type="text"
                                    class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                    id="editSprAnswers" name="spr_answers" placeholder="e.g. 12, 12.0, 24/2">
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4 space-y-4">
                        <div>
                            <label for="editExplanation" class="block text-xs font-bold text-slate-700 mb-1.5">Correct
                                rationale</label>
                            <textarea
                                class="w-full px-3 py-2 text-sm text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                id="editExplanation" name="explanation" rows="3"></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="editRationaleA"
                                    class="block text-xs font-semibold text-slate-600 mb-1">Rationale A</label>
                                <textarea
                                    class="w-full px-3 py-1.5 text-xs text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                    id="editRationaleA" name="rationale_a" rows="1"></textarea>
                            </div>
                            <div>
                                <label for="editRationaleB"
                                    class="block text-xs font-semibold text-slate-600 mb-1">Rationale B</label>
                                <textarea
                                    class="w-full px-3 py-1.5 text-xs text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                    id="editRationaleB" name="rationale_b" rows="1"></textarea>
                            </div>
                            <div>
                                <label for="editRationaleC"
                                    class="block text-xs font-semibold text-slate-600 mb-1">Rationale C</label>
                                <textarea
                                    class="w-full px-3 py-1.5 text-xs text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                    id="editRationaleC" name="rationale_c" rows="1"></textarea>
                            </div>
                            <div>
                                <label for="editRationaleD"
                                    class="block text-xs font-semibold text-slate-600 mb-1">Rationale D</label>
                                <textarea
                                    class="w-full px-3 py-1.5 text-xs text-slate-900 bg-white border border-slate-300 rounded-lg placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand"
                                    id="editRationaleD" name="rationale_d" rows="1"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4" id="editMediaManagementContainer">
                        <div class="flex justify-between items-center mb-3">
                            <h6 class="text-sm font-bold text-slate-900 flex items-center gap-2 mb-0">
                                <x-ui.icon name="image" class="w-4 h-4 text-brand" /> Media management
                            </h6>
                            <button type="button"
                                class="px-2.5 py-1 text-xs bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold rounded-lg flex items-center gap-1.5 cursor-pointer"
                                onclick="refreshEditMediaList()" title="Refresh media list from text fields">
                                <x-ui.icon name="arrow-clockwise" class="w-4 h-4" /> Refresh List
                            </button>
                        </div>
                        <div id="editMediaList" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <!-- Existing media items will be listed here -->
                        </div>
                        <p class="text-slate-600 text-[11px] flex items-center gap-1.5 mt-2.5 mb-0">
                            <x-ui.icon name="info-circle" class="w-4 h-4 text-brand" /> Media is managed via
                            Markdown <code>![](...)</code> inside text fields.
                        </p>
                    </div>
                </div>

                <!-- Right Side: Real-time Live Preview (Sticky) -->
                <div
                    class="lg:col-span-5 p-6 bg-slate-50 flex flex-col h-full overflow-y-auto edit-question-preview-pane">
                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-200">
                        <span class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <x-ui.icon name="file-earmark-richtext" class="w-4 h-4 text-amber-700" /> Question preview
                        </span>
                        <span
                            class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase bg-slate-100 text-slate-600 border border-slate-200 rounded-md font-mono"
                            id="editPreviewTypeBadge">MCQ</span>
                    </div>
                    <div class="flex-1 pr-1 passage-preview" id="editQuestionPreviewContent"
                        style="padding-bottom: 20px;">
                        <!-- Compiled real-time preview -->
                    </div>
                </div>

            </div>
            <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-slate-200 edit-question-footer">
                <button type="button"
                    class="px-4 py-2 bg-white border border-slate-300 text-slate-700 hover:text-slate-900 font-semibold text-sm rounded-lg hover:bg-slate-50"
                    x-on:click="$dispatch('close-modal', 'editQuestionModal')">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 bg-brand hover:bg-brand-hover text-white font-semibold text-sm rounded-lg">Update
                    Question</button>
            </div>
        </form>
    </div>
</x-ui.modal>
