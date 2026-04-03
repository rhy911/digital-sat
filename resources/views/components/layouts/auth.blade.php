@props(['title' => 'Digital SAT'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/css/auth.css','resources/sass/app.scss', 'resources/js/app.js', 'resources/js/auth.js'])
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
        <div class="signin-container">
            {{ $slot }}
        </div>
    </main>
    <footer>
        
    </footer>
    @stack('scripts')
</body>
</html>