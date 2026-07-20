{{-- Shared performance partial: latest-score hero card.
     Expects: $latestScore, $scoreDelta, $scorePercent, $hasLatestScore, $latestCompleted --}}
<article class="progress-card ds-hero-score {{ $hasLatestScore ? '' : 'ds-hero-score--empty' }}"
    aria-labelledby="latest-score-title">
    <div>
        <span class="ds-card-label">Latest estimated practice score</span>
        <h2 id="latest-score-title">{{ $latestScore ?? 'No baseline yet' }}</h2>
        @if($latestCompleted)
            <p>
                Completed {{ optional($latestCompleted->completed_at)->format('M j, Y') ?? 'recently' }}.
                @if($scoreDelta !== null)
                    {{ $scoreDelta >= 0 ? 'Up' : 'Down' }} {{ abs($scoreDelta) }} points from the previous
                    completed test.
                @else
                    Complete another test to see score movement.
                @endif
            </p>
        @else
            <p>Finish one full-length practice test to unlock score movement and focus recommendations.</p>
        @endif

        @if($scoreDelta !== null)
            <span class="ds-score-change {{ $scoreDelta >= 0 ? 'is-positive' : 'is-negative' }}"
                aria-label="Score changed {{ $scoreDelta >= 0 ? 'up' : 'down' }} {{ abs($scoreDelta) }} points">
                {{ $scoreDelta >= 0 ? '+' : '-' }}{{ abs($scoreDelta) }} since previous test
            </span>
        @elseif(!$hasLatestScore)
            <span class="ds-score-change is-pending">Baseline needed</span>
        @endif
    </div>

    @if($hasLatestScore)
        <div class="ds-score-visual">
            <div class="ds-score-ring" role="img" aria-label="Latest estimated practice score {{ $latestScore }} out of 1600"
                style="--score-progress: {{ $scorePercent }}%">
                <span>{{ $latestScore }}</span>
            </div>
            <span class="ds-score-range">400-1600</span>
        </div>
    @else
        <div class="ds-score-placeholder" aria-hidden="true">
            <span>--</span>
        </div>
    @endif
</article>
