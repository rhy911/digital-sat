{{-- Shared performance partial: efficiency-card.blade.php --}}
<article class="progress-card ds-insight-card" aria-labelledby="time-efficiency-title">
    <div class="ds-card__header">
        <div>
            <h3 id="time-efficiency-title" class="ds-card-title">Time Efficiency</h3>
            <p class="text-sm text-slate-600">Average time spent on correct vs. incorrect answers.</p>
        </div>
    </div>
    
    <div class="ds-efficiency-body">
        <div class="ds-efficiency-section">
            <h4>Reading & Writing</h4>
            <div class="ds-eff-row">
                <span class="ds-eff-label">Correct</span>
                <div class="ds-eff-bar-wrapper">
                    <div class="ds-eff-bar is-correct" style="width: {{ min(100, ($timeMatrix['reading_and_writing']['correctAvg'] / 120) * 100) }}%"></div>
                </div>
                <span class="ds-eff-val">{{ $timeMatrix['reading_and_writing']['correctAvg'] }}s</span>
            </div>
            <div class="ds-eff-row">
                <span class="ds-eff-label">Incorrect</span>
                <div class="ds-eff-bar-wrapper">
                    <div class="ds-eff-bar is-incorrect" style="width: {{ min(100, ($timeMatrix['reading_and_writing']['incorrectAvg'] / 120) * 100) }}%"></div>
                </div>
                <span class="ds-eff-val">{{ $timeMatrix['reading_and_writing']['incorrectAvg'] }}s</span>
            </div>
        </div>

        <div class="ds-efficiency-section" style="margin-top: 1.5rem;">
            <h4>Math</h4>
            <div class="ds-eff-row">
                <span class="ds-eff-label">Correct</span>
                <div class="ds-eff-bar-wrapper">
                    <div class="ds-eff-bar is-correct" style="width: {{ min(100, ($timeMatrix['math']['correctAvg'] / 120) * 100) }}%"></div>
                </div>
                <span class="ds-eff-val">{{ $timeMatrix['math']['correctAvg'] }}s</span>
            </div>
            <div class="ds-eff-row">
                <span class="ds-eff-label">Incorrect</span>
                <div class="ds-eff-bar-wrapper">
                    <div class="ds-eff-bar is-incorrect" style="width: {{ min(100, ($timeMatrix['math']['incorrectAvg'] / 120) * 100) }}%"></div>
                </div>
                <span class="ds-eff-val">{{ $timeMatrix['math']['incorrectAvg'] }}s</span>
            </div>
        </div>
    </div>
</article>
