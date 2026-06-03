@props([
    'user',
    'title' => null,
    'headerClass' => '',
    'logoClass' => '',
    'userClass' => '',
    'bodyClass' => 'antialiased bg-gray-50',
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Digital SAT' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
    {{ $head ?? '' }}
</head>
<body class="{{ $bodyClass }}">
    <div id="loadingScreen" class="loading-screen hidden" aria-hidden="true">
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
            <p id="loadingStatusText" class="loading-status">Preparing your test...</p>
        </div>
    </div>

    <x-app.user-header 
        :user="$user" 
        :header-class="$headerClass" 
        :logo-class="$logoClass" 
        :user-class="$userClass" 
    />
    
    <main>
        {{ $slot }}
    </main>

    @livewireScripts
    {{ $scripts ?? '' }}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loadingScreen = document.getElementById('loadingScreen');
            const loadingStatusText = document.getElementById('loadingStatusText');

            if (!loadingScreen) {
                return;
            }

            function shouldHandleClick(event, link) {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return false;
                }
                if (link.target && link.target !== '_self') {
                    return false;
                }

                const url = new URL(link.href, window.location.href);
                return url.origin === window.location.origin && url.pathname.startsWith('/take-test');
            }

            function navigateAfterLoaderPaint(href) {
                if (loadingStatusText) {
                    loadingStatusText.textContent = 'Preparing your test...';
                }
                loadingScreen.classList.remove('hidden');
                loadingScreen.setAttribute('aria-hidden', 'false');
                document.body.style.cursor = 'wait';

                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        window.location.href = href;
                    });
                });
            }

            document.addEventListener('click', function(event) {
                const link = event.target.closest('a[href]');
                if (!link || !shouldHandleClick(event, link)) {
                    return;
                }

                event.preventDefault();
                link.setAttribute('aria-disabled', 'true');
                navigateAfterLoaderPaint(link.href);
            });

            window.addEventListener('pageshow', function() {
                loadingScreen.classList.add('hidden');
                loadingScreen.setAttribute('aria-hidden', 'true');
                document.body.style.cursor = '';
                document.querySelectorAll('a[aria-disabled="true"]').forEach(link => {
                    link.removeAttribute('aria-disabled');
                });
            });
        });
    </script>
</body>
</html>
