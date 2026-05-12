<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Practice - Digital SAT</title>
    @vite(['resources/css/app.css', 'resources/css/home.css', 'resources/css/practice.css', 'resources/sass/app.scss', 'resources/js/app.js'])
</head>

<body>
    <header class="bg-[#0077c8!important]">
        <div class="container">
            <div class="d-flex justify-content-between">
                <div class="bluebook-logo text-[#fff!important]">
                    <span>Bluebook™</span>
                </div>
                <div class="user-dropdown">
                    <div class="user text-[#fff!important]" id="userDropdown">
                        {{ $user->username ?? 'Guest' }}
                        <div class="avatar">
                            <img src="{{ asset('images/default_avt.jpg') }}" alt="User">
                        </div>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <main>
        <div class="welcome bg-[#0077c8!important]">
            <div class="container">
                <h1 class="text-[#ffffff!important]">My Practice</h1>
                <p class="text-[#ffffff]">Review your practice test scores, dig deeper into your performance, and learn
                    your
                    strengths before
                    test day.</p>
            </div>
        </div>
        <div class="container !pb-20">
            <a href="{{ route('home') }}" class="flex text-[#324dc7] text-decoration-none me-3 gap-1 !mb-10">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <span class="font-bold">Back to Home</span>
            </a>

            <h1 class="!text-[#000] !font-bold !mb-4">SAT Practice Tests</h1>
            <div class="grid gap-6" style="grid-template-columns: repeat(auto-fill, 260px);">
                @foreach ($completedTests as $test)
                    <div class="practice-card">
                        <!-- Header -->
                        <div class="practice-card-header">
                            <span class="text-white text-2xl font-semibold">SAT</span>
                        </div>

                        <!-- Sub-header -->
                        <div class="practice-card-subheader">
                            <span
                                class="text-white text-[14px] font-semibold tracking-wider uppercase">{{ $test->test->title }}</span>
                            <span
                                class="text-[#ffffff] text-sm">{{ $test->completed_at ? $test->completed_at->format('M d, Y') : 'N/A' }}</span>
                        </div>

                        <!-- Score banner -->
                        <div class="practice-card-score-banner">
                            <p class="m-0 mb-1 text-xs font-medium text-[#444] uppercase tracking-[0.5px]">Your Total
                                Score</p>
                            <p class="practice-card-total-score">{{ $test->total_score ?? '---' }}</p>
                            <p class="practice-card-score-range">400–1600</p>
                        </div>

                        <!-- Section scores -->
                        <div class="practice-card-sections">
                            <div class="practice-card-section-row mb-14">
                                <div>
                                    <p class="practice-card-section-name">Reading and Writing</p>
                                    <p class="practice-card-section-range">200–800</p>
                                </div>
                                <span
                                    class="practice-card-section-score">{{ $test->score_reading_writing ?? '---' }}</span>
                            </div>
                            <hr class="practice-card-hr">
                            <div class="practice-card-section-row">
                                <div>
                                    <p class="practice-card-section-name">Math</p>
                                    <p class="practice-card-section-range">200–800</p>
                                </div> <span class="practice-card-section-score">{{ $test->score_math ?? '---' }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="practice-card-actions">
                            <button class="practice-card-btn-primary">Score Details</button>
                            <div class="practice-card-footer-link">
                                <span>☰ Practice Specific Questions</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </main>

    <script>
        // Dropdown toggle
        const userDropdown = document.getElementById('userDropdown');
        const dropdownMenu = document.getElementById('dropdownMenu');

        if (userDropdown && dropdownMenu) {
            userDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });

            document.addEventListener('click', (e) => {
                if (!userDropdown.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });
        }
    </script>
</body>

</html>
