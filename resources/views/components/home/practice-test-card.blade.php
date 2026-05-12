@props(['test'])

<div class="practice-card">
    <!-- Header -->
    <div class="practice-card-header">
        <span class="text-white text-2xl font-semibold">SAT</span>
    </div>

    <!-- Sub-header -->
    <div class="practice-card-subheader">
        <span class="text-white text-[14px] font-semibold tracking-wider uppercase">{{ $test->test->title }}</span>
        <span class="text-white text-sm">{{ $test->completed_at ? $test->completed_at->format('M d, Y') : 'N/A' }}</span>
    </div>

    <!-- Score banner -->
    <div class="practice-card-score-banner">
        <p class="m-0 mb-1 text-xs font-medium text-[#444] uppercase tracking-[0.5px]">Your Total Score</p>
        <p class="practice-card-total-score">{{ $test->total_score ?? '---' }}</p>
        <p class="practice-card-score-range">400–1600</p>
    </div>

    <!-- Section scores -->
    <div class="practice-card-sections">
        <div class="practice-card-section-row mb-14">
            <div>
                <p class="practice-card-section-name">Reading and Writing</p>
                <p class="practice-card-section-range">200–800</p>
            </div>
            <span class="practice-card-section-score">{{ $test->score_reading_writing ?? '---' }}</span>
        </div>
        <hr class="practice-card-hr">
        <div class="practice-card-section-row">
            <div>
                <p class="practice-card-section-name">Math</p>
                <p class="practice-card-section-range">200–800</p>
            </div>
            <span class="practice-card-section-score">{{ $test->score_math ?? '---' }}</span>
        </div>
    </div>

    <!-- Actions -->
    <div class="practice-card-actions">
        <button class="practice-card-btn-primary">Score Details</button>
        <div class="practice-card-footer-link">
            <span>☰ Practice Specific Questions</span>
        </div>
    </div>
</div>
