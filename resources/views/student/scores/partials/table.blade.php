{{-- Partial: question results table
     Variables expected:
       $answers  — array of row data (all rows, pre-filtered if needed)
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
        <div class="sd-view-btns">
            <span style="color:#94a3b8;font-size:.8rem;font-weight:600;">Filter:</span>
            <button class="sd-view-btn active" data-filter="all">All</button>
            <button class="sd-view-btn" data-filter="correct">Correct</button>
            <button class="sd-view-btn" data-filter="wrong">Incorrect</button>
            <button class="sd-view-btn" data-filter="omitted">Omitted</button>
        </div>
    </div>

    <div class="sd-table-outer">
        <table class="sd-table" id="{{ $tableId }}">
            <thead>
                <tr>
                    <th>#</th>
                    <th class="section-col">Section</th>
                    <th class="correct-col" style="display:none;">Your Answer</th>
                    <th class="correct-col" style="display:none;">Correct Answer</th>
                    <th>Result</th>
                    <th>Domain</th>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>
                @forelse($answers as $row)
                    {{-- data-status for result filter; data-section for tab filter --}}
                    <tr data-status="{{ $row['statusKey'] }}" data-section="{{ $row['sectionType'] }}">
                        <td><span class="sd-q-num">{{ $row['idx'] }}</span></td>
                        <td class="section-col">
                            <span class="sd-section-pill {{ $row['sectionType'] }}">
                                {{ $row['sectionName'] }}
                                @if (!empty($row['moduleNumber']))
                                    <span class="sd-module-label">M{{ $row['moduleNumber'] }}</span>
                                @endif
                            </span>
                        </td>
                        <td class="correct-col" style="display:none;">
                            @if($row['statusKey'] === 'correct')
                                <span style="font-weight:600;color:#059669;">{{ $row['answer']->selected_answer ?? '—' }}</span>
                            @elseif($row['statusKey'] === 'wrong')
                                <span style="font-weight:600;color:#dc2626;">{{ $row['answer']->selected_answer ?? '—' }}</span>
                            @else
                                <span style="font-weight:600;color:#94a3b8;">Omitted</span>
                            @endif
                        </td>
                        <td class="correct-col" style="display:none;">
                            <span style="font-weight:600;color:#059669;">{{ $row['correctAnswer'] }}</span>
                        </td>
                        <td>
                            @if ($row['statusKey'] === 'correct')
                                <span class="sd-status-badge correct">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    Correct
                                </span>
                            @elseif($row['statusKey'] === 'wrong')
                                <span class="sd-status-badge wrong">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="3">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg>
                                    Incorrect
                                </span>
                            @else
                                <span class="sd-status-badge omitted">— Omitted</span>
                            @endif
                        </td>
                        <td>
                            <span class="sd-domain-tag" title="{{ $row['domainLabel'] }}">{{ $row['domainLabel'] }}</span>
                        </td>
                        <td>
                            <button class="sd-review-btn js-review-btn"
                                data-question="{{ json_encode($row['questionData']) }}">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2.5">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                Review
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;font-style:italic;">No
                            questions found.</td>
                    </tr>
                @endforelse
                @if (count($answers) > 0)
                    <tr class="sd-no-results-row" hidden>
                        <td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;font-style:italic;">No
                            questions match this filter.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
    <div class="sd-pagination" data-table-pagination="{{ $tableId }}">
        <div class="sd-pagination-info" data-page-info></div>
        <div class="sd-pagination-controls">
            <button class="sd-page-btn" type="button" data-page-action="prev">Previous</button>
            <div class="sd-page-numbers" data-page-numbers></div>
            <button class="sd-page-btn" type="button" data-page-action="next">Next</button>
        </div>
    </div>
</div>
