{{-- Partial: single domain performance card
     Variables expected: $domain, $data, $filled, $barClass, $badgeClass, $perfLabel, $secPct
--}}
@php
    // SAT domain label map: maps both database variants to the full SAT domain name
    $domainLabels = [
        // Reading & Writing
        'craft_and_structure' => 'Craft and Structure',
        'information_and_ideas' => 'Information and Ideas',
        'standard_english_conventions' => 'Standard English Conventions',
        'expression_of_ideas' => 'Expression of Ideas',
        // Math
        'algebra' => 'Algebra',
        'advanced_math' => 'Advanced Math',
        'problem_solving' => 'Problem-Solving and Data Analysis',
        'problem_solving_and_data_analysis' => 'Problem-Solving and Data Analysis',
        'geometry' => 'Geometry and Trigonometry',
        'geometry_and_trigonometry' => 'Geometry and Trigonometry',
    ];
    $domainLabel = $domainLabels[$domain] ?? ucwords(str_replace('_', ' ', $domain));
@endphp
<div class="sd-domain-card">
    <div class="sd-domain-title">{{ $domainLabel }}</div>
    <div class="sd-domain-sub">({{ $secPct }}% of test section, {{ $data['total'] }} questions)</div>
    <div class="sd-bars">
        @for ($i = 1; $i <= 7; $i++)
            <div class="sd-bar {{ $i <= $filled ? 'filled ' . $barClass : 'empty' }}"></div>
        @endfor
    </div>
    <span class="sd-perf-badge {{ $badgeClass }}">
        @if ($perfLabel === 'High')
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="20 6 9 17 4 12" />
            </svg>
        @elseif($perfLabel === 'Medium')
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="3">
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
        @else
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="3">
                <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
            </svg>
        @endif
        {{ $perfLabel }}
    </span>
</div>
