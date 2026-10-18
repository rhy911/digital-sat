{{-- Partial: question results table
     Variables expected:
       $answers  — array of row data
       $tableId  — unique HTML id for the <table>
--}}
<div class="sd-table-wrap">
    <div class="sd-table-toolbar">
        <label class="sd-toggle-row">
            <span class="sd-toggle-wrap">
                <input type="checkbox" class="sd-toggle-input sd-correct-toggle"
                    id="show-correct-toggle-{{ $tableId }}">
                <span class="sd-toggle-slider"></span>
            </span>
            Show Correct Answers
        </label>
        <div class="sd-view-btns" role="group" aria-label="Filter questions by result">
            <span style="color:var(--ds-text-secondary, #475569);font-size:.8rem;font-weight:600;">Filter:</span>
            <button class="sd-view-btn active" data-filter="all" type="button" aria-pressed="true">All (<span data-count-filter="all">{{ count($answers) }}</span>)</button>
            <button class="sd-view-btn" data-filter="wrong" type="button" aria-pressed="false">Incorrect (<span data-count-filter="wrong">{{ collect($answers)->where('statusKey', 'wrong')->count() }}</span>)</button>
            <button class="sd-view-btn" data-filter="omitted" type="button" aria-pressed="false">Omitted (<span data-count-filter="omitted">{{ collect($answers)->where('statusKey', 'omitted')->count() }}</span>)</button>
            <button class="sd-view-btn" data-filter="correct" type="button" aria-pressed="false">Correct (<span data-count-filter="correct">{{ collect($answers)->where('statusKey', 'correct')->count() }}</span>)</button>
        </div>
    </div>

    <div class="sd-table-outer">
        <table class="sd-table" id="{{ $tableId }}" aria-label="Question review results">
            <thead>
                <tr>
                    <th scope="col" style="width: 44px;">#</th>
                    <th scope="col" class="section-col">Section</th>
                    <th scope="col">Result</th>
                    <th scope="col" class="correct-col" style="display:none;">Your Answer</th>
                    <th scope="col" class="correct-col" style="display:none;">Correct Answer</th>
                    <th scope="col">Domain</th>
                    <th scope="col">Difficulty</th>
                    <th scope="col">Pacing</th>
                    <th scope="col" style="text-align: right;">Review</th>
                </tr>
            </thead>
            <tbody>
                @forelse($answers as $row)
                    @php
                        $pacing = $row['pacing'];
                        $isPretest = !empty($row['isPretest']);
                    @endphp
                    <tr data-status="{{ $row['statusKey'] }}" data-section="{{ $row['sectionType'] }}" data-answer-id="{{ $row['answer']->id }}">
                        <td data-label="#">
                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                <span class="sd-q-num">{{ $row['idx'] }}</span>
                                @if ($isPretest)
                                    <span class="sd-unscored-indicator" title="Unscored practice question (not included in scaled score)" aria-label="Unscored question">*</span>
                                @endif
                            </div>
                        </td>
                        <td data-label="Section" class="section-col">
                            <span class="sd-section-pill {{ $row['sectionType'] }}">
                                {{ $row['sectionName'] }}
                                @if (!empty($row['moduleNumber']))
                                    <span class="sd-module-label">M{{ $row['moduleNumber'] }}</span>
                                @endif
                            </span>
                        </td>
                        <td data-label="Result">
                            @if ($row['statusKey'] === 'correct')
                                <span class="sd-status-badge correct">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <span>Correct</span>
                                </span>
                            @elseif($row['statusKey'] === 'wrong')
                                <span class="sd-status-badge wrong">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg>
                                    <span>Incorrect</span>
                                </span>
                            @else
                                <span class="sd-status-badge omitted">
                                    <span class="sd-dash" aria-hidden="true">&ndash;</span>
                                    <span>Omitted</span>
                                </span>
                            @endif
                        </td>
                        <td data-label="Your Answer" class="correct-col" style="display:none;">
                            @if($row['statusKey'] === 'correct')
                                <span class="sd-val-correct">{{ $row['answer']->selected_answer ?? '—' }}</span>
                            @elseif($row['statusKey'] === 'wrong')
                                <span class="sd-val-wrong">{{ $row['answer']->selected_answer ?? '—' }}</span>
                            @else
                                <span class="sd-val-omitted">Omitted</span>
                            @endif
                        </td>
                        <td data-label="Correct Answer" class="correct-col" style="display:none;">
                            <span class="sd-val-correct">{{ $row['correctAnswer'] }}</span>
                        </td>
                        <td data-label="Domain">
                            <span class="sd-domain-tag" title="{{ $row['domainLabel'] }}">{{ $row['domainLabel'] }}</span>
                        </td>
                        <td data-label="Difficulty">
                            @php $diffLower = strtolower($row['difficulty'] ?? ''); @endphp
                            @if(in_array($diffLower, ['easy', 'medium', 'hard'], true))
                                <span class="sd-diff-badge {{ $diffLower }}">{{ ucfirst($diffLower) }}</span>
                            @else
                                <span class="sd-diff-badge unknown">—</span>
                            @endif
                        </td>
                        <td data-label="Pacing">
                            @if ($pacing['status'] === 'none')
                                <span class="sd-timing-empty">No timing data</span>
                            @else
                                <div style="display: inline-flex; flex-direction: column;">
                                    <span style="font-size: 0.8rem; font-weight: 700;" class="sd-timing-{{ $pacing['status'] }}">
                                        {{ $pacing['actualSeconds'] }}s
                                    </span>
                                    <span style="font-size: 0.7rem; color: var(--ds-text-secondary, #475569);" title="{{ $pacing['label'] }}">
                                        {{ $pacing['shortLabel'] }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td data-label="Review" style="text-align: right;">
                            <button class="sd-review-btn js-review-btn"
                                type="button"
                                data-answer-id="{{ $row['answer']->id }}"
                                aria-label="Review question {{ $row['idx'] }}">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <span>Review</span>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:2rem;color:var(--ds-text-secondary, #475569);font-style:italic;">
                            No questions found.
                        </td>
                    </tr>
                @endforelse
                @if (count($answers) > 0)
                    <tr class="sd-no-results-row" hidden>
                        <td colspan="9" style="text-align:center;padding:2rem;color:var(--ds-text-secondary, #475569);">
                            <p style="margin: 0; font-weight: 600;">No questions match this filter.</p>
                            <button type="button" class="sd-clear-filters-btn" style="margin-top: 0.5rem; font-size: 0.8rem; font-weight: 700; color: var(--ds-brand, #2563eb); background: none; border: none; cursor: pointer; text-decoration: underline;">
                                Clear filters
                            </button>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="sd-pagination" data-table-pagination="{{ $tableId }}" aria-label="Question review pagination">
        <div class="sd-pagination-info" data-page-info aria-live="polite"></div>
        <div class="sd-pagination-controls">
            <button class="sd-page-btn" type="button" data-page-action="prev" aria-label="Previous page">Previous</button>
            <div class="sd-page-numbers" data-page-numbers></div>
            <button class="sd-page-btn" type="button" data-page-action="next" aria-label="Next page">Next</button>
        </div>
    </div>
</div>
