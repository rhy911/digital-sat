@props([
    'cancelRoute' => null,
    'cancelText' => 'Return to progress',
    'title' => 'Digital SAT Test Engine',
    'statusText' => 'Preparing your test...',
    'recoveryStatusText' => 'Still preparing. You can return and try again.',
])

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
        <h4 class="loading-title fw-bold mb-2">{{ $title }}</h4>
        <p id="loadingStatusText" class="loading-status">{{ $statusText }}</p>
        @if($cancelRoute)
            <a id="loadingCancelLink" href="{{ $cancelRoute }}" class="loading-cancel hidden">{{ $cancelText }}</a>
        @endif
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const loadingScreen = document.getElementById('loadingScreen');
                const loadingStatusText = document.getElementById('loadingStatusText');
                const loadingCancelLink = document.getElementById('loadingCancelLink');
                let loadingRecoveryTimer = null;

                if (!loadingScreen) return;

                function shouldHandleClick(event, link) {
                    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                        return false;
                    }
                    if (link.target && link.target !== '_self') {
                        return false;
                    }

                    const url = new URL(link.href, window.location.origin);
                    return url.origin === window.location.origin && 
                        (url.pathname.startsWith('/take-test') || url.pathname.startsWith('/engine/take-test') || url.pathname.startsWith('/engine/session'));
                }

                function navigateAfterLoaderPaint(href) {
                    if (loadingStatusText) {
                        loadingStatusText.textContent = "{{ $statusText }}";
                    }
                    if (loadingCancelLink) {
                        loadingCancelLink.classList.add('hidden');
                    }
                    loadingScreen.classList.remove('hidden');
                    loadingScreen.setAttribute('aria-hidden', 'false');
                    document.body.style.cursor = 'wait';

                    @if($cancelRoute)
                        window.clearTimeout(loadingRecoveryTimer);
                        loadingRecoveryTimer = window.setTimeout(() => {
                            if (loadingStatusText) {
                                loadingStatusText.textContent = "{{ $recoveryStatusText }}";
                            }
                            if (loadingCancelLink) {
                                loadingCancelLink.classList.remove('hidden');
                            }
                        }, 8000);
                    @endif

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
                    if (loadingRecoveryTimer) {
                        window.clearTimeout(loadingRecoveryTimer);
                    }
                    loadingScreen.classList.add('hidden');
                    loadingScreen.setAttribute('aria-hidden', 'true');
                    if (loadingCancelLink) {
                        loadingCancelLink.classList.add('hidden');
                    }
                    document.body.style.cursor = '';
                    document.querySelectorAll('a[aria-disabled="true"]').forEach(link => {
                        link.removeAttribute('aria-disabled');
                    });
                });

                // Global trigger function for JS-initiated loading
                window.triggerLoadingScreen = function(statusMsg) {
                    if (loadingStatusText && statusMsg) {
                        loadingStatusText.textContent = statusMsg;
                    }
                    if (loadingCancelLink) {
                        loadingCancelLink.classList.add('hidden');
                    }
                    loadingScreen.classList.remove('hidden');
                    loadingScreen.setAttribute('aria-hidden', 'false');
                };

                window.hideLoadingScreen = function() {
                    if (loadingRecoveryTimer) {
                        window.clearTimeout(loadingRecoveryTimer);
                    }
                    loadingScreen.classList.add('hidden');
                    loadingScreen.setAttribute('aria-hidden', 'true');
                    if (loadingCancelLink) {
                        loadingCancelLink.classList.add('hidden');
                    }
                };
            });
        </script>
    @endpush
@endonce
