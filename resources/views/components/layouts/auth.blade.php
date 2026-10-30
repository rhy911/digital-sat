@props(['title' => 'DFET SAT'])

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <x-head.favicons />
    @vite(['resources/css/app.css', 'resources/css/auth.css', 'resources/js/app.js', 'resources/js/auth.js'])
    @livewireStyles
    @stack('styles')
</head>

<body class="auth-body">
    <a href="#auth-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:text-slate-900 focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-brand">
        Skip to content
    </a>
    <header class="auth-header">
        <a href="/" aria-label="DFET SAT home" class="auth-logo">
            <img src="/brand/logo-horizontal-inverse.svg" alt="" class="auth-logo__img">
        </a>
    </header>
    <main id="auth-content" class="auth-main">
        <div class="auth-stage">
            <div class="auth-container bg-white">
                {{ $slot }}
            </div>
        </div>
    </main>

    <footer class="auth-footer">
        <div class="auth-footer__links">
            <a href="/">Home</a>
            <a href="{{ route('exam-join.entry') }}">Have a code?</a>
            <a href="mailto:support@digisat.org">Help &amp; Support</a>
        </div>
        <p>
            &copy; {{ date('Y') }} DFET SAT. All rights reserved.
        </p>
    </footer>
    @livewireScripts
    @stack('scripts')
</body>

</html>
