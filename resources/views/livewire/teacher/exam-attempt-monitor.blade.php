@php
    $isInProgress = $attempt->status === 'in_progress';
    $currentModule = $attempt->currentModule;

    $savedResponses = $attempt->userAnswers->count();
    $answeredResponses = $attempt->userAnswers->filter(fn ($answer) => filled($answer->selected_answer))->count();
    $moduleResponses = $currentModule
        ? $attempt->userAnswers->where('module_id', $currentModule->id)->filter(fn ($answer) => filled($answer->selected_answer))->count()
        : $answeredResponses;
    $questionTotal = $currentModule?->total_questions ?: max($savedResponses, 1);
    $progress = min(100, (int) round(($moduleResponses / max($questionTotal, 1)) * 100));
    $elapsedSeconds = (int) $attempt->current_module_elapsed_seconds;
@endphp

{{-- Polling stops once the attempt is finished: nothing can change after that,
     and a closed session left open on a projector should not keep hitting the
     server every 5 seconds forever. --}}
<div @if ($isInProgress) wire:poll.5s @endif class="exam-monitor">
    <x-ui.card :padded="false" class="home-stats" role="group" aria-label="Attempt at a glance">
        <div class="home-stats__grid">
            <div class="home-stat">
                <span class="home-stat__label">Answered</span>
                <span class="home-stat__value home-num">{{ $answeredResponses }}</span>
            </div>

            <div class="home-stat">
                <span class="home-stat__label">{{ $isInProgress ? 'Module progress' : 'Responses saved' }}</span>
                <span class="home-stat__value home-num">
                    {{ $isInProgress && $currentModule ? $moduleResponses.' / '.$questionTotal : $savedResponses }}
                </span>
            </div>

            <div class="home-stat">
                <span class="home-stat__label">Module time</span>
                <span class="home-stat__value home-num">
                    {{ intdiv($elapsedSeconds, 60) }}:{{ str_pad((string) ($elapsedSeconds % 60), 2, '0', STR_PAD_LEFT) }}
                </span>
            </div>

            <div class="home-stat">
                <span class="home-stat__label">{{ $isInProgress ? 'Started' : 'Total score' }}</span>
                <span class="home-stat__value home-num">
                    {{-- No score while running: nothing is computed until a module
                         is submitted, so a number here would be fiction. --}}
                    @if ($isInProgress)
                        {{ $attempt->created_at->format('M j, g:i A') }}
                    @else
                        {{ $attempt->total_score ?? '—' }}
                    @endif
                </span>
                @unless ($isInProgress)
                    <span class="home-stat__note">
                        R&amp;W {{ $attempt->score_reading_writing ?? '—' }}
                        &middot; Math {{ $attempt->score_math ?? '—' }}
                    </span>
                @endunless
            </div>
        </div>
    </x-ui.card>

    @if ($isInProgress && $currentModule)
        <x-ui.card>
            <div class="attempt-monitor__progress">
                <div>
                    <span>
                        {{ $currentModule->section?->name ?? 'Current section' }}
                        &middot; Module {{ $currentModule->module_number }}
                    </span>
                    <strong>{{ $progress }}%</strong>
                </div>
                <span role="progressbar" aria-label="Current module completion" aria-valuemin="0" aria-valuemax="100"
                    aria-valuenow="{{ $progress }}">
                    <i style="width: {{ $progress }}%"></i>
                </span>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card aria-labelledby="responses-title">
        <x-slot:header>
            <div class="home-panel__headline">
                <h2 id="responses-title" class="home-panel__title">Question map</h2>
                <p class="home-panel__sub">
                    {{ $isInProgress
                        ? 'Where they are now — click any question for its detail'
                        : 'Click any question for its detail' }}
                </p>
            </div>

            <div class="exam-monitor__head-aside">
                @if ($isInProgress)
                    <span class="exam-live">
                        <span class="exam-live__dot" aria-hidden="true"></span>
                        Updating every 5s
                    </span>
                @endif

                {{-- Legend mirrors the engine's own review navigator, so a teacher
                     reads the grid the same way the student does. --}}
                <ul class="qmap-legend">
                    @if ($isInProgress)
                        <li><span class="qmap-key qmap-key--answered"></span>Answered</li>
                        <li><span class="qmap-key qmap-key--blank"></span>Unanswered</li>
                    @else
                        <li><span class="qmap-key qmap-key--correct"></span>Correct</li>
                        <li><span class="qmap-key qmap-key--wrong"></span>Incorrect</li>
                        <li><span class="qmap-key qmap-key--omitted"></span>Omitted</li>
                    @endif
                </ul>
            </div>
        </x-slot:header>

        @if ($moduleGrid->isEmpty())
            <div class="home-empty">
                <x-ui.icon name="journal-text" class="home-empty__icon w-6 h-6" />
                <p class="home-empty__title">Nothing to track yet</p>
                <p class="home-empty__body">
                    Questions appear here as soon as the candidate opens their first module.
                </p>
            </div>
        @else
            <div class="qmap">
                @foreach ($moduleGrid as $group)
                    @php
                        $module = $group['module'];
                        $answeredInModule = $group['questions']
                            ->filter(fn ($q) => filled($q['answer']?->selected_answer))
                            ->count();
                        $moduleTotal = $group['questions']->count();
                    @endphp

                    <section class="qmap-module">
                        <header class="qmap-module__head">
                            <h3 class="qmap-module__title">
                                {{ $module->section?->name ?? 'Section' }}
                                <span aria-hidden="true">&middot;</span>
                                Module {{ $module->module_number }}
                            </h3>
                            <span class="qmap-module__meta home-num">
                                {{ $answeredInModule }} / {{ $moduleTotal }}
                            </span>
                            @if ($group['isCurrent'] && $isInProgress)
                                <x-ui.status-badge status="brand">Here now</x-ui.status-badge>
                            @elseif ($group['isSubmitted'])
                                <x-ui.status-badge status="success">Submitted</x-ui.status-badge>
                            @endif
                        </header>

                        <ol class="qmap-grid">
                            @foreach ($group['questions'] as $cell)
                                @php
                                    $answer = $cell['answer'];
                                    $hasResponse = filled($answer?->selected_answer);

                                    // While a module is live, "answered vs blank" is all
                                    // that is knowable — correctness is not shown until
                                    // the module is submitted, so a teacher watching over
                                    // a shoulder cannot read answers off this screen.
                                    if (! $group['isSubmitted']) {
                                        $state = $hasResponse ? 'answered' : 'blank';
                                        $label = $hasResponse ? 'Answered' : 'Not answered';
                                    } elseif (! $hasResponse) {
                                        $state = 'omitted';
                                        $label = 'Omitted';
                                    } else {
                                        $state = $answer->is_correct ? 'correct' : 'wrong';
                                        $label = $answer->is_correct ? 'Correct' : 'Incorrect';
                                    }
                                @endphp

                                <li>
                                    @if ($answer)
                                        <button type="button" class="qmap-cell qmap-cell--{{ $state }}"
                                            @click="fetchPreview({{ $answer->id }})"
                                            :class="selectedAnswerId === {{ $answer->id }} ? 'is-selected' : ''"
                                            title="Question {{ $cell['number'] }} — {{ $label }}">
                                            <span aria-hidden="true">{{ $cell['number'] }}</span>
                                            <span class="sr-only">
                                                Question {{ $cell['number'] }}, {{ $label }}. Open detail.
                                            </span>
                                        </button>
                                    @else
                                        {{-- No saved row yet, so there is nothing to open. --}}
                                        <span class="qmap-cell qmap-cell--{{ $state }} is-inert"
                                            title="Question {{ $cell['number'] }} — {{ $label }}">
                                            <span aria-hidden="true">{{ $cell['number'] }}</span>
                                            <span class="sr-only">Question {{ $cell['number'] }}, {{ $label }}</span>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endforeach
            </div>
        @endif
    </x-ui.card>
</div>
