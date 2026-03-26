<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital SAT</title>
    @vite(['resources/css/app.css', 'resources/css/home.css', 'resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
    <header>
        <div class="container">
            <div class="d-flex justify-content-between">
                <div class="bluebook-logo">
                    <span>Bluebook™</span>
                </div>
                <div class="user-dropdown">
                    <div class="user" id="userDropdown">
                        {{ $user->name ?? 'Guest' }}
                        <div class="avatar">
                            <img src="{{ asset('images/default_avt.jpg') }}" alt="User">
                        </div>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
            <h1>Welcome, {{ explode(' ', $user->name)[0] ?? 'User' }}! Good luck on test day!</h1>
        </div>
    </header>
    <main>
        <div class="container">
            <section class="your-tests">
                <h2>Your Tests
                    <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
                        <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked>
                        <label class="btn btn-outline-primary" for="btnradio1">✓ Active</label>

                        <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off">
                        <label class="btn btn-outline-primary" for="btnradio2">Past</label>
                    </div>
                    <a href="#">
                        Don't see your test here?
                    </a>
                </h2>
                <div class="test-box">
                    <h4>You Have No Upcoming Tests</h4>
                    <p>Tests appear here a few weeks before test day. <strong>If you got a paper ticket from your school, <a href="/logout">sign out</a> and sign in with it.</strong></p>
                </div>
            </section>

            <section class="practice">
                <h2>Practice and Prepare
                    <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
                        <input type="radio" class="btn-check" name="btnradio2" id="btnradio3" autocomplete="off" checked>
                        <label class="btn btn-outline-primary" for="btnradio3">✓ Active</label>

                        <input type="radio" class="btn-check" name="btnradio2" id="btnradio4" autocomplete="off">
                        <label class="btn btn-outline-primary" for="btnradio4">Past</label>
                    </div>
                    <a href="#">
                        <p>Learn more about Bluebook practice</p>
                    </a>
                </h2>
                <div class="practice-options d-flex gap-4">
                    <a href="">
                        <div class="option">
                            <img src="{{ asset('images/test_preview.png') }}" alt="Test Preview">
                            <h4>Test Preview</h4>
                        </div>
                    </a>
                    <a href="test_preview.php">
                        <div class="option">
                            <img src="{{ asset('images/test.png') }}" alt="Full-Length Practice">
                            <h4>Full-Length Practice</h4>
                        </div>
                    </a>
                </div>
            </section>

            <section class="bigfuture">
                <h2>Explore BigFuture</h2>
                <div class="bigfuture-content">
                    <img src="{{ asset('images/big_future.jpg') }}" alt="Big Future">
                    <div class="info">
                        <h4>Plan for Life After High School</h4>
                        <p>Whether you're interested in a four-year university, community college, or career training, BigFuture has what you need to start planning your future, your way.</p>
                        <div href="#" class="btn">Go to BigFuture</div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        // Dropdown toggle
        const userDropdown = document.getElementById('userDropdown');
        const dropdownMenu = document.getElementById('dropdownMenu');
        const logoutForm = dropdownMenu.querySelector('form');

        userDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userDropdown.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });

        // Handle logout
        logoutForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const response = await fetch(logoutForm.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': logoutForm.querySelector('input[name="_token"]')?.value,
                        'Accept': 'application/json'
                    }
                });
                if (response.ok) {
                    localStorage.removeItem('api_token');
                    window.location.href = '/';
                }
            } catch (error) {
                console.error('Logout error:', error);
            }
        });
    </script>
</body>
</html>