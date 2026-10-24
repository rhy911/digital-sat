@php
    $trendAttempts = $homeScoreTrend['attempts'] ?? [];
    $attemptCount = count($trendAttempts);
    $calendarSpacing = $attemptCount > 0 && collect($trendAttempts)->every(fn ($attempt) => $attempt['timestamp'] !== null);
    $times = array_column($trendAttempts, 'timestamp');
    $firstTime = $calendarSpacing ? min($times) : null;
    $lastTime = $calendarSpacing ? max($times) : null;
    $points = [];
    $rangeValues = array_column($trendAttempts, 'score');
    if ($user->target_score) $rangeValues[] = (int) $user->target_score;
    $axisMin = !$isTeacher && $rangeValues ? max(400, floor((min($rangeValues) - 50) / 100) * 100) : 400;
    $axisMax = !$isTeacher && $rangeValues ? min(1600, ceil((max($rangeValues) + 50) / 100) * 100) : 1600;
    if ($axisMax - $axisMin < 300) {
        $axisMin = max(400, min($axisMin, $axisMax - 300));
        $axisMax = min(1600, max($axisMax, $axisMin + 300));
    }
    foreach ($trendAttempts as $index => $attempt) {
        $fraction = $calendarSpacing
            ? ($lastTime > $firstTime ? ($attempt['timestamp'] - $firstTime) / ($lastTime - $firstTime) : 0.5)
            : ($attemptCount > 1 ? $index / ($attemptCount - 1) : 0.5);
        $points[] = $attempt + ['x' => round(5 + $fraction * 90, 3), 'y' => round(95 - (($attempt['score'] - $axisMin) / ($axisMax - $axisMin)) * 90, 3)];
    }
    $pointString = implode(' ', array_map(fn ($point) => $point['x'] . ',' . $point['y'], $points));
    $latestPoint = $attemptCount ? $trendAttempts[$attemptCount - 1] : null;
    $initialTargetY = $user->target_score ? 95 - (($user->target_score - $axisMin) / ($axisMax - $axisMin)) * 90 : 0;
@endphp

<p class="home-history__summary">
    {{ $calendarSpacing ? 'Full tests spaced by completion date.' : 'Recent full tests in attempt order.' }}
    @if($latestPoint)<span class="home-num">Latest: <strong>{{ $latestPoint['score'] }} / 1600</strong></span>@endif
</p>
@if(!$isTeacher)
    <p class="home-history__summary" x-show="savedGoal !== null" @if(!$user->target_score) x-cloak @endif>Dashed line: target <span x-text="savedGoal">{{ $user->target_score }}</span></p>
@endif
@if($attemptCount)
    <div class="home-history" data-home-score-history>
        <div class="home-history__axis" aria-hidden="true"><span @if(!$isTeacher) x-text="chartRange.max" @endif>{{ $axisMax }}</span><span @if(!$isTeacher) x-text="(chartRange.min + chartRange.max) / 2" @endif>{{ ($axisMin + $axisMax) / 2 }}</span><span @if(!$isTeacher) x-text="chartRange.min" @endif>{{ $axisMin }}</span></div>
        <div class="home-history__plot">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="Recent full-test scores; complete values are available in View score data">
                @foreach([5, 50, 95] as $gridY)<line x1="0" x2="100" y1="{{ $gridY }}" y2="{{ $gridY }}" class="home-sparkline__grid" vector-effect="non-scaling-stroke" />@endforeach
                <line x1="0" x2="100" y1="{{ $initialTargetY }}" y2="{{ $initialTargetY }}" class="home-sparkline__target-line" vector-effect="non-scaling-stroke"
                    @if(!$isTeacher) x-show="savedGoal !== null" :y1="goalY" :y2="goalY" @elseif(!$user->target_score) style="display:none" @endif />
                @if($attemptCount > 1)<polyline points="{{ $pointString }}" @if(!$isTeacher) :points="@js($points).map(point => `${point.x},${scoreY(point.score)}`).join(' ')" @endif class="home-sparkline__line" vector-effect="non-scaling-stroke" />@endif
            </svg>
            @foreach($points as $point)
                <button type="button" @class(['home-history__point', 'home-history__point--end' => $point['x'] >= 50]) style="inset-inline-start: {{ $point['x'] }}%; top: {{ $point['y'] }}%" @if(!$isTeacher) :style="{ top: scoreY({{ $point['score'] }}) + '%' }" @endif aria-label="{{ $point['score'] }} out of 1600, {{ $point['dateFull'] ?? 'Undated' }}" x-data="{ tooltipDismissed: false }" @keydown.escape.stop="tooltipDismissed = true" @focus="tooltipDismissed = false" @mouseenter="tooltipDismissed = false">
                    <span class="home-history__tooltip" x-show="!tooltipDismissed">{{ $point['score'] }} · {{ $point['dateFull'] ?? 'Undated' }}</span>
                </button>
            @endforeach
        </div>
        <div class="home-history__dates" aria-hidden="true"><span>{{ $trendAttempts[0]['dateFull'] ?? 'Undated' }}</span>@if($attemptCount > 1)<span>{{ $latestPoint['dateFull'] ?? 'Undated' }}</span>@endif</div>
    </div>
    @if($attemptCount === 1)<p class="home-note">1 test recorded. Complete additional full-length tests to see your progress curve.</p>@endif
    <details class="home-history__data">
        <summary>View score data</summary>
        <table>
            <caption class="sr-only">Score progression history for recent full tests</caption>
            <thead><tr><th scope="col">Date</th><th scope="col">Score</th><th scope="col">Target</th></tr></thead>
            <tbody>
                @foreach($trendAttempts as $attempt)
                    <tr><td>{{ $attempt['dateFull'] ?? 'Undated' }}</td><td>{{ $attempt['score'] }} / 1600</td><td @if(!$isTeacher) x-text="savedGoal === null ? 'Not set' : savedGoal" @endif>{{ $user->target_score ?? 'Not set' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>
@else
    <p class="home-note">Complete a full-length adaptive test to begin tracking your score trajectory.</p>
@endif
