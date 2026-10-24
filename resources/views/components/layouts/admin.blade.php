@props(['ui' => 'legacy', 'title' => 'Dashboard'])

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? $title }}</title>
    <x-head.favicons />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>

<body class="@stack('body_class') {{ $ui === 'product' ? 'product-ui' : '' }}">

    <main>
        {{ $slot }}
    </main>
    @livewireScripts
    @stack('scripts')
</body>

</html>
