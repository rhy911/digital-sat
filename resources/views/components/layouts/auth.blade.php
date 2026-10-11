@props(['title' => 'Digital SAT'])

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

<body class="auth-body min-h-screen min-h-dvh flex flex-col bg-brand m-0">
    <a href="#auth-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:text-slate-900 focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-brand">
        Skip to content
    </a>
    <header class="bg-brand shrink-0">
        <a href="/" aria-label="DigiSAT home" class="auth-logo">
            <img src="/brand/logo-horizontal-inverse.svg" alt="" class="auth-logo__img">
        </a>
    </header>
    <main id="auth-content" class="flex-1 flex flex-col items-center justify-center w-full bg-brand">
        <div class="flex-1 flex items-center justify-center w-full my-auto py-6">
            <div class="auth-container bg-white">
                {{ $slot }}
            </div>
        </div>
    </main>

    <footer class="auth-footer bg-brand mt-auto shrink-0 w-full">
        <div class="flex justify-center gap-6">
            <a href="/">Home</a>
            <a href="{{ route('exam-join.entry') }}">Have a code?</a>
            <a href="mailto:support@digisat.org">Help &amp; Support</a>
        </div>
        <p class="text-center text-white/80 mt-3 text-xs">
            &copy; {{ date('Y') }} DigiSAT. All rights reserved.
        </p>
    </footer>
    @livewireScripts
    @stack('scripts')
</body>

</html>
