<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Digital SAT' }}</title>
    @vite(['resources/css/app.css', 'resources/css/auth.css','resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
    <header>
        <div class="bluebook-logo">
            <span>Bluebook™</span>
        </div>
        <button class="test-device-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-monitor"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            <span>Test your device</span>
        </button>
    </header>
    <main>
        {{ $slot }}
    </main>
    <footer>
        
    </footer>
    <script>
        (function () {
            const eyeIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            const eyeOffIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

            function bindPasswordToggle(inputEl, toggleEl) {
                if (!inputEl || !toggleEl || toggleEl.dataset.bound === '1') {
                    return;
                }

                toggleEl.dataset.bound = '1';
                toggleEl.innerHTML = eyeIcon;
                toggleEl.setAttribute('aria-label', 'Show password');
                toggleEl.setAttribute('aria-pressed', 'false');

                function syncToggleVisibility() {
                    const hasValue = inputEl.value.trim() !== '';
                    toggleEl.style.display = hasValue ? 'flex' : 'none';

                    if (!hasValue) {
                        inputEl.type = 'password';
                        toggleEl.innerHTML = eyeIcon;
                        toggleEl.setAttribute('aria-label', 'Show password');
                        toggleEl.setAttribute('aria-pressed', 'false');
                    }
                }

                toggleEl.addEventListener('click', function () {
                    const isHidden = inputEl.type === 'password';
                    inputEl.type = isHidden ? 'text' : 'password';
                    toggleEl.innerHTML = isHidden ? eyeOffIcon : eyeIcon;
                    toggleEl.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                    toggleEl.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                    inputEl.focus();
                });

                inputEl.addEventListener('input', syncToggleVisibility);
                syncToggleVisibility();
            }

            window.initPasswordToggles = function () {
                document.querySelectorAll('.password-toggle[data-password-target]').forEach(function (toggleEl) {
                    const targetId = toggleEl.getAttribute('data-password-target');
                    const inputEl = document.getElementById(targetId);
                    bindPasswordToggle(inputEl, toggleEl);
                });
            };
        })();
    </script>
    @stack('scripts')
</body>
</html>