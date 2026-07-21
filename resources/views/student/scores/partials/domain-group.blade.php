{{-- Partial: one domain-group section (RW or Math) — grid of domain cards for a section
     Variables expected: $sectionKey, $sectionLabel, $summaries
--}}
@if ($summaries->isNotEmpty())
    <div class="sd-domain-group" data-section="{{ $sectionKey }}" style="margin-bottom: 2rem;">
        <h4 class="sd-domain-section-label !font-bold">{{ $sectionLabel }}</h4>
        <div class="sd-domains-grid">
            @foreach ($summaries as $row)
                @php
                    $pct = $row['percentCorrect'] / 100;
                    $filled = max(1, round($pct * 7));
                    $perfLabel = $row['performance'];
                    $barClass = $perfLabel === 'High' ? '' : ($perfLabel === 'Medium' ? 'medium' : 'low');
                    $badgeClass = strtolower($perfLabel);
                    $skills = $row['skills'];
                @endphp
                <div class="sd-domain-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <div class="sd-domain-title">{{ $row['domain'] }}</div>
                            <div class="sd-domain-sub">({{ $row['coveragePercent'] }}% of test section,
                                {{ $row['total'] }} questions)</div>
                        </div>
                        <span class="sd-perf-badge {{ $badgeClass }}">
                            @if ($perfLabel === 'High')
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="3">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                            @elseif($perfLabel === 'Medium')
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="3">
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                </svg>
                            @else
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="3">
                                    <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
                                </svg>
                            @endif
                            {{ $perfLabel }}
                        </span>
                    </div>
                    <div class="sd-bars" style="margin-top: 0.5rem; margin-bottom: 0.8rem;">
                        @for ($i = 1; $i <= 7; $i++)
                            <div class="sd-bar {{ $i <= $filled ? 'filled ' . $barClass : 'empty' }}"></div>
                        @endfor
                    </div>

                    @if (!empty($skills))
                        <div class="sd-subskills-list"
                            style="margin-top: 0.8rem; border-top: 1px dashed #e2e8f0; padding-top: 0.8rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            @foreach ($skills as $skill)
                                @php
                                    $sPct = $skill['percentCorrect'];
                                    $tagBg = $sPct >= 80 ? '#f0fdf4' : ($sPct >= 50 ? '#f5f3ff' : '#fef2f2');
                                    $tagBorder =
                                        $sPct >= 80 ? '#bbf7d0' : ($sPct >= 50 ? '#ddd6fe' : '#fecaca');
                                    $tagColor = $sPct >= 80 ? '#15803d' : ($sPct >= 50 ? '#6d28d9' : '#b91c1c');
                                @endphp
                                <div class="sd-subskill-tag"
                                    style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.775rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 6px; background: {{ $tagBg }}; border: 1px solid {{ $tagBorder }}; color: {{ $tagColor }};">
                                    <span>{{ $skill['name'] }}</span>
                                    <span style="opacity: 0.6;">·</span>
                                    <span
                                        style="font-weight: 800;">{{ $skill['correct'] }}/{{ $skill['total'] }}
                                        ({{ $sPct }}%)
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
