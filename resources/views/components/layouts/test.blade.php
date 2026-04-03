<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? 'Test' }}</title>
    @vite(['resources/css/app.css', 'resources/css/test.css','resources/sass/app.scss', 'resources/js/app.js', 'resources/js/test.js'])
    @stack('styles')
</head>
<body>
    <header>
        <div class="d-flex flex-column justify-content-start"> 
            <h5>{{ $sectionTitle ?? 'No Section Title Available' }}</h5>
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Directions
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton" id="dropdownMenu">
                    @if (isset($sectionDirections))
                        {!! $sectionDirections !!}
                    @else
                        <p>No directions available.</p>
                    @endif
                    <button class="btn btn-secondary float-end">Close</button>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-center">
            <div class="text-center justify-items-center">
                <div class="timer" id="timerDisplay">00:00</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-clock d-none" id="clockIcon">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <div class="hide-button" id="timerToggle" onclick="toggleTimer()">Hide</div>
            </div>
        </div>
        <div class="d-flex justify-content-end">
            <div class="icon-container" id="highlightNotesBtn">
                <div class="d-flex icon">
                    <img src="{{ asset('/images/highlight.png') }}" alt="Highlights">
                    <img src="{{ asset('/images/notes.png') }}" alt="Notes">
                </div>
                <p class="m-0">Highlights & Notes</p>
            </div>
            <div class="icon-container">
                <div class="icon">
                    <img src="{{ asset('/images/more.png') }}" alt="More">
                </div>
                <p class="m-0">More</p>
            </div>
        </div>
    </header>
    <main>
        {{ $slot }}
    </main>
    <footer>
        <div class="d-flex justify-content-start">
            <div>
                <h5 class="m-0">{{ $username ?? 'No Name Available' }}</h5>
            </div>
        </div>
        <div class="d-flex justify-content-center">
            <button type="button" class="btn btn-secondary d-flex align-items-center gap-1" data-bs-toggle="popover" data-bs-placement="top" data-bs-content-id="popover-content">
                Question <span>{{ $currentQuestion ?? '...' }}</span> of <span id="total">{{ $totalQuestions ?? '...' }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-chevron-up">
                    <polyline points="18 15 12 9 6 15"></polyline>
                </svg>
            </button>

            <div id="popover-content" class="d-none">
                <div class="d-flex flex-column gap-4">
                    <h5>{{ $sectionTitle ?? 'No Section Title Available' }}</h5>
                    <div class="row text-center question-nav-row">
                        <div class="col d-flex align-items-center justify-content-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-map-pin">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            Current
                        </div>
                        <div class="col d-flex align-items-center justify-content-center gap-2">
                            <svg fill="#000000" version="1.1" xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 389 389">
                                <g><g><g>
                                    <path d="M379,326.035h-18.852c-5.522,0-10,4.477-10,10v14.111h-14.113c-5.522,0-10,4.477-10,10V379c0,5.523,4.478,10,10,10H379c5.522,0,10-4.477,10-10v-42.965C389,330.512,384.522,326.035,379,326.035z" />
                                    <path d="M166.927,350.146h-58.813c-5.522,0-10,4.477-10,10V379c0,5.523,4.478,10,10,10h58.813c5.522,0,10-4.477,10-10v-18.854C176.927,354.623,172.449,350.146,166.927,350.146z" />
                                    <path d="M280.887,350.146h-58.812c-5.523,0-10,4.477-10,10V379c0,5.523,4.477,10,10,10h58.812c5.522,0,10-4.477,10-10v-18.854C290.887,354.623,286.409,350.146,280.887,350.146z" />
                                    <path d="M52.965,350.146H38.852v-14.111c0-5.523-4.478-10-10-10H10c-5.522,0-10,4.477-10,10V379c0,5.523,4.478,10,10,10h42.965c5.521,0,10-4.477,10-10v-18.854C62.965,354.623,58.486,350.146,52.965,350.146z" />
                                    <path d="M10,290.886h18.852c5.522,0,10-4.477,10-10v-58.812c0-5.523-4.478-10-10-10H10c-5.522,0-10,4.477-10,10v58.812C0,286.409,4.478,290.886,10,290.886z" />
                                    <path d="M10,176.926h18.852c5.522,0,10-4.477,10-10v-58.812c0-5.523-4.478-10-10-10H10c-5.522,0-10,4.477-10,10v58.812C0,172.449,4.478,176.926,10,176.926z" />
                                    <path d="M52.965,0H10C4.478,0,0,4.477,0,10v42.967c0,5.523,4.478,10,10,10h18.852c5.522,0,10-4.477,10-10V38.854h14.113c5.521,0,10-4.477,10-10V10C62.965,4.478,58.486,0,52.965,0z" />
                                    <path d="M280.887,0h-58.812c-5.522,0-10,4.477-10,10v18.854c0,5.523,4.478,10,10,10h58.812c5.522,0,10-4.477,10-10V10C290.887,4.478,286.409,0,280.887,0z" />
                                    <path d="M108.113,38.854h58.813c5.522,0,10-4.477,10-10V10c0-5.523-4.478-10-10-10h-58.813c-5.522,0-10,4.477-10,10v18.854C98.113,34.377,102.591,38.854,108.113,38.854z" />
                                    <path d="M379,0h-42.965c-5.522,0-10,4.477-10,10v18.854c0,5.523,4.478,10,10,10h14.113v14.113c0,5.523,4.478,10,10,10H379c5.522,0,10-4.477,10-10V10C389,4.478,384.522,0,379,0z" />
                                    <path d="M379,212.074h-18.852c-5.522,0-10,4.477-10,10v58.812c0,5.522,4.478,10,10,10H379c5.522,0,10-4.478,10-10v-58.812C389,216.551,384.522,212.074,379,212.074z" />
                                    <path d="M379,98.114h-18.852c-5.522,0-10,4.477-10,10v58.812c0,5.523,4.478,10,10,10H379c5.522,0,10-4.477,10-10v-58.812C389,102.591,384.522,98.114,379,98.114z" />
                                </g></g></g>
                            </svg>
                            Unanswered
                        </div>
                        <div class="col d-flex align-items-center justify-content-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#ab2334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-bookmark">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                            </svg>
                            For Review
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-3">
                        <!-- Question buttons will be dynamically generated by JavaScript -->
                    </div>
                    <div class="text-center go-review-btn">
                        <button class="btn btn-outline-primary">Go to Review Page</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-row-reverse justify-content-start gap-3">
            <div class="navigate-btn" onclick="nextQuestion()" id="nextButton">Next</div>
            <div class="navigate-btn" onclick="prevQuestion()" id="backButton">Back</div>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>