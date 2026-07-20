{{-- Shared performance partial: pacing bar chart (avg time spent vs. expected,
     by difficulty tier). Expects: $rows (pacingSummaries shape) --}}
<article class="progress-card" aria-labelledby="pacing-title">
    <div class="ds-card__header">
        <div>
            <h3 id="pacing-title" class="ds-card-title">Pacing</h3>
            <p class="text-sm text-slate-600">Average time spent vs. expected time, by difficulty.</p>
        </div>
    </div>

    @if(count($rows))
        <div class="ds-bar-list">
            @foreach($rows as $row)
                @php
                    $scaledWidth = min($row['pacePercent'], 150) / 150 * 100;
                @endphp
                <div class="ds-bar-row">
                    <span class="ds-bar-row__label">{{ $row['label'] }}<small>{{ $row['pace'] }}</small></span>
                    <span class="ds-bar-track is-pacing" role="img" aria-label="{{ $row['label'] }} average {{ $row['avgTimeSpent'] }} seconds versus {{ $row['avgExpectedTime'] }} seconds expected">
                        <span class="ds-bar-fill is-pace" style="width: {{ $scaledWidth }}%"></span>
                        <!-- Expected baseline marker represented by a red needle flag pin -->
                        <span class="ds-bar-track__needle" style="left: 66.67%" title="On-pace baseline expected: {{ $row['avgExpectedTime'] }}s"></span>
                    </span>
                    <span class="ds-bar-row__value">{{ $row['avgTimeSpent'] }}s<small>vs {{ $row['avgExpectedTime'] }}s</small></span>
                </div>
            @endforeach
        </div>
    @else
        <div class="ds-empty ds-empty--compact">
            <h4>No pacing data yet</h4>
            <p>Complete a practice test to see how your timing compares to expected pace.</p>
        </div>
    @endif
</article>
