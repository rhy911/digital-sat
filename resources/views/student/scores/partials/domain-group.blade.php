{{-- Partial: one domain-group section (RW or Math) — grid of domain cards for a section
     Variables expected: $sectionKey, $sectionLabel, $summaries
--}}
@if ($summaries->isNotEmpty())
    <div class="sd-domain-group" data-section="{{ $sectionKey }}" style="margin-bottom: 2rem;">
        <h3 class="sd-domain-section-label" style="font-size: 1.15rem; font-weight: 700; color: var(--ds-text-primary, #0f172a); margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
            <span class="sd-sec-dot {{ $sectionKey }}"></span>
            {{ $sectionLabel }}
        </h3>
        <div class="sd-domains-grid">
            @foreach ($summaries as $row)
                @php
                    $pct = $row['percentCorrect'];
                    $skills = $row['skills'] ?? [];
                @endphp
                <div class="sd-domain-card">
                    <div class="sd-domain-card-heading">
                        <div>
                            <div class="sd-domain-title">{{ $row['domain'] }}</div>
                            <div class="sd-domain-sub">{{ $row['coveragePercent'] }}% of section questions</div>
                        </div>
                        <div class="sd-domain-result">
                            <span class="sd-domain-counts">
                                {{ $row['correct'] }} <span style="font-size: 0.8rem; font-weight: 600; color: var(--ds-text-tertiary, #64748b);">/ {{ $row['total'] }}</span>
                            </span>
                            <div class="sd-domain-accuracy">{{ $pct }}% accuracy</div>
                        </div>
                    </div>

                    {{-- Proportional Accuracy Bar --}}
                    <div class="sd-accuracy-bar-track" aria-hidden="true">
                        <div class="sd-accuracy-bar-fill brand" style="width: {{ $pct }}%;"></div>
                    </div>

                    @if (!empty($skills))
                        <details class="sd-subskills-accordion" open>
                            <summary>
                                <span>{{ count($skills) }} subskills observed</span>
                                <span class="sd-subskills-toggle">
                                    <span class="sd-subskills-open-label">Hide details</span>
                                    <span class="sd-subskills-closed-label">Show details</span>
                                </span>
                            </summary>
                            <div class="sd-subskills-list">
                                @foreach ($skills as $skill)
                                    <div class="sd-subskill-item">
                                        <span>{{ $skill['name'] }}</span>
                                        <strong>{{ $skill['correct'] }}/{{ $skill['total'] }} ({{ $skill['percentCorrect'] }}%)</strong>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
