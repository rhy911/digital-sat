{{-- Partial: single answer row inside a module's response list (attempt-monitor.blade.php)
     Variables expected: $answer, $isInProgress, $domainLabels, $difficultyBadgeClasses
--}}
<?php
    $correct = $answer->question?->sprCorrectAnswers->pluck('answer')->implode(', ') ?: $answer->question?->answerChoices->firstWhere('is_correct', true)?->label;
    $timeSpent = (int) ($answer->time_spent ?? 0);
    if ($timeSpent >= 60) {
        $minutes = floor($timeSpent / 60);
        $seconds = $timeSpent % 60;
        $timeString = "{$minutes}m" . ($seconds > 0 ? " {$seconds}s" : "");
    } else {
        $timeString = "{$timeSpent}s";
    }
?>
<li class="p-0 border-b border-slate-100 last:border-b-0" data-answer-id="{{ $answer->id }}">
    <button type="button"
        class="w-full text-left px-4 py-3 flex items-center justify-between transition-colors focus:outline-none"
        :class="selectedAnswerId === {{ $answer->id }} ? 'bg-indigo-50/70 text-indigo-900' : 'hover:bg-slate-50 text-slate-700'"
        @click="fetchPreview({{ $answer->id }})">
        <div class="flex items-center gap-3 min-w-0 flex-1">
            <span class="attempt-monitor__question-number shrink-0 font-medium"
                :class="selectedAnswerId === {{ $answer->id }} ? 'bg-brand text-white' : ''">
                {{ $loop->iteration }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm truncate mb-0" :class="selectedAnswerId === {{ $answer->id }} ? 'font-medium text-indigo-950' : 'text-slate-700'">
                    {{ strip_tags($answer->question?->stem) }}
                </p>
                <div class="flex items-center gap-2 flex-wrap mt-1">
                    @if ($isInProgress)
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ filled($answer->selected_answer) ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-100 text-slate-500' }}">
                            {{ filled($answer->selected_answer) ? 'Answered: ' . $answer->selected_answer : 'Omitted' }}
                        </span>
                    @else
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $answer->is_correct ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100' }}">
                            {{ $answer->is_correct ? 'Correct' : 'Incorrect' }}
                        </span>
                    @endif

                    @if($answer->question?->skill_domain)
                        @php($domainLabel = $domainLabels[$answer->question->skill_domain] ?? ucwords(str_replace('_', ' ', $answer->question->skill_domain)))
                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">
                            {{ $domainLabel }}
                        </span>
                    @endif

                    @if($answer->question?->difficulty)
                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded {{ $difficultyBadgeClasses($answer->question->difficulty) }}">
                            {{ ucfirst($answer->question->difficulty) }}
                        </span>
                    @endif

                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200 flex items-center gap-1">
                        <svg class="w-3 h-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ $timeString }}
                    </span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0 ml-3">
            <svg x-show="loading && selectedAnswerId === {{ $answer->id }}" class="animate-spin h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </button>
</li>
