<template id="builderBlockTemplate">
    <div class="dash-panel overflow-hidden mb-6 builder-block" data-index="{INDEX}">
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
            <span class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <i class="bi bi-question-circle text-indigo-600"></i> Question #{DISPLAY_INDEX}
            </span>
            <button type="button" class="px-2.5 py-1 text-xs border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-lg remove-block-btn" aria-label="Remove question card">
                Remove
            </button>
        </div>
        <div class="p-5 space-y-5">
            <!-- R&W Passage (Hidden by default, shown if module is R&W) -->
            <div class="builder-passage-container hidden">
                <label class="block text-xs font-bold text-slate-600 tracking-wider uppercase mb-2">Passage (Reading & Writing only)</label>
                <textarea class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none builder-passage" rows="3" placeholder="Enter passage text..."></textarea>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-600 tracking-wider uppercase mb-2">Question Stem <span class="text-rose-500">*</span></label>
                <textarea class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none builder-stem" rows="2" placeholder="e.g. What is the value of x?" required></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 tracking-wider uppercase mb-2">Domain (Optional)</label>
                    <select class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-white text-slate-800 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em] builder-domain" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')">
                        <option value="">Select domain...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 tracking-wider uppercase mb-2">Difficulty (Optional)</label>
                    <select class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-white text-slate-800 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em] builder-difficulty" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2394a3b8%22 stroke-width=%222%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 9l-7 7-7-7%22 /%3E%3C/svg%3E')">
                        <option value="">Select difficulty...</option>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 tracking-wider uppercase mb-2">Question Format</label>
                <div class="flex rounded-xl overflow-hidden border border-slate-200 p-1 bg-slate-50 gap-1" role="radiogroup" aria-label="Question format">
                    <div class="flex-1">
                        <input type="radio" class="sr-only peer builder-format-radio builder-format-mcq" name="format_{INDEX}" id="format_mcq_{INDEX}" autocomplete="off" checked value="multiple_choice" role="radio" aria-checked="true">
                        <label class="flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-slate-600 rounded-lg cursor-pointer hover:bg-slate-100 peer-checked:bg-white peer-checked:text-indigo-600 peer-checked:shadow-sm" for="format_mcq_{INDEX}">
                            <i class="bi bi-list-ol"></i> Multiple Choice (MCQ)
                        </label>
                    </div>
                    <div class="flex-1">
                        <input type="radio" class="sr-only peer builder-format-radio builder-format-spr" name="format_{INDEX}" id="format_spr_{INDEX}" autocomplete="off" value="student_produced_response" role="radio" aria-checked="false">
                        <label class="flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-slate-600 rounded-lg cursor-pointer hover:bg-slate-100 peer-checked:bg-white peer-checked:text-indigo-600 peer-checked:shadow-sm" for="format_spr_{INDEX}">
                            <i class="bi bi-pencil-square"></i> Student Produced Response (SPR)
                        </label>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <!-- MCQ Container -->
                <div class="builder-mcq-container space-y-3">
                    <h6 class="text-xs font-bold text-indigo-700 uppercase tracking-wider flex items-center gap-1.5 mb-3"><i class="bi bi-list-stars"></i> MCQ Choices (Mark correct one)</h6>
                    <div class="builder-choices-container space-y-2.5">
                        <!-- 4 choices -->
                        @foreach(['A', 'B', 'C', 'D'] as $choiceLabel)
                        <div class="flex rounded-xl overflow-hidden border border-slate-200">
                            <div class="bg-slate-50 border-r border-slate-200 px-3.5 flex items-center gap-2.5 shrink-0">
                                <input class="w-4 h-4 text-indigo-600 border-slate-200 bg-white focus:ring-indigo-500 focus:ring-offset-white builder-correct-radio" type="radio" name="correct_{INDEX}" value="{{ $choiceLabel }}" @if($choiceLabel === 'A') checked @endif>
                                <span class="text-xs font-bold text-slate-600">{{ $choiceLabel }}</span>
                            </div>
                            <input type="text" class="w-full px-4 py-2.5 text-sm text-slate-800 bg-white placeholder-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none builder-choice-content" data-label="{{ $choiceLabel }}" placeholder="Option {{ $choiceLabel }} content" required>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- SPR Container -->
                <div class="builder-spr-container hidden space-y-2">
                    <h6 class="text-xs font-bold text-indigo-700 uppercase tracking-wider flex items-center gap-1.5 mb-3"><i class="bi bi-check-all"></i> SPR Accepted Answers <span class="text-rose-500">*</span></h6>
                    <input type="text" class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none builder-spr-answers" placeholder="e.g. 3|3.0 (separate multiple answers with | or ;)">
                    <div class="text-[11px] text-slate-500 mt-2 flex items-center gap-1.5 font-medium"><i class="bi bi-info-circle"></i> Use | or ; to specify multiple accepted formats (e.g. decimal and fraction).</div>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <label class="block text-xs font-bold text-slate-600 tracking-wider uppercase mb-2">Explanation (Optional)</label>
                <textarea class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 outline-none builder-explanation" rows="2" placeholder="Why is this answer correct?"></textarea>
            </div>
        </div>
    </div>
</template>
