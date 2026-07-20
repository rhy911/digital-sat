{{-- Shared performance partial: habits-card.blade.php --}}
<article class="progress-card ds-insight-card" aria-labelledby="skipped-analytics-title">
    <div class="ds-card__header">
        <div>
            <h3 id="skipped-analytics-title" class="ds-card-title">Test Taking Habits</h3>
            <p class="text-sm text-slate-600">Breakdown of skipped, rushed, or stuck questions.</p>
        </div>
    </div>
    
    <div class="ds-habits-body">
        <div class="ds-habit-item">
            <span class="ds-habit-icon is-skipped">∅</span>
            <div class="ds-habit-info">
                <strong>{{ $skippedCount }}</strong>
                <span>Skipped / Unanswered</span>
                <small>Leaving items blank hurts your score. Always guess if time runs low!</small>
            </div>
        </div>
        
        <div class="ds-habit-item">
            <span class="ds-habit-icon is-stuck">⌛</span>
            <div class="ds-habit-info">
                <strong>{{ $stuckCount }}</strong>
                <span>Stuck Questions</span>
                <small>Spent > 90s and answered incorrectly. Learn when to flag and skip!</small>
            </div>
        </div>

        <div class="ds-habit-item">
            <span class="ds-habit-icon is-rushed">⚡</span>
            <div class="ds-habit-info">
                <strong>{{ $rushedCount }}</strong>
                <span>Rushed Questions</span>
                <small>Answered correctly in < 15s. Quick thinking or lucky guesses.</small>
            </div>
        </div>
    </div>
</article>
