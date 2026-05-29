<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'Test' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Noto+Serif:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/test/test-main.css', 'resources/js/app.js', 'resources/js/test.js'])
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
    <script src="https://www.desmos.com/api/v1.12/calculator.js?apiKey=db07a8e640bc4faca92a5c89e0745235"></script>
    <script src="https://www.desmos.com/api/v1.12/scientific.js?apiKey=db07a8e640bc4faca92a5c89e0745235"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js" onload="if(window.smartRenderMath) { window.smartRenderMath(document.body); } else { renderMathInElement(document.body, {
                                                                    delimiters: [
                                                                        { left: '$$', right: '$$', display: false },
                                                                        { left: '\\\\[', right: '\\\\]', display: true },
                                                                    ],
                                                                    throwOnError : false,
                                                                    trust: true
                                                                }); }"></script>
    @stack('styles')
</head>

<body>
    <!-- Secure Test Loading Screen -->
    <div id="loadingScreen" class="loading-screen flex flex-col items-center justify-center">
        <div class="loading-container text-center">
            <div class="loading-spinner-wrapper mb-4">
                <div class="loading-spinner"></div>
                <div class="loading-spinner-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                </div>
            </div>
            <h4 class="loading-title fw-bold mb-2">Digital SAT Test Engine</h4>
            <p id="loadingStatusText" class="loading-status">Initializing secure test environment...</p>
        </div>
    </div>

    <header>
        <div class="flex flex-col justify-start">
            <h1 class="text-xl md:text-2xl font-medium">Section {{ $sectionNumber ?? '1' }}, Module
                {{ $moduleNumber ?? '1' }}:
                {{ $sectionName ?? ($sectionTitle ?? 'Reading and Writing') }}
            </h1>
            <div class="relative" x-data="{ open: true }" @click.outside="open = false">
                <button class="dropdown-toggle flex items-center gap-2 font-bold relative z-50" type="button"
                    @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                    :class="open ? 'bg-white rounded-md' : ''">
                    Directions <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="feather feather-chevron-down">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="fixed inset-0 bg-black/50 z-40" x-show="open" x-cloak style="display: none;"></div>
                <div class="absolute mt-3 bg-white border border-gray-200 rounded-md shadow-lg z-50 flex-col w-[90vw] md:w-208 max-h-[80vh] p-0"
                    x-show="open" x-cloak :class="open ? 'flex' : 'hidden'" style="display: none;">

                    <!-- Dropdown Tail -->
                    <div
                        class="absolute -top-[8px] left-12 w-4 h-4 bg-white border-l border-t border-gray-200 rotate-45">
                    </div>

                    <div class="pt-8 pb-5 pl-7 pr-5 overflow-y-auto flex-1 min-h-0 bg-white rounded-t-md relative z-10">
                        @if (isset($sectionDirections))
                            {!! $sectionDirections !!}
                        @else
                            <p>No directions available.</p>
                        @endif
                    </div>
                    <div class="py-4 px-7 flex justify-end bg-white rounded-b-md relative z-10">
                        <button type="button"
                            class="bg-[#fedb00] text-[#1e1e1e] py-2 px-[22px] rounded-full font-semibold text-sm transition-shadow duration-300 shadow-[inset_0_0_0_1px_#1e1e1e] hover:shadow-[inset_0_0_0_2px_#1e1e1e]"
                            @click="open = false">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex justify-center">
            <div class="text-center justify-items-center">
                <div class="timer" id="timerDisplay">00:00</div>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="feather feather-clock hidden" id="clockIcon">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <div class="hide-button mt-2" id="timerToggle" onclick="toggleTimer()">Hide</div>
            </div>
        </div>
        <div class="flex justify-end">
            @if (($sectionType ?? '') !== 'math')
                <div class="icon-container" id="highlightNotesBtn">
                    <div class="flex icon">
                        <img src="{{ asset('/images/highlight.png') }}" alt="Highlights">
                        <img src="{{ asset('/images/notes.png') }}" alt="Notes">
                    </div>
                    <p class="m-0" data-text="Highlights & Notes">Highlights & Notes</p>
                </div>
            @endif
            @if (($sectionType ?? '') === 'math')
                <div class="icon-container" id="calculatorBtn">
                    <div class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                            class="bi bi-calculator" viewBox="0 0 16 16">
                            <path
                                d="M12 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM4 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z" />
                            <path
                                d="M4 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5zm0 4a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5z" />
                        </svg>
                    </div>
                    <p class="m-0" data-text="Calculator">Calculator</p>
                </div>
            @endif
            <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                <div class="icon-container" id="moreBtn" :class="open ? 'highlight-mode-active' : ''"
                    @click="open = !open">
                    <div class="icon">
                        <img src="{{ asset('/images/more.png') }}" alt="More">
                    </div>
                    <p class="m-0" data-text="More">More</p>
                </div>
                <div id="moreMenu"
                    class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg z-50"
                    x-show="open" x-cloak style="display: none;">
                    <div>
                        <button class="block w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-100"
                            @click="open = false; window.showCustomAlert('Taking a break... (Functionality to be implemented)', 'info', 'Take a Break')">
                            Take a break
                        </button>
                        <button class="block w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50"
                            @click="open = false; window.showCustomConfirm('Are you sure you want to exit the exam? Your progress will be saved.', 'warning', 'Exit Exam').then(confirmed => { if(confirmed) window.location.href = '/home'; })">
                            Exit the exam
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <main>
        {{ $slot }}
    </main>
    <footer>
        <div class="flex justify-start">
            <div>
                <h1 class="text-xl md:text-2xl font-medium">{{ $username ?? 'No Name Available' }}</h1>
            </div>
        </div>
        <div class="flex justify-center relative" x-data="{ popoverOpen: false }" @click.outside="popoverOpen = false">
            <button type="button" class="popover-btn flex items-center gap-1 z-50 relative"
                :class="popoverOpen ? 'popover-open' : ''" @click="popoverOpen = !popoverOpen">
                Question <span>{{ $currentQuestion ?? '...' }}</span> of <span
                    id="total">{{ $totalQuestions ?? '...' }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="feather feather-chevron-up">
                    <polyline points="18 15 12 9 6 15"></polyline>
                </svg>
            </button>

            <div id="popover-content"
                class="absolute bottom-full mb-4 bg-white rounded-xl shadow-[0_0_15px_rgba(0,0,0,0.15)] z-50 w-[90vw] md:w-140"
                x-show="popoverOpen" x-cloak style="display: none;" :class="popoverOpen ? 'block' : 'hidden'">

                <div class="absolute -bottom-2 left-1/2 h-4 w-4 -translate-x-1/2 rotate-45 bg-white shadow-md"></div>

                <div class="p-6 flex flex-col">
                    <h3 class="m-0 text-center text-lg md:text-xl font-bold pb-4 border-b border-gray">
                        {{ $sectionTitle ?? 'No Section Title Available' }} Questions</h3>
                    <div
                        class="flex justify-center items-center gap-4 md:gap-6 text-sm font-medium py-4 border-b border-gray">
                        <div class="flex items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                stroke-linejoin="round" class="feather feather-map-pin">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            Current
                        </div>
                        <div class="flex items-center justify-center gap-2 px-4 md:px-6 border-x border-gray">
                            <svg fill="#000000" version="1.1" xmlns="http://www.w3.org/2000/svg" width="16px"
                                height="16px" viewBox="0 0 389 389">
                                <g>
                                    <g>
                                        <g>
                                            <path
                                                d="M379,326.035h-18.852c-5.522,0-10,4.477-10,10v14.111h-14.113c-5.522,0-10,4.477-10,10V379c0,5.523,4.478,10,10,10H379c5.522,0,10-4.477,10-10v-42.965C389,330.512,384.522,326.035,379,326.035z" />
                                            <path
                                                d="M166.927,350.146h-58.813c-5.522,0-10,4.477-10,10V379c0,5.523,4.478,10,10,10h58.813c5.522,0,10-4.477,10-10v-18.854C176.927,354.623,172.449,350.146,166.927,350.146z" />
                                            <path
                                                d="M280.887,350.146h-58.812c-5.523,0-10,4.477-10,10V379c0,5.523,4.477,10,10,10h58.812c5.522,0,10-4.477,10-10v-18.854C290.887,354.623,286.409,350.146,280.887,350.146z" />
                                            <path
                                                d="M52.965,350.146H38.852v-14.111c0-5.523-4.478-10-10-10H10c-5.522,0-10,4.477-10,10V379c0,5.523,4.478,10,10,10h42.965c5.521,0,10-4.477,10-10v-18.854C62.965,354.623,58.486,350.146,52.965,350.146z" />
                                            <path
                                                d="M10,290.886h18.852c5.522,0,10-4.477,10-10v-58.812c0-5.523-4.478-10-10-10H10c-5.522,0-10,4.477-10,10v58.812C0,286.409,4.478,290.886,10,290.886z" />
                                            <path
                                                d="M10,176.926h18.852c5.522,0,10-4.477,10-10v-58.812c0-5.523-4.478-10-10-10H10c-5.522,0-10,4.477-10,10v58.812C0,172.449,4.478,176.926,10,176.926z" />
                                            <path
                                                d="M52.965,0H10C4.478,0,0,4.477,0,10v42.967c0,5.523,4.478,10,10,10h18.852c5.522,0,10-4.477,10-10V38.854h14.113c5.521,0,10-4.477,10-10V10C62.965,4.478,58.486,0,52.965,0z" />
                                            <path
                                                d="M280.887,0h-58.812c-5.522,0-10,4.477-10,10v18.854c0,5.523,4.478,10,10,10h58.812c5.522,0,10-4.477,10-10V10C290.887,4.478,286.409,0,280.887,0z" />
                                            <path
                                                d="M108.113,38.854h58.813c5.522,0,10-4.477,10-10V10c0-5.523-4.478-10-10-10h-58.813c-5.522,0-10,4.477-10,10v18.854C98.113,34.377,102.591,38.854,108.113,38.854z" />
                                            <path
                                                d="M379,0h-42.965c-5.522,0-10,4.477-10,10v18.854c0,5.523,4.478,10,10,10h14.113v14.113c0,5.523,4.478,10,10,10H379c5.522,0,10-4.477,10-10V10C389,4.478,384.522,0,379,0z" />
                                            <path
                                                d="M379,212.074h-18.852c-5.522,0-10,4.477-10,10v58.812c0,5.522,4.478,10,10,10H379c5.522,0,10-4.478,10-10v-58.812C389,216.551,384.522,212.074,379,212.074z" />
                                            <path
                                                d="M379,98.114h-18.852c-5.522,0-10,4.477-10,10v58.812c0,5.523,4.478,10,10,10H379c5.522,0,10-4.477,10-10v-58.812C389,102.591,384.522,98.114,379,98.114z" />
                                        </g>
                                    </g>
                                </g>
                            </svg>
                            Unanswered
                        </div>
                        <div class="flex items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                fill="#ab2334" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="feather feather-bookmark">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                            </svg>
                            For Review
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-center gap-3 pt-5" @click="popoverOpen = false">
                        <!-- Question buttons will be dynamically generated by JavaScript -->
                    </div>
                    <div class="text-center go-review-btn pt-4" @click="popoverOpen = false">
                        <button>Go to Review Page</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex flex-row-reverse justify-start gap-3">
            <div class="navigate-btn" onclick="nextQuestion()" id="nextButton">Next</div>
            <div class="navigate-btn" onclick="prevQuestion()" id="backButton">Back</div>
        </div>
    </footer>

    <!-- Desmos Calculator Modal -->
    <div id="calculatorModal" class="calculator-modal hidden">
        <div class="calculator-modal-header">
            <div class="calculator-tabs">
                <button class="calc-tab active" data-tab="graphing">Graphing</button>
                <button class="calc-tab" data-tab="scientific">Scientific</button>
            </div>
            <button class="closeCalculatorBtn">&times;</button>
        </div>
        <div id="graphingCalc" class="calculator-content"></div>
        <div id="scientificCalc" class="calculator-content hidden"></div>
        <div class="calculator-resize-handle"></div>
    </div>

    <!-- Beautiful Premium Custom Alert Modal -->
    <div id="customAlertModal" class="custom-alert-modal hidden">
        <div class="custom-alert-backdrop"></div>
        <div class="custom-alert-box">
            <div class="custom-alert-icon" id="customAlertIcon">
                <!-- SVG Icon resolved dynamically in JS -->
            </div>
            <div class="custom-alert-content">
                <h5 class="custom-alert-title" id="customAlertTitle">Notification</h5>
                <p id="customAlertMessage" class="custom-alert-message">Something went wrong.</p>
            </div>
            <div class="custom-alert-actions">
                <button id="customAlertCancelBtn" class="custom-alert-btn btn-secondary hidden">Cancel</button>
                <button id="customAlertConfirmBtn" class="custom-alert-btn btn-primary">OK</button>
            </div>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>

</html>