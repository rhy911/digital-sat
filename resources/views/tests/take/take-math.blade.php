@php
    $testData->section_directions ??= "
        <div class='math-directions-content'>
            <p>The question in this section address a number of important math skills.</p>
            <p>Use of calculator is permitted for all questions. A reference sheet, calculator, and these directions can be accessed throughout the test.</p>
            
            <p>Unless otherwise indicated:</p>
            <ul class='list-disc ps-6'>
                <li class='mb-1'>All variables and expressions represent real numbers.</li>
                <li class='mb-1'>Figures provided are drawn to scale.</li>
                <li class='mb-1'>All figures lie in a plane.</li>
                <li class='mb-1'>The domain of a given function f is the set of all real numbers x for which f(x) is a real number.</li>
            </ul>

            <p>For <strong>multiple-choice questions</strong>, solve each problem and choose the correct answer from the choices provided. Each multiple-choice questions has a single correct answer.</p>
            
            <p>For <strong>student-produced response questions</strong>, solve each problem and enter your answer as described below.</p>
            <ul class='list-disc ps-6'>
                <li>If you find <strong>more than one correct answers</strong>, enter only one answer.</li>
                <li>You can enter up to 5 characters for a <strong>positive</strong> answer and up to 6 characters (including the negative sign) for a <strong>negative</strong> answer.</li>
                <li>If your answer is a <strong>fraction</strong> that doesn't fit in the provided space, enter the decimal equivalent.</li>
                <li>If your answer is a <strong>decimal</strong> that doesn't fit in the provided space, enter it by truncating or rounding at the fourth digit.</li>
                <li>If your answer is a <strong>mixed number</strong> (such as 3 1/2), enter it as an improper fraction (7/2) or its decimal equivalent (3.5).</li>
                <li>Don't enter <strong>symbols</strong> such as a percent sign, comma, or dollar sign.</li>
            </ul>
        </div>
        ";

    $testData ??= (object) [
        'page_title' => 'Math Section',
        'section_title' => 'Math Questions',
        'username' => auth()->user()?->username ?? 'Guest',
    ];

    $questions ??= collect();
    $hasSPR = $questions->contains('question_type', 'student_produced_response');
@endphp

<x-layouts.test :pageTitle="$testData->page_title" :sectionTitle="$testData->section_title" :sectionNumber="$sectionNumber" :moduleNumber="$moduleNumber" :sectionName="$sectionName"
    :sectionType="$sectionType" :sectionDirections="$testData->section_directions" :username="$testData->username" :currentQuestion="$currentQuestion" :totalQuestions="$totalQuestions">

    <div class="overlay" id="dropdownOverlay"></div>
    <div class="resizable-container">
        {{-- Left Panel: Only shown if there is a passage (rare in Math) or for SPR directions --}}
        @if ($hasSPR || $questions->contains(fn($q) => !empty($q->passage_id)))
            <div class="resizable-panel left-panel">
                @foreach ($questions as $index => $q)
                    <div class="passage-container @if (!$loop->first) d-none @endif"
                        id="passage{{ $loop->iteration }}">
                        @if ($q->passage)
                            {!! Str::markdown($q->passage->content) !!}
                        @elseif($q->question_type === 'student_produced_response')
                            <div class="spr-directions p-3">
                                <h5 class="fw-bold mb-3">Student-produced response directions</h5>
                                <ul class="ps-6 list-disc">
                                    <li>If you find <strong>more than one correct answers</strong>, enter only one
                                        answer.</li>
                                    <li>You can enter up to 5 characters for a <strong>positive</strong> answer and up
                                        to 6 characters (including the negative sign) for a <strong>negative</strong>
                                        answer.</li>
                                    <li>If your answer is a <strong>fraction</strong> that doesn't fit in the provided
                                        space, enter the decimal equivalent.</li>
                                    <li>If your answer is a <strong>decimal</strong> that doesn't fit in the provided
                                        space, enter it by truncating or rounding at the fourth digit.</li>
                                    <li>If your answer is a <strong>mixed number</strong> (such as 3 1/2), enter it as
                                        an improper fraction (7/2) or its decimal equivalent (3.5).</li>
                                    <li>Don't enter <strong>symbols</strong> such as a percent sign, comma, or dollar
                                        sign.</li>
                                </ul>
                            </div>
                        @else
                            <p class="text-muted italic">Reference formulas or notes for this math question can be found
                                here.</p>
                        @endif
                    </div>
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
        @endif

        {{-- Right Panel: Centered if no left panel --}}
        <div class="resizable-panel right-panel">
            @foreach ($questions as $q)
                <div class="question @if (!$loop->first) d-none @endif"
                    id="question{{ $loop->iteration }}" 
                    data-question-id="{{ $q->id }}"
                    data-section-type="{{ $q->section_type }}"
                    data-question-type="{{ $q->question_type }}">
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
                            <div class="stem-text mb-4">{!! trim(Str::markdown($q->stem, ['html_input' => 'strip', 'allow_unsafe_links' => false])) !!}</div>

                            <div class="d-flex flex-column gap-3">
                                @if ($q->question_type === 'student_produced_response')
                                    <div class="answer-input-container">
                                        <label class="d-block mb-2 fw-bold">Enter your answer:</label>
                                        <input type="text" class="form-control spr-input"
                                            name="q{{ $loop->iteration }}" placeholder="______" maxlength="6">
                                        @if ($q->spr_hint)
                                            <div class="form-text mt-1 italic text-muted small">{{ $q->spr_hint }}
                                            </div>
                                        @endif

                                        <h4 class="spr-preview mt-4">Answer Preview: <span class="preview-value"></span>
                                        </h4>
                                    </div>
                                @else
                                    @foreach ($q->answerChoices->sortBy('order') as $choice)
                                        <div class="answer-option">
                                            <input type="radio"
                                                id="q{{ $loop->parent->iteration }}{{ $choice->label }}"
                                                name="q{{ $loop->parent->iteration }}" value="{{ $choice->label }}">
                                            <label
                                                for="q{{ $loop->parent->iteration }}{{ $choice->label }}">{!! Str::markdown($choice->content) !!}</label>
                                        </div>
                                    @endforeach
                                @endif
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
    </script>
</x-layouts.test>
