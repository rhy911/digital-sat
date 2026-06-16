@props(['test'])

<div class="practice-card" tabindex="0">
    <!-- Cohesive Unified Header -->
    <div class="practice-card-header-cohesive">
        <div class="header-left">
            <span class="badge-sat">SAT</span>
            <h3 class="test-title">{{ \Illuminate\Support\Str::limit($test->test->title ?? '', 24, '...') }}</h3>
        </div>
        <span class="test-date">{{ $test->completed_at ? $test->completed_at->format('M d, Y') : 'N/A' }}</span>
    </div>

    <!-- Scores Split Body -->
    <div class="practice-card-score-body">
        <!-- Total Score Panel -->
        <div class="total-score-panel">
            <span class="score-label">Total Score</span>
            <span class="score-value">{{ $test->total_score ?? '---' }}</span>
            <span class="score-range">400–1600</span>
        </div>

        <!-- Section Scores breakdown -->
        <div class="section-scores-panel">
            <div class="section-score-item rw-item" tabindex="0">
                <div class="section-icon-wrapper-sm">
                    <svg class="section-icon-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <div class="section-info-sm">
                    <span class="section-name-sm">Reading & Writing</span>
                    <span class="section-score-sm">{{ $test->score_reading_writing ?? '---' }}</span>
                </div>
            </div>

            <div class="section-score-item math-item" tabindex="0">
                <div class="section-icon-wrapper-sm">
                    <svg class="section-icon-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="section-info-sm">
                    <span class="section-name-sm">Math</span>
                    <span class="section-score-sm">{{ $test->score_math ?? '---' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="practice-card-actions">
        <a href="{{ route('my-practice.score', $test) }}" class="practice-card-btn-primary">Score Details</a>
        <div class="practice-card-footer-link" role="button" tabindex="0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                stroke="currentColor" class="w-3.5 h-3.5" aria-hidden="true"
                style="width: 0.85rem; height: 0.85rem; display: inline-block;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            <span>Practice Specific Questions</span>
        </div>
    </div>
</div>