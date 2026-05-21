@props(['title' => 'Digital SAT', 'nextUrl' => '#', 'backUrl' => '/home'])

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
            overflow: auto;
            scroll-behavior: smooth;
            padding-bottom: 20px;
        }

        main h1 {
            font-size: 2.25rem;
            text-align: center;
            font-weight: 400;
            margin: 2rem 0;
        }

        main .container {
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            padding: 50px 40px;
            border-radius: 12px;
            max-width: 32.5rem;
        }

        footer {
            background-color: #fff;
        }

        .buttons {
            display: flex;
            flex-direction: row-reverse;
            padding: 20px 40px;
            border-top: 1px solid #cccccc;
            gap: 20px;
        }

        .btn {
            background-color: #2c53da;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background-color: #1a3bb8;
            color: #fff;
        }
    </style>
</head>

<body>
    <!-- Portal Loading Screen -->
    <div id="loadingScreen" class="loading-screen hidden d-flex flex-column align-items-center justify-content-center">
        <div class="loading-container text-center">
            <div class="loading-spinner-wrapper mb-4">
                <div class="loading-spinner"></div>
                <div class="loading-spinner-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
            </div>
            <h4 class="loading-title fw-bold mb-2">Digital SAT Test Engine</h4>
            <p id="loadingStatusText" class="loading-status">Entering secure testing environment...</p>
        </div>
    </div>

    <header></header>
    <main>
        <h1>{{ $title }}</h1>
        <div class="container">
            {{ $slot }}
        </div>
    </main>
    <footer>
        <div class="buttons">
            <a href="{{ $nextUrl }}" class="btn" role="button">Next</a>
            <a href="{{ $backUrl }}" class="btn" role="button">Back</a>
        </div>
    </footer>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const nextBtn = document.querySelector('footer .buttons a.btn[role="button"]');
            const loadingScreen = document.getElementById('loadingScreen');
            if (nextBtn && loadingScreen) {
                nextBtn.addEventListener('click', function(e) {
                    const href = nextBtn.getAttribute('href');
                    if (href && href !== '#' && !href.startsWith('javascript:')) {
                        loadingScreen.classList.remove('hidden');
                    }
                });
            }
        });
    </script>
    @stack('scripts')
</body>

</html>
