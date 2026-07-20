{{-- Shared performance partial: recent score trend chart + history list.
     Expects: $history, $hasSingleHistory, $baselineAttempt, $latestScore, $scoreDelta,
              $hasOlderScoreVersion, $latestScoreAttempt, $latestCompleted, $reviewRoute
              (closure: UserTest -> string|null; null means the row is not clickable),
              $showBadges (bool, default false — teacher view distinguishes assignment
              vs shared-independent attempts). --}}
@php
    $showBadges = $showBadges ?? false;
@endphp
<article class="progress-card ds-trend-card" aria-labelledby="trend-title">
    <div class="ds-card__header">
        <div>
            <h3 id="trend-title" class="ds-card-title">Recent estimated score movement</h3>
            @if($hasOlderScoreVersion)
                <p class="text-sm text-slate-600">Trend uses {{ $latestScoreAttempt?->score_conversion_version ?? 'legacy' }} conversion only.</p>
            @endif
        </div>
        @if($latestCompleted && ($reviewRoute)($latestCompleted))
            <a href="{{ ($reviewRoute)($latestCompleted) }}" class="ds-link">Open score report</a>
        @endif
    </div>

    @if($history->isNotEmpty())
        <div class="ds-trend-body {{ $hasSingleHistory ? 'is-single' : '' }}">
            @if($hasSingleHistory && $baselineAttempt)
                <div class="ds-baseline-card" aria-label="Baseline estimated practice score {{ $baselineAttempt->total_score }}">
                    <span>Baseline</span>
                    <strong>{{ $baselineAttempt->total_score }}</strong>
                    <small>{{ optional($baselineAttempt->completed_at)->format('M j, Y') ?? 'Completed' }}</small>
                </div>
            @endif

            @unless($hasSingleHistory)
                <div class="ds-trend-panel">
                    @php
                        $trendCount = max(1, $history->count());
                        $pointsArray = [];
                        foreach ($history->values() as $index => $attempt) {
                            $score = max(400, min(1600, (int) $attempt->total_score));
                            $x = $trendCount === 1 ? 50 : round(($index / ($trendCount - 1)) * 100, 2);
                            $y = round(100 - ((($score - 400) / 1200) * 100), 2);
                            $pointsArray[] = ['x' => $x, 'y' => $y];
                        }
                        $trendPoints = collect($pointsArray)->map(fn($p) => "{$p['x']},{$p['y']}")->implode(' ');
                        
                        $areaPoints = "";
                        if (count($pointsArray) > 0) {
                            $first = $pointsArray[0];
                            $last = $pointsArray[count($pointsArray) - 1];
                            $areaPoints = "{$first['x']},100 " . collect($pointsArray)->map(fn($p) => "{$p['x']},{$p['y']}")->implode(' ') . " {$last['x']},100";
                        }
                    @endphp
                    <div class="ds-trend-chart"
                        aria-label="Last {{ $history->count() }} completed test scores from 400 to 1600"
                        style="--history-count: {{ $history->count() }}">
                        <div class="ds-trend-chart__plot" aria-hidden="true">
                            <span class="ds-trend-chart__axis is-top">1600</span>
                            <span class="ds-trend-chart__axis is-bottom">400</span>
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false">
                                <defs>
                                    <linearGradient id="trend-area-grad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="var(--accent, #2A4D8F)" stop-opacity="0.25" />
                                        <stop offset="100%" stop-color="var(--accent, #2A4D8F)" stop-opacity="0.00" />
                                    </linearGradient>
                                </defs>
                                @if($areaPoints)
                                    <polygon points="{{ $areaPoints }}" fill="url(#trend-area-grad)" />
                                @endif
                                <polyline points="{{ $trendPoints }}" />
                            </svg>

                            @foreach($history as $attempt)
                                @php
                                    $score = max(400, min(1600, (int) $attempt->total_score));
                                    $x = $trendCount === 1 ? 50 : round(($loop->index / ($trendCount - 1)) * 100, 2);
                                    $y = round(100 - ((($score - 400) / 1200) * 100), 2);
                                @endphp
                                <span class="ds-trend-chart__point" style="--x: {{ $x }}; --y: {{ $y }};"></span>
                            @endforeach
                        </div>

                        <div class="ds-trend-chart__labels">
                            @foreach($history as $attempt)
                                <div class="ds-trend-chart__label"
                                    aria-label="Estimated practice score {{ $attempt->total_score }} on {{ optional($attempt->completed_at)->format('M j') ?? 'Done' }}">
                                    <strong>{{ $attempt->total_score }}</strong>
                                    <small>{{ optional($attempt->completed_at)->format('M j') ?? 'Done' }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endunless

            <div class="ds-trend-insight">
                <span>{{ $hasSingleHistory ? 'Baseline captured' : 'Latest movement' }}</span>
                <strong>{{ $latestScore }}</strong>
                @if($scoreDelta !== null)
                    <p>{{ $scoreDelta >= 0 ? 'Up' : 'Down' }} {{ abs($scoreDelta) }} points from the previous
                        completed test.</p>
                @else
                    <p>Complete one more full-length practice to turn this into a real movement trend.</p>
                @endif
            </div>
        </div>

        <ol class="ds-history-list" aria-label="Recent completed score reports">
            @foreach($history->reverse()->values() as $attempt)
                @php($route = ($reviewRoute)($attempt))
                <li>
                    <span>{{ optional($attempt->completed_at)->format('M j') ?? 'Completed' }}</span>
                    <strong>{{ $attempt->total_score }}</strong>
                    @if($showBadges)
                        <span class="ds-history-badge {{ $attempt->assignment_id ? 'is-assignment' : 'is-shared' }}">
                            {{ $attempt->assignment_id ? 'Assignment' : 'Shared practice' }}
                        </span>
                    @endif
                    @if($route)
                        <a href="{{ $route }}" class="ds-link">Review</a>
                    @endif
                </li>
            @endforeach
        </ol>
    @else
        <div class="ds-empty">
            <h4>No completed practice yet</h4>
            <p>Finish a full-length practice test and your score trend will appear here.</p>
        </div>
    @endif
</article>
