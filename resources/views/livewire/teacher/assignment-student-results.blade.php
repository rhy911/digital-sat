<div class="relative">
    <div class="sp-head flex items-center justify-between">
        <div>
            <p>Best completed score represents each student; every attempt remains available.</p>
        </div>
        <div wire:loading.flex class="items-center gap-2 px-3 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-full border border-blue-200">
            <svg class="animate-spin h-3.5 w-3.5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Loading page...</span>
        </div>
    </div>

    <div wire:loading.class="opacity-50 pointer-events-none" class="transition-opacity duration-150">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Best score</th>
                    @if($assignment->assign_type !== 'section')
                        <th>R&amp;W Score</th>
                        <th>Math Score</th>
                    @endif
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td class="name-cell">
                            <x-ui.person-cell :name="$row['recipient']->student->name"
                                :email="$row['recipient']->student->email"
                                :initials="$row['recipient']->student->initials" />
                        </td>
                        <td>
                            <span class="status-pill {{ $row['recipient']->status === 'withdrawn' ? 'danger' : ($row['in_progress'] ? 'in-progress' : ($row['best'] ? 'ok' : 'pending')) }}">
                                <span class="d"></span>
                                {{ $row['recipient']->status === 'withdrawn' ? 'Withdrawn' : ($row['in_progress'] ? 'In progress' : ($row['best'] ? ($row['late'] ? 'Completed late' : 'Completed') : 'Not started')) }}
                            </span>
                        </td>
                        <td>{{ $row['completed_count'] }} / {{ $assignment->attempt_limit }}</td>
                        <td>
                            @if ($row['best'])
                                @if ($assignment->assign_type === 'section')
                                    <strong>{{ $assignment->section_type === 'reading_writing' ? ($row['best']->score_reading_writing ?? '—') : ($row['best']->score_math ?? '—') }} / 800</strong>
                                @else
                                    <strong>{{ $row['best']->total_score ?? '—' }} / 1600</strong>
                                @endif
                                <small style="display: block; font-size: 11px; color: var(--ink-soft); margin-top: 2px;">
                                    Correct: {{ $row['best']->correct_answers_count }}/{{ $row['best']->total_questions_count }}
                                </small>
                            @else
                                —
                            @endif
                        </td>
                        @if($assignment->assign_type !== 'section')
                            <td>{{ $row['best']?->score_reading_writing ?? '—' }}</td>
                            <td>{{ $row['best']?->score_math ?? '—' }}</td>
                        @endif
                        <td class="text-right">
                            @if ($row['attempts']->isNotEmpty())
                                <a href="{{ route('teacher.assignments.students.show', [$assignment, $row['recipient']->student]) }}" class="link-action">
                                    View attempts
                                </a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $assignment->assign_type === 'section' ? 5 : 7 }}" class="empty-row">No recipients yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (isset($report['paginator']) && $report['paginator'] && $report['paginator']->hasPages())
        <div class="cw-pagination">
            <div class="cw-pagination__info">
                Showing <strong>{{ $report['paginator']->firstItem() }}</strong>
                to <strong>{{ $report['paginator']->lastItem() }}</strong>
                of <strong>{{ $report['paginator']->total() }}</strong> results
            </div>
            <div class="cw-pagination__controls">
                @if ($report['paginator']->onFirstPage())
                    <button type="button" disabled class="cw-pagination__btn">
                        Previous
                    </button>
                @else
                    <button type="button" wire:click="previousPage" wire:loading.attr="disabled" class="cw-pagination__btn">
                        Previous
                    </button>
                @endif

                @for ($p = 1; $p <= $report['paginator']->lastPage(); $p++)
                    @if ($p == $report['paginator']->currentPage())
                        <button type="button" class="cw-pagination__btn is-active">
                            {{ $p }}
                        </button>
                    @else
                        <button type="button" wire:click="gotoPage({{ $p }})" wire:loading.attr="disabled" class="cw-pagination__btn">
                            {{ $p }}
                        </button>
                    @endif
                @endfor

                @if ($report['paginator']->hasMorePages())
                    <button type="button" wire:click="nextPage" wire:loading.attr="disabled" class="cw-pagination__btn">
                        Next
                    </button>
                @else
                    <button type="button" disabled class="cw-pagination__btn">
                        Next
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
