{{-- Partial: difficulty-tier performance summary for this attempt
     Variables expected: $difficultySummaries, $difficultyBySection
--}}
@php
    $bySection = $difficultyBySection ?? ['all' => $difficultySummaries];
@endphp

<div class="sd-difficulty-section" style="margin-top: 2rem; margin-bottom: 2.5rem;">
    <h3 class="sd-subsection-title" style="margin-bottom: 0.25rem;">Difficulty Breakdown</h3>
    <p class="sd-section-sub" style="margin-bottom: 1rem;">Accuracy by question difficulty across all practice questions in scope.</p>

    @foreach ($bySection as $scope => $tiers)
        <div class="sd-difficulty-group" data-difficulty-section="{{ $scope }}" style="{{ $scope !== 'all' ? 'display: none;' : '' }}">
            @if (count($tiers))
                <div class="sd-domains-grid sd-difficulty-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    @foreach ($tiers as $tier)
                        @php
                            $pct = $tier['percentCorrect'];
                            $diffLower = strtolower($tier['difficulty']);
                        @endphp
                        <div class="sd-domain-card" style="background: var(--ds-surface, #ffffff); border: 1px solid var(--ds-border-default, #e2e8f0); border-radius: 10px; padding: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                    <span class="sd-diff-dot {{ $diffLower }}"></span>
                                    <span class="sd-domain-title" style="font-size: 0.95rem; font-weight: 700; color: var(--ds-text-primary, #0f172a);">{{ $tier['label'] }}</span>
                                </div>
                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--ds-text-primary, #0f172a);">
                                    {{ $tier['correct'] }} <span style="color: var(--ds-text-tertiary, #64748b); font-weight: 500;">/ {{ $tier['total'] }}</span>
                                </span>
                            </div>

                            <div class="sd-accuracy-bar-track" style="margin-bottom: 0.4rem;">
                                <div class="sd-accuracy-bar-fill {{ $diffLower }}" style="width: {{ $pct }}%;"></div>
                            </div>

                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--ds-text-tertiary, #64748b);">
                                <span>{{ $tier['total'] }} question{{ $tier['total'] === 1 ? '' : 's' }}</span>
                                <span style="font-weight: 700; color: var(--ds-text-primary, #0f172a);">{{ $pct }}% accuracy</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p style="font-size: 0.85rem; color: var(--ds-text-secondary, #475569); font-style: italic;">No difficulty data recorded for this section.</p>
            @endif
        </div>
    @endforeach
</div>
