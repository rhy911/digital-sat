@props([
    'user',
    'title' => 'DigiSAT Progress',
    'bodyClass' => 'antialiased ds-home-shell',
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            <a id="loadingCancelLink" href="{{ route('home.progress') }}" class="loading-cancel hidden">Return to progress</a>
        </div>
    </div>

    <x-app.progress-header :user="$user" />

    <main class="ds-home-main">
        {{ $slot }}
    </main>

    @livewireScripts
    @stack('scripts')
    {{ $scripts ?? '' }}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.initDropdownToggle === 'function') {
                const { menuEl } = window.initDropdownToggle({
                    triggerId: 'progressUserDropdown',
                    menuId: 'progressDropdownMenu',
                    openClass: 'show',
                });
                const accountButton = document.getElementById('progressUserDropdown');
                const syncAccountState = () => {
                    accountButton?.setAttribute('aria-expanded', menuEl?.classList.contains('show') ? 'true' : 'false');
                };
                accountButton?.addEventListener('click', () => window.setTimeout(syncAccountState, 0));
                document.addEventListener('click', () => window.setTimeout(syncAccountState, 0));
                const logoutForm = menuEl?.querySelector?.('form');
                if (typeof window.initAjaxLogout === 'function') {
                    window.initAjaxLogout({ formEl: logoutForm, redirectTo: '/', tokenStorageKey: 'api_token' });
                }
            }

            const loadingScreen = document.getElementById('loadingScreen');
            const loadingStatusText = document.getElementById('loadingStatusText');
            const loadingCancelLink = document.getElementById('loadingCancelLink');
            let loadingRecoveryTimer = null;

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
                loadingCancelLink?.classList.add('hidden');
                loadingScreen.classList.remove('hidden');
                loadingScreen.setAttribute('aria-hidden', 'false');
                document.body.style.cursor = 'wait';
                window.clearTimeout(loadingRecoveryTimer);
                loadingRecoveryTimer = window.setTimeout(() => {
                    if (loadingStatusText) {
                        loadingStatusText.textContent = 'Still preparing. You can return to progress and try again.';
                    }
                    loadingCancelLink?.classList.remove('hidden');
                }, 8000);

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
                window.clearTimeout(loadingRecoveryTimer);
                loadingScreen.classList.add('hidden');
                loadingScreen.setAttribute('aria-hidden', 'true');
                loadingCancelLink?.classList.add('hidden');
                document.body.style.cursor = '';
                document.querySelectorAll('a[aria-disabled="true"]').forEach(link => {
                    link.removeAttribute('aria-disabled');
                });
            });
        });
    </script>
</body>
</html>
