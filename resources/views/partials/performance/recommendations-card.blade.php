{{-- Shared performance partial: Actionable study recommendations note card --}}
@if(isset($recommendations) && count($recommendations))
    <div class="ds-study-card">
        <!-- Absolute positioned binder clip / tack pin -->
        <span class="ds-study-clip"></span>
        
        <div class="ds-study-card__header">
            <h4>Teacher's Actionable Study Tips</h4>
            <p>Targeted adjustments to boost your score based on your lowest sub-skills.</p>
        </div>
        
        <ul class="ds-study-list handwriting">
            @foreach($recommendations as $rec)
                <li>
                    <svg class="ds-study-bullet" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span>{!! $rec !!}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
