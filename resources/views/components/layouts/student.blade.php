@props([
    'user',
    'title' => 'Digital SAT',
    'headerType' => 'default', // 'default' or 'progress'
    'headerClass' => '',
    'logoClass' => '',
    'userClass' => '',
    'bodyClass' => null,
    'cancelRoute' => null,
])

@php
    if (!$bodyClass) {
        $bodyClass = $headerType === 'progress' ? 'antialiased ds-home-shell' : 'antialiased bg-gray-50';
    }
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
    {{ $head ?? '' }}
</head>

<body class="{{ $bodyClass }}">
    <x-ui.loading-screen :cancel-route="$cancelRoute" />

    @if ($headerType === 'progress')
        <x-student.headers.progress-header :user="$user" />
    @elseif ($headerType !== 'none')
        <x-student.headers.user-header :user="$user" :header-class="$headerClass" :logo-class="$logoClass" :user-class="$userClass" />
    @endif

    @if ($headerType !== 'none')
        <main class="{{ $headerType === 'progress' ? 'ds-home-main' : '' }}">
            {{ $slot }}
        </main>
    @else
        {{ $slot }}
    @endif

    @livewireScripts
    @stack('scripts')
    {{ $scripts ?? '' }}
    <script>
        function initStudentHeader() {
            const logoutForm = document.getElementById('header-logout-form');
            if (typeof window.initAjaxLogout === 'function') {
                window.initAjaxLogout({
                    formEl: logoutForm,
                    redirectTo: '/signin',
                    tokenStorageKey: 'api_token'
                });
            }
        }

        document.addEventListener('DOMContentLoaded', initStudentHeader);
        document.addEventListener('livewire:navigated', initStudentHeader);
    </script>
</body>

</html>
