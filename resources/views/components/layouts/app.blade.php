@props([
    'user',
    'title' => null,
    'headerClass' => '',
    'logoClass' => '',
    'userClass' => '',
    'bodyClass' => 'antialiased bg-gray-50',
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Digital SAT' }}</title>
    @vite(['resources/css/app.css', 'resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
    {{ $head ?? '' }}
</head>
<body class="{{ $bodyClass }}">
    <x-app.user-header 
        :user="$user" 
        :header-class="$headerClass" 
        :logo-class="$logoClass" 
        :user-class="$userClass" 
    />
    
    <main>
        {{ $slot }}
    </main>

    {{ $scripts ?? '' }}
</body>
</html>
