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
        <a href="/" class="text-3xl text-white font-bold tracking-widest no-underline">
            <span><span class="font-semibold italic">Digi</span><span class="ml-0.5 font-black not-italic">SAT</span></span>
        </a>
    </header>
    <main>
        <div class="auth-container mx-auto bg-white gap-10 flex items-center flex-col  max-w-132 p-10">
            {{ $slot }}
        </div>
    </main>
    <footer>

    </footer>
    @livewireScripts
    @stack('scripts')
</body>

</html>
