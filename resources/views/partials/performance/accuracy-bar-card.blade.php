{{-- Shared performance partial: accuracy school card (domains / difficulty).
     Expects: $title, $subtitle, $rows (array of {domain/label, total, correct, percentCorrect, performance, skills[] (optional)}),
              $emptyTitle, $emptyBody, $ariaLabelledby, $rowLabelKey ('domain' or 'label') --}}
<article class="progress-card" aria-labelledby="{{ $ariaLabelledby }}">
    <div class="ds-card__header">
        <div>
            <h3 id="{{ $ariaLabelledby }}" class="ds-card-title">{{ $title }}</h3>
            <p class="text-sm text-slate-600">{{ $subtitle }}</p>
        </div>
    </div>

    @if(count($rows))
        <div class="ds-school-list">
            @foreach($rows as $index => $row)
                @php
                    $label = $row[$rowLabelKey];
                    $pct = $row['percentCorrect'];
                    
                    // Letter Grade calculation
                    $grade = $pct >= 90 ? 'A+' : ($pct >= 80 ? 'A' : ($pct >= 65 ? 'B' : ($pct >= 50 ? 'C' : ($pct >= 35 ? 'D' : 'F'))));
                    $gradeClass = $pct >= 80 ? 'grade-a' : ($pct >= 50 ? 'grade-c' : 'grade-f');
                    
                    // Alpine collapse state index
                    $hasSkills = !empty($row['skills']);
                @endphp
                <div class="ds-school-item {{ $hasSkills ? 'has-skills' : '' }}" 
                     @if($hasSkills) x-data="{ open: false }" @endif>
                    
                    <div class="ds-item-main" @if($hasSkills) @click="open = !open" style="cursor: pointer;" @endif>
                        <!-- Circular Grade Stamp in Red/Amber ink -->
                        <span class="ds-grade-stamp {{ $gradeClass }}">{{ $grade }}</span>

                        <!-- Info -->
                        <div class="ds-item-info">
                            <span class="ds-item-title">{{ $label }}</span>
                            @if($rowLabelKey === 'domain')
                                <small class="ds-item-subtitle">{{ $row['section'] }}</small>
                            @endif
                        </div>

                        <!-- 10-Dot Math Graph Paper Waffle Line -->
                        <div class="ds-waffle-row" aria-label="Accuracy {{ $pct }}%" title="Accuracy: {{ $pct }}%">
                            @for($d = 1; $d <= 10; $d++)
                                <span class="ds-waffle-dot {{ ($d * 10) <= $pct ? 'is-active' : '' }} {{ $gradeClass }}"></span>
                            @endfor
                        </div>

                        <!-- Numeric details -->
                        <span class="ds-item-stats">
                            <strong>{{ $pct }}%</strong>
                            <small>{{ $row['correct'] }}/{{ $row['total'] }}</small>
                        </span>

                        @if($hasSkills)
                            <!-- Collapsible Caret indicator -->
                            <span class="ds-expand-arrow" :class="{ 'is-expanded': open }">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </span>
                        @endif
                    </div>

                    @if($hasSkills)
                        <!-- Collapsible Child Skills List -->
                        <div class="ds-skills-list" x-show="open" x-cloak x-transition>
                            @foreach($row['skills'] as $skill)
                                @php
                                    $sPct = $skill['percentCorrect'];
                                    $sGrade = $sPct >= 90 ? 'A+' : ($sPct >= 80 ? 'A' : ($sPct >= 65 ? 'B' : ($sPct >= 50 ? 'C' : ($sPct >= 35 ? 'D' : 'F'))));
                                    $sGradeClass = $sPct >= 80 ? 'grade-a' : ($sPct >= 50 ? 'grade-c' : 'grade-f');
                                @endphp
                                <div class="ds-skill-row">
                                    <!-- Indented notebook page dashed checklist line indicator -->
                                    <span class="ds-skill-bullet"></span>
                                    
                                    <div class="ds-skill-info">
                                        <span class="ds-skill-name">{{ $skill['name'] }}</span>
                                    </div>

                                    <!-- 10-Dot Math Graph Paper Waffle Line (smaller) -->
                                    <div class="ds-waffle-row is-small" aria-label="Accuracy {{ $sPct }}%">
                                        @for($d = 1; $d <= 10; $d++)
                                            <span class="ds-waffle-dot {{ ($d * 10) <= $sPct ? 'is-active' : '' }} {{ $sGradeClass }}"></span>
                                        @endfor
                                    </div>

                                    <span class="ds-skill-stats">
                                        <strong>{{ $sPct }}%</strong>
                                        <small>{{ $skill['correct'] }}/{{ $skill['total'] }}</small>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="ds-empty ds-empty--compact">
            <h4>{{ $emptyTitle }}</h4>
            <p>{{ $emptyBody }}</p>
        </div>
    @endif
</article>
