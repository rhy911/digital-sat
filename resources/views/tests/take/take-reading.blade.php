@php
    $testData->section_directions ??= "
        <div class='reading-directions-content'>
            <p>The questions in this section address a number of important reading and writing skills. Each question includes one or more passages, which may include a table or graph. Read each passage and question carefully, and then choose the best answer to the question based on the passage(s).</p>
            <p>All questions in this section are multiple-choice with four answer choices. Each question has a single best answer.</p>
            <p>&nbsp;</p>
            <p>&nbsp;</p>
        </div>
        ";

    $testData ??= (object) [
        'page_title' => 'Reading and Writing Section',
        'section_title' => 'Reading and Writing Questions',
        'username' => auth()->user()?->username ?? 'Guest',
    ];

    $questions ??= collect();
@endphp

<x-layouts.test :pageTitle="$testData->page_title" :sectionTitle="$testData->section_title" :sectionNumber="$sectionNumber" :moduleNumber="$moduleNumber" :sectionName="$sectionName"
    :sectionType="$sectionType" :sectionDirections="$testData->section_directions" :username="$testData->username" :currentQuestion="$currentQuestion" :totalQuestions="$totalQuestions">

    <div class="overlay" id="dropdownOverlay"></div>
    <div class="resizable-container">
        <div class="resizable-panel left-panel">
            @foreach ($questions as $index => $q)
                @if ($q->passage)
                    <div class="passage-container @if (!$loop->first) d-none @endif"
                        id="passage{{ $loop->iteration }}">@markdown($q->passage->content)</div>
                @else
                    <div class="passage-container @if (!$loop->first) d-none @endif"
                        id="passage{{ $loop->iteration }}">
                        <p class="text-muted italic">This question does not have a passage.</p>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="resizer">
            <svg width="18px" height="18px" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <g transform="rotate(90 10 10)">
                    <path fill="#fff"
                        d="M15.4067807,11.3333333 C16.0247676,11.3333333 16.1797643,11.9734248 15.7777728,12.3868172 L15.7777728,12.3868172 L10.5478836,17.777111 C10.2598897,18.0742963 9.73890077,18.0742963 9.45090688,17.777111 L9.45090688,17.777111 L4.22201765,12.3868172 C3.82002617,11.9734248 3.97602286,11.3333333 4.59200981,11.3333333 L4.59200981,11.3333333 Z M10.5478836,2.22288898 L15.7777728,7.61318284 C16.1797643,8.02657523 16.0247676,8.66666667 15.4067807,8.66666667 L4.59200981,8.66666667 C3.97602286,8.66666667 3.82002617,8.02657523 4.22201765,7.61318284 L9.45090688,2.22288898 C9.73890077,1.92570367 10.2598897,1.92570367 10.5478836,2.22288898 Z" />
                </g>
            </svg>
        </div>
        <div class="resizable-panel right-panel">
            @foreach ($questions as $q)
                <div class="question @if (!$loop->first) d-none @endif"
                    id="question{{ $loop->iteration }}" data-question-id="{{ $q->id }}"
                    data-section-type="{{ $q->section_type }}" data-question-type="{{ $q->question_type }}">
                    <div class="d-flex flex-column gap-3">
                        <div class="question-header d-flex align-items-center gap-3">
                            <div class="number">{{ $loop->iteration }}</div>
                            <span class="bookmark">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="feather feather-bookmark">
                                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                </svg>
                                Mark for Review
                            </span>
                        </div>
                        <div class="question-body">
                            <div class="stem-text mb-4">@markdown($q->stem)</div>

                            <div class="d-flex flex-column gap-3">
                                @foreach ($q->answerChoices->sortBy('order') as $choice)
                                    <div class="answer-row d-flex align-items-center gap-3">
                                        <div class="answer-option flex-grow-1">
                                            <input type="radio"
                                                id="q{{ $loop->parent->iteration }}{{ $choice->label }}"
                                                name="q{{ $loop->parent->iteration }}" value="{{ $choice->label }}">
                                            <label
                                                for="q{{ $loop->parent->iteration }}{{ $choice->label }}">@markdown($choice->content)</label>
                                        </div>
                                        <button type="button" class="strike-btn" title="Strike through">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="7" y1="7" x2="17" y2="17">
                                                </line>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @include('tests.take.partials.review-section')

    <script>
        window.nextModuleId = @json($nextModuleId ?? null);
        window.nextModuleName = @json($nextModuleName ?? null);
        window.userTestId = @json($userTestId ?? null);
        window.currentModuleId = @json($testData->module_id ?? null);
        window.isPreview = @json($testData->is_preview ?? false);
        window.durationMinutes = @json($testData->duration_minutes ?? 32);
    </script>
</x-layouts.test>
