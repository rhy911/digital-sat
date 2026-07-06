<div class="flex flex-col h-full bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
    <!-- Header with Metadata -->
    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center gap-4 pr-10">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">
            Question Details
        </span>
        <div class="flex items-center gap-2">
            <?php 
                $expectedTime = $userAnswer->question_snapshot['expected_time'] ?? $userAnswer->question?->expected_time ?? 0;
                $timeSpent = $userAnswer->time_spent ?? 0;
            ?>
            @if($expectedTime > 0)
                <?php $timeWarning = $timeSpent > ($expectedTime * 1.2); ?>
                <span class="text-xs font-semibold px-2 py-0.5 rounded border {{ $timeWarning ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-50 text-slate-600 border-slate-200' }}">
                    ⏱️ {{ $timeSpent }}s / {{ $expectedTime }}s
                </span>
            @elseif($timeSpent > 0)
                <span class="text-xs font-semibold px-2 py-0.5 rounded border bg-slate-50 text-slate-600 border-slate-200">
                    ⏱️ {{ $timeSpent }}s
                </span>
            @endif
        </div>
    </div>

    <!-- Content (Passage + Stem + Choices) -->
    <div class="p-5 overflow-y-auto flex-1 space-y-4">
        <!-- Passage (if exists) -->
        @if($userAnswer->question?->passage?->content)
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700 leading-relaxed max-h-60 overflow-y-auto shadow-inner">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Passage</span>
                <div class="prose prose-sm max-w-none text-slate-800">
                    {!! \App\Support\QuestionContentRenderer::markdown($userAnswer->question->passage->content) !!}
                </div>
            </div>
        @endif

        <!-- Question Stem -->
        <div class="text-sm text-slate-900 font-medium leading-relaxed">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Question Prompt</span>
            <div class="prose prose-sm max-w-none text-slate-900">
                {!! \App\Support\QuestionContentRenderer::markdown($userAnswer->question?->stem) !!}
            </div>
        </div>

        <!-- Answer Choices / Input -->
        <div class="pt-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Options & Results</span>
            @if($userAnswer->question?->answerChoices->isNotEmpty())
                <div class="flex flex-col gap-2.5">
                    @foreach($userAnswer->question->answerChoices as $choice)
                        <?php
                            $isStudentChoice = $choice->label === $userAnswer->selected_answer;
                            $isCorrectChoice = $choice->is_correct;
                        ?>
                        <div class="flex items-start p-3 rounded-lg border transition-all {{ $isCorrectChoice ? 'bg-emerald-50/60 border-emerald-300 shadow-sm' : ($isStudentChoice && !$isCorrectChoice ? 'bg-rose-50/60 border-rose-300 shadow-sm' : 'bg-white border-slate-200') }}">
                            <!-- Choice Label Indicator -->
                            <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs shrink-0 mr-3 border 
                                {{ $isCorrectChoice ? 'bg-emerald-500 border-emerald-600 text-white shadow-sm' : ($isStudentChoice && !$isCorrectChoice ? 'bg-rose-500 border-rose-600 text-white shadow-sm' : 'bg-slate-100 border-slate-300 text-slate-600') }}">
                                {{ $choice->label }}
                            </div>
                            <div class="text-sm leading-normal flex-1 prose prose-sm max-w-none {{ $isCorrectChoice ? 'text-emerald-950 font-medium' : ($isStudentChoice && !$isCorrectChoice ? 'text-rose-950 font-medium' : 'text-slate-800') }}">
                                {!! \App\Support\QuestionContentRenderer::markdown($choice->content) !!}
                            </div>
                            
                            <!-- Status Label badge on the right -->
                            <div class="shrink-0 ml-2">
                                @if($isCorrectChoice && $isStudentChoice)
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded border border-emerald-200">Correct &amp; Chosen</span>
                                @elseif($isCorrectChoice)
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded border border-emerald-200">Correct Answer</span>
                                @elseif($isStudentChoice)
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 bg-rose-100 text-rose-800 rounded border border-rose-200">Student Choice</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif($userAnswer->question?->sprCorrectAnswers->isNotEmpty())
                <div class="space-y-3">
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-700">Student's Response:</span>
                        @if($isInProgress)
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-600 border border-slate-200 rounded text-sm font-medium">{{ $userAnswer->selected_answer ?: 'Omitted' }}</span>
                        @else
                            <span class="px-2.5 py-1 rounded text-sm font-semibold border {{ $userAnswer->is_correct ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-rose-50 border-rose-200 text-rose-700' }}">
                                {{ $userAnswer->selected_answer ?: 'Omitted' }} ({{ $userAnswer->is_correct ? 'Correct' : 'Incorrect' }})
                            </span>
                        @endif
                    </div>
                    <div class="p-3 bg-emerald-50/40 border border-emerald-100 rounded-lg">
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider block mb-1.5">Accepted Answers:</span>
                        <div class="flex gap-2 flex-wrap">
                            @foreach($userAnswer->question->sprCorrectAnswers as $spr)
                                <span class="px-2.5 py-1 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded text-sm font-semibold shadow-sm">{{ $spr->answer }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Explanation (for completed tests) -->
        @if (!$isInProgress && $userAnswer->question?->explanation?->explanation)
            <div class="mt-4 p-4 bg-indigo-50/50 border border-indigo-100 rounded-lg">
                <span class="text-xs font-bold text-indigo-800 uppercase tracking-wider block mb-2">Explanation</span>
                <div class="text-sm text-indigo-900 leading-relaxed prose prose-sm max-w-none">
                    {!! \App\Support\QuestionContentRenderer::markdown($userAnswer->question->explanation->explanation) !!}
                </div>
            </div>
        @endif
    </div>
</div>
