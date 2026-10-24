<div class="relative">
    <div class="sp-head flex items-center justify-between gap-4 flex-wrap">
        <div>
            <p>Best completed score represents each student; every attempt remains available.</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <div class="relative min-w-[200px]">
                <input wire:model.live.debounce.250ms="search" type="search" placeholder="Search students..." class="search-input text-sm px-3 py-1.5 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brand/20 w-full" aria-label="Search students">
            </div>
            <div wire:loading.flex class="items-center gap-2 px-3 py-1 bg-blue-50 text-blue-700 text-sm font-semibold rounded-full border border-blue-200">
                <svg class="animate-spin h-3.5 w-3.5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Loading page...</span>
            </div>
        </div>
    </div>

    <div wire:loading.class="opacity-50 pointer-events-none" class="transition-opacity duration-150">
        <div class="table-responsive-wrapper" tabindex="0" role="region" aria-label="Student results table">
            <table>
                <thead>
                    <tr>
                        <th aria-sort="{{ $sortField === 'name' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1.5 hover:text-slate-900 cursor-pointer font-semibold {{ $sortField === 'name' ? 'text-brand' : '' }}">
                                <span>Student</span>
                                @if ($sortField === 'name')
                                    <span class="text-sm leading-none">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th aria-sort="{{ $sortField === 'status' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1.5 hover:text-slate-900 cursor-pointer font-semibold {{ $sortField === 'status' ? 'text-brand' : '' }}">
                                <span>Status</span>
                                @if ($sortField === 'status')
                                    <span class="text-sm leading-none">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="text-right" aria-sort="{{ $sortField === 'attempts' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <button type="button" wire:click="sortBy('attempts')" class="inline-flex items-center gap-1.5 hover:text-slate-900 cursor-pointer font-semibold ml-auto {{ $sortField === 'attempts' ? 'text-brand' : '' }}">
                                <span>Attempts</span>
                                @if ($sortField === 'attempts')
                                    <span class="text-sm leading-none">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="text-right" aria-sort="{{ $sortField === 'score' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <button type="button" wire:click="sortBy('score')" class="inline-flex items-center gap-1.5 hover:text-slate-900 cursor-pointer font-semibold ml-auto {{ $sortField === 'score' ? 'text-brand' : '' }}">
                                <span>Best score</span>
                                @if ($sortField === 'score')
                                    <span class="text-sm leading-none">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        @if($assignment->assign_type !== 'section')
                            <th class="text-right" aria-sort="{{ $sortField === 'rw' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <button type="button" wire:click="sortBy('rw')" class="inline-flex items-center gap-1.5 hover:text-slate-900 cursor-pointer font-semibold ml-auto {{ $sortField === 'rw' ? 'text-brand' : '' }}">
                                    <span>R&amp;W Score</span>
                                    @if ($sortField === 'rw')
                                        <span class="text-sm leading-none">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                            <th class="text-right" aria-sort="{{ $sortField === 'math' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <button type="button" wire:click="sortBy('math')" class="inline-flex items-center gap-1.5 hover:text-slate-900 cursor-pointer font-semibold ml-auto {{ $sortField === 'math' ? 'text-brand' : '' }}">
                                    <span>Math Score</span>
                                    @if ($sortField === 'math')
                                        <span class="text-sm leading-none">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                        @endif
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody wire:loading.class="opacity-50 pointer-events-none transition-opacity">
                    @forelse($report['rows'] as $row)
                        <tr>
                            <td class="name-cell">
                                <x-ui.person-cell :name="$row['recipient']->student->name"
                                    :email="$row['recipient']->student->email"
                                    :initials="$row['recipient']->student->initials" />
                            </td>
                            <td>
                                @php
                                    $rowStatus = $row['status'] ?? \App\Support\AssignmentStatusResolver::resolve($assignment, $row['attempts'], $row['recipient']);
                                    $pillClass = match ($rowStatus['variant']) {
                                        'success' => 'ok',
                                        'brand' => 'in-progress',
                                        'danger' => 'danger',
                                        'neutral' => 'draft',
                                        default => 'pending',
                                    };
                                @endphp
                                <span class="status-pill {{ $pillClass }}">
                                    <span class="d"></span>
                                    {{ $rowStatus['state'] }}
                                </span>
                            </td>
                            <td class="text-right tabular-nums">
                                @php $maxAllowed = $row['recipient']->maxAllowedAttempts(); @endphp
                                {{ $row['completed_count'] }} / {{ $maxAllowed }}
                                @if ($row['recipient']->extra_attempts > 0)
                                    <span class="inline-flex items-center gap-1 text-sm font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">+{{ $row['recipient']->extra_attempts }} bonus</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">
                                @if ($row['best'])
                                    @if ($assignment->assign_type === 'section')
                                        <strong>{{ $assignment->section_type === 'reading_writing' ? ($row['best']->score_reading_writing ?? '—') : ($row['best']->score_math ?? '—') }} / 800</strong>
                                    @else
                                        <strong>{{ $row['best']->total_score ?? '—' }} / 1600</strong>
                                    @endif
                                    <span class="block text-sm font-medium text-slate-600 mt-0.5">
                                        Correct: {{ $row['best']->correct_answers_count }}/{{ $row['best']->total_questions_count }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            @if($assignment->assign_type !== 'section')
                                <td class="text-right tabular-nums">{{ $row['best']?->score_reading_writing ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ $row['best']?->score_math ?? '—' }}</td>
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
                            <td colspan="{{ $assignment->assign_type === 'section' ? 5 : 7 }}" class="empty-row py-8 text-center text-slate-500">
                                @if (trim($search) !== '')
                                    <p class="text-sm font-medium text-slate-700 m-0">No students match &ldquo;{{ $search }}&rdquo;</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-2 text-sm font-semibold text-brand hover:underline cursor-pointer">
                                        Clear search
                                    </button>
                                @else
                                    <p class="text-sm font-medium text-slate-700 m-0">No recipients yet.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
