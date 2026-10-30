@props([
    'cancelRoute' => null,
    'cancelText' => 'Return to progress',
    'statusText' => 'Preparing your test…',
    'recoveryStatusText' => 'Still preparing. You can return and try again.',
])

<div id="loadingScreen" class="loading-screen hidden" aria-hidden="true">
    <div class="loading-container">
        <div class="loading-spinner" aria-hidden="true"></div>
        <p id="loadingStatusText" class="loading-status" role="status">{{ $statusText }}</p>
        @if ($cancelRoute)
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
                    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event
                        .shiftKey || event.altKey) {
                        return false;
                    }
                    if (link.target && link.target !== '_self') {
                        return false;
                    }

                    const url = new URL(link.href, window.location.origin);
                    return url.origin === window.location.origin &&
                        (url.pathname.startsWith('/take-test') || url.pathname.startsWith('/engine/take-test') || url
                            .pathname.startsWith('/engine/session'));
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

                    @if ($cancelRoute)
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
