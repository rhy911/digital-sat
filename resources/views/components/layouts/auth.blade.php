@props(['title' => 'Digital SAT'])

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/css/auth.css', 'resources/js/app.js', 'resources/js/auth.js'])
    @livewireStyles
    @stack('styles')
</head>

<body>
    <header>
        <x-brand.wordmark href="/" size="lg" tone="inverse" />
    </header>
    <main>
        <div class="flex-1 flex items-center justify-center w-full my-4">
            <div class="auth-container bg-white">
                {{ $slot }}
            </div>
        </div>

        <footer class="auth-footer">
            <div class="flex justify-center gap-6">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Help Center</a>
            </div>
            <p class="text-center opacity-70 mt-3 text-[10px]">
                &copy; {{ date('Y') }} DigiSAT. All rights reserved.
            </p>
        </footer>
    </main>
    @livewireScripts
    @stack('scripts')
</body>

</html>
