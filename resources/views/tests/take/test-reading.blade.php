@php
    $testData ??= (object) [
        'page_title' => "No Title Available",
        'section_title' => "No Section Title Available",
        'section_directions' => "No directions available.",
        'username' => auth()->user()?->username ?? "Guest"
    ];

    $questions ??= collect();
@endphp

<x-layouts.test :pageTitle="$testData->page_title"
    :sectionTitle="$testData->section_title"
    :sectionDirections="$testData->section_directions"
    :username="$testData->username"
    :currentQuestion="$currentQuestion"
    :totalQuestions="$totalQuestions">
    <div class="overlay" id="dropdownOverlay"></div>
    <div class="resizable-container">
        <div class="resizable-panel left-panel">
            @foreach($questions as $index => $q)
                @if($q->passage)
                    <div class="passage-container @if(!$loop->first) d-none @endif" id="passage{{ $q->question_number }}">
                        {!! $q->passage->content !!}
                    </div>
                @elseif($q->section_type === 'math')
                    <div class="passage-container @if(!$loop->first) d-none @endif" id="passage{{ $q->question_number }}">
                        <p class="text-muted italic">Reference formulas or notes for this math question can be found here.</p>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="resizer">
            <svg fill="#ffffff" width="18" height="18" viewBox="0 0 6.4 6.4" xmlns="http://www.w3.org/2000/svg"><path d="M3.4 1v4.4a.2.2 0 0 1-.4 0V1a.2.2 0 0 1 .4 0m-1 2H.883l.459-.459a.2.2 0 0 0-.283-.283l-.8.8-.004.004-.009.01-.006.009-.005.007-.006.01-.004.007-.005.01-.004.008-.004.01-.003.009-.003.01-.002.009-.002.011-.001.008a.2.2 0 0 0 0 .04l.001.008.002.011.002.009.003.01.003.009.004.01.004.008.005.01.004.007.006.01.005.007.006.009.009.01.004.004.8.8a.2.2 0 0 0 .283-.283L.883 3.4H2.4a.2.2 0 0 0 0-.4m3.755.327.006-.009.005-.007.006-.01.004-.007.005-.01.004-.008.004-.01.003-.009.003-.01.002-.009.002-.011.001-.008a.2.2 0 0 0 0-.04l-.001-.008-.002-.011-.002-.009-.003-.01-.003-.009-.004-.01-.004-.008-.005-.01-.004-.007-.006-.01-.005-.007-.006-.009-.009-.01-.004-.004-.8-.8a.2.2 0 0 0-.283.283l.458.46H4a.2.2 0 0 0 0 .4h1.517l-.459.459a.2.2 0 0 0 .283.283l.8-.8.004-.004.009-.01"/></svg>
        </div>
        <div class="resizable-panel right-panel">
            @foreach($questions as $q)
            <div class="question @if(!$loop->first) d-none @endif" id="question{{ $q->question_number }}">
                <div class="d-flex flex-column gap-3">
                    <div class="question-header d-flex align-items-center gap-3">
                        <div class="number">{{ $q->question_number }}</div>
                        <span class="bookmark">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-bookmark">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                            </svg>
                            Mark for Review
                        </span>
                    </div>
                    <div class="question-body">
                        <p>{!! $q->stem !!}</p>
                        <div class="d-flex flex-column gap-3">
                            @foreach ($q->answerChoices->sortBy('order') as $choice)
                            <div class="answer-option">
                                <input type="radio" id="q{{ $q->question_number }}{{ $choice->label }}" name="q{{ $q->question_number }}" value="{{ $choice->label }}">
                                <label for="q{{ $q->question_number }}{{ $choice->label }}">{!! $choice->content !!}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</x-layouts.test>

