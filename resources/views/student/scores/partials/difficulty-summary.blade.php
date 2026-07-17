{{-- Partial: difficulty-tier performance summary for this attempt
     Variables expected: $difficultySummaries — array of ['label','total','correct','percentCorrect','performance']
--}}
@if (count($difficultySummaries))
    <h2 class="text-3xl font-bold">Difficulty Breakdown</h2>
    <p class="sd-section-sub">Accuracy by question difficulty for this attempt.</p>

    <div class="sd-domains-grid sd-difficulty-grid">
        @foreach ($difficultySummaries as $tier)
            @php
                $pct = $tier['total'] > 0 ? $tier['correct'] / $tier['total'] : 0;
                $filled = max(1, round($pct * 7));
                $barClass = $tier['performance'] === 'High' ? '' : ($tier['performance'] === 'Medium' ? 'medium' : 'low');
                $badgeClass = strtolower($tier['performance']);
            @endphp
            <div class="sd-domain-card">
                <div class="sd-domain-title">{{ $tier['label'] }}</div>
                <div class="sd-domain-sub">({{ $tier['total'] }} question{{ $tier['total'] === 1 ? '' : 's' }})</div>
                <div class="sd-bars">
                    @for ($i = 1; $i <= 7; $i++)
                        <div class="sd-bar {{ $i <= $filled ? 'filled ' . $barClass : 'empty' }}"></div>
                    @endfor
                </div>
                <span class="sd-perf-badge {{ $badgeClass }}">{{ $tier['percentCorrect'] }}% correct</span>
            </div>
        @endforeach
    </div>
@endif
