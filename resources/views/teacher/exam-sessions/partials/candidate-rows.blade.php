{{--
    The candidate roster, rendered identically on first paint and on every live
    poll. ExamSessionController::liveStatus() renders THIS file and ships the
    HTML, rather than the JSON+template-string pair it used to, because the two
    copies of the markup had to be kept in sync by hand — the poll would quietly
    replace the styled rows with a divergent set four seconds after load.

    Expects: $examSession, $candidates, $totalQuestions
--}}
@if ($candidates->isEmpty())
    <div class="home-empty">
        <x-ui.icon name="people" class="home-empty__icon w-6 h-6" />
        <p class="home-empty__title">Nobody has joined yet</p>
        <p class="home-empty__body">
            Share the code above. Candidates appear here the moment they start, and this list refreshes
            on its own.
        </p>
    </div>
@else
    <ul class="exam-roster">
        @foreach ($candidates as $attempt)
            @php
                // Modules can be reached either through their owning section or
                // the section_modules pivot (reused content), so fall back
                // rather than showing "—" for a candidate demonstrably mid-test.
                $section = $attempt->currentModule?->section ?? $attempt->currentModule?->sections?->first();
                $placeLabel = $attempt->currentModule
                    ? ($section
                        ? "Section {$section->order} · Module {$attempt->currentModule->module_number}"
                        : "Module {$attempt->currentModule->module_number}")
                    : null;

                $isInProgress = $attempt->status === 'in_progress';
                $answered = (int) ($attempt->answered_count ?? 0);
                $percent = $totalQuestions > 0
                    ? min(100, (int) round($answered / $totalQuestions * 100))
                    : 0;
            @endphp

            <li class="exam-candidate" data-candidate-id="{{ $attempt->id }}">
                <div class="exam-candidate__identity">
                    <span class="exam-candidate__name">{{ $attempt->guest_name ?: $attempt->user->name }}</span>
                    <span class="exam-candidate__meta">
                        <x-ui.status-badge
                            :status="match ($attempt->status) {
                                'completed' => 'success',
                                'in_progress' => 'brand',
                                default => 'neutral',
                            }">
                            {{ ucfirst(str_replace('_', ' ', $attempt->status)) }}
                        </x-ui.status-badge>

                        @if ($placeLabel)
                            <span>{{ $placeLabel }}</span>
                        @elseif ($attempt->status === 'completed')
                            <span>Finished {{ $attempt->completed_at?->diffForHumans() ?? 'recently' }}</span>
                        @else
                            <span>Not started</span>
                        @endif
                    </span>
                </div>

                <div class="exam-candidate__figures">
                    {{-- Scores are withheld while the attempt runs: IRT produces
                         nothing until a module is submitted, so any number here
                         would be a placeholder a teacher could misread. Progress
                         is the honest live figure. --}}
                    @if ($isInProgress)
                        <div class="exam-progress">
                            <div class="exam-progress__head">
                                <span class="exam-progress__label">Progress</span>
                                <span class="exam-progress__value">{{ $percent }}%</span>
                            </div>
                            <span class="exam-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                                aria-valuenow="{{ $percent }}"
                                aria-label="Questions answered by {{ $attempt->guest_name ?: $attempt->user->name }}">
                                <i style="width: {{ $percent }}%"></i>
                            </span>
                            <span class="exam-progress__count home-num">{{ $answered }} / {{ $totalQuestions }}</span>
                        </div>
                    @else
                        <div class="exam-scores">
                            <span class="exam-score">
                                <span class="exam-score__label">R&amp;W</span>
                                <span class="exam-score__value">{{ $attempt->score_reading_writing ?? '—' }}</span>
                            </span>
                            <span class="exam-score">
                                <span class="exam-score__label">Math</span>
                                <span class="exam-score__value">{{ $attempt->score_math ?? '—' }}</span>
                            </span>
                            <span class="exam-score exam-score--total">
                                <span class="exam-score__label">Total</span>
                                <span class="exam-score__value">{{ $attempt->total_score ?? '—' }}</span>
                            </span>
                        </div>
                    @endif
                </div>

                <div class="exam-candidate__actions">
                    <x-ui.button size="sm" variant="secondary"
                        :href="route('teacher.exam-sessions.attempts.show', [$examSession, $attempt])">
                        View detail
                    </x-ui.button>

                    {{-- The state-changing actions live behind one menu so a row
                         scans as "open this" rather than three equal choices.
                         x-bind:data-menu-open lets the live poll skip its
                         innerHTML swap while a menu is open — otherwise the
                         teacher's menu would vanish under them within 4s. --}}
                    <x-ui.dropdown align="right" width="auto" x-bind:data-menu-open="open">
                        <x-slot:trigger>
                            <button type="button" class="exam-actions" :aria-expanded="open" aria-haspopup="true">
                                Actions
                                <svg class="exam-actions__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                                    :class="open ? 'is-open' : ''">
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </button>
                        </x-slot:trigger>

                        <x-slot:content>
                            @if ($isInProgress)
                                <form method="POST"
                                    action="{{ route('teacher.exam-sessions.attempts.force-submit', [$examSession, $attempt]) }}"
                                    data-confirm="Force-submit this candidate's attempt now? Unanswered questions are marked omitted and scored.">
                                    @csrf
                                    <button type="submit" class="exam-menuitem" role="menuitem">Force submit</button>
                                </form>
                            @endif

                            <form method="POST"
                                action="{{ route('teacher.exam-sessions.attempts.reset', [$examSession, $attempt]) }}"
                                data-confirm="Reset this attempt? The candidate can retake the exam from the start.">
                                @csrf
                                <button type="submit" class="exam-menuitem exam-menuitem--danger" role="menuitem">
                                    Reset attempt
                                </button>
                            </form>
                        </x-slot:content>
                    </x-ui.dropdown>
                </div>
            </li>
        @endforeach
    </ul>
@endif
