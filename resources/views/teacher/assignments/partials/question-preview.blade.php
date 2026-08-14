@php
    // $context is optional and only the exam-session tracker passes it, so the
    // assignment attempt monitor's modal renders exactly as it did before.
    $context = $context ?? null;

    $question = $userAnswer->question;
    $snapshot = is_array($userAnswer->question_snapshot) ? $userAnswer->question_snapshot : [];

    // Snapshot first: it is what the student was actually graded against, and it
    // can legitimately differ from the live question row (see the warning on
    // TestProgressionService::responsesForModule).
    $domain = $snapshot['skill_domain'] ?? $question?->skill_domain;
    $subdomain = $snapshot['skill_subdomain'] ?? $question?->skill_subdomain;
    $difficulty = $snapshot['difficulty'] ?? $question?->difficulty;
    $isPretest = (bool) ($snapshot['is_pretest'] ?? $question?->is_pretest);

    $domainLabel = $domain ? \Illuminate\Support\Str::of($domain)->replace('_', ' ')->title()->toString() : null;
    $subdomainLabel = $subdomain ? \Illuminate\Support\Str::of($subdomain)->replace('_', ' ')->title()->toString() : null;

    $hasResponse = filled($userAnswer->selected_answer);
    $resultLabel = $isInProgress
        ? ($hasResponse ? 'Answered' : 'Not answered')
        : (! $hasResponse ? 'Omitted' : ($userAnswer->is_correct ? 'Correct' : 'Incorrect'));
    $resultClasses = match ($resultLabel) {
        'Correct' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'Incorrect' => 'bg-rose-100 text-rose-800 border-rose-200',
        'Answered' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
        default => 'bg-slate-100 text-slate-600 border-slate-200',
    };

    $chipClasses = 'text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded border';

    // Time is reported unconditionally in the context strip, including a plain
    // "0s". The old header badge rendered nothing when both figures were zero,
    // which is the common case for a question the candidate never opened — and
    // a silently missing row reads as "no data captured" rather than "no time
    // spent on it", which is the opposite of what a teacher needs to see.
    $spentSeconds = (int) ($userAnswer->time_spent ?? 0);
    $expectedSeconds = (int) ($snapshot['expected_time'] ?? $question?->expected_time ?? 0);

    $humanSeconds = function (int $seconds): string {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $remainder = $seconds % 60;

        return intdiv($seconds, 60).'m'.($remainder > 0 ? ' '.$remainder.'s' : '');
    };

    // Same 1.2x threshold the score report's pacing column uses.
    $isSlow = $expectedSeconds > 0 && $spentSeconds > $expectedSeconds * 1.2;
@endphp

<div class="flex flex-col h-full bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
    <!-- Header with Metadata -->
    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center gap-4 pr-10">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">
            @if ($context)
                {{ $context['sectionName'] ?? 'Question' }}
                @if (!empty($context['moduleNumber']))
                    &middot; Module {{ $context['moduleNumber'] }}
                @endif
                @if (!empty($context['questionNumber']))
                    &middot; Q{{ $context['questionNumber'] }}
                @endif
            @else
                Question Details
            @endif
        </span>
        <div class="flex items-center gap-2">
            {{-- Callers that pass $context get the fuller, always-present timing
                 chip in the strip below instead, so it is not shown twice. --}}
            @unless ($context)
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
            @endunless
        </div>
    </div>

    @if ($context)
        {{-- Everything a teacher would otherwise have to infer: how it was
             graded, which skill it tests, how hard it is, and whether it counts
             toward the score at all. --}}
        <div class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-center gap-2">
            <span class="{{ $chipClasses }} {{ $resultClasses }}">{{ $resultLabel }}</span>

            @if ($hasResponse)
                <span class="{{ $chipClasses }} bg-white text-slate-700 border-slate-200">
                    Response: {{ $userAnswer->selected_answer }}
                </span>
            @endif

            {{-- Always rendered, 0s included: "spent no time here" is itself the
                 signal a teacher is looking for on a skipped question. --}}
            <span class="{{ $chipClasses }} {{ $isSlow
                ? 'bg-rose-50 text-rose-700 border-rose-100'
                : 'bg-white text-slate-700 border-slate-200' }}"
                title="{{ $expectedSeconds > 0
                    ? 'Time spent versus the expected time for this question'
                    : 'Time spent on this question' }}">
                Time: {{ $humanSeconds($spentSeconds) }}@if ($expectedSeconds > 0)
                    / {{ $humanSeconds($expectedSeconds) }} expected
                @endif
            </span>

            @if ($isSlow)
                <span class="{{ $chipClasses }} bg-rose-50 text-rose-700 border-rose-100">Over pace</span>
            @endif

            @if ($domainLabel)
                <span class="{{ $chipClasses }} bg-indigo-50 text-indigo-700 border-indigo-100"
                    @if ($subdomainLabel) title="{{ $subdomainLabel }}" @endif>
                    {{ $domainLabel }}
                </span>
            @endif

            @if ($subdomainLabel)
                <span class="{{ $chipClasses }} bg-white text-slate-600 border-slate-200">{{ $subdomainLabel }}</span>
            @endif

            @if ($difficulty)
                <span class="{{ $chipClasses }} {{ match (strtolower($difficulty)) {
                    'easy' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                    'hard' => 'bg-rose-50 text-rose-700 border-rose-100',
                    default => 'bg-amber-50 text-amber-700 border-amber-100',
                } }}">
                    {{ ucfirst($difficulty) }}
                </span>
            @endif

            @if ($isPretest)
                {{-- Pretest items are excluded from IRT scoring (PRODUCT.md §4),
                     so a teacher reading a wrong answer here should know it did
                     not cost the candidate anything. --}}
                <span class="{{ $chipClasses }} bg-slate-100 text-slate-600 border-slate-200"
                    title="Pretest items do not count toward the score">
                    Unscored pretest
                </span>
            @endif
        </div>
    @endif

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
