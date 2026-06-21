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

    @if($headerType === 'progress')
        <x-student.headers.progress-header :user="$user" />
    @else
        <x-student.headers.user-header 
            :user="$user" 
            :header-class="$headerClass" 
            :logo-class="$logoClass" 
            :user-class="$userClass" 
        />
    @endif
    
    <main class="{{ $headerType === 'progress' ? 'ds-home-main' : '' }}">
        {{ $slot }}
    </main>

    @livewireScripts
    @stack('scripts')
    {{ $scripts ?? '' }}
    <script>
        function initStudentHeader() {
            const triggerId = "{{ $headerType === 'progress' ? 'progressUserDropdown' : 'userDropdown' }}";
            const menuId = "{{ $headerType === 'progress' ? 'progressDropdownMenu' : 'dropdownMenu' }}";
            const trigger = document.getElementById(triggerId);

            if (!trigger || trigger.dataset.menuReady === 'true') return;
            trigger.dataset.menuReady = 'true';

            if (typeof window.initDropdownToggle === 'function') {
                const { menuEl } = window.initDropdownToggle({
                    triggerId: triggerId,
                    menuId: menuId,
                    openClass: 'show',
                });
                
                @if($headerType === 'progress')
                    const accountButton = document.getElementById(triggerId);
                    const syncAccountState = () => {
                        accountButton?.setAttribute('aria-expanded', menuEl?.classList.contains('show') ? 'true' : 'false');
                    };
                    accountButton?.addEventListener('click', () => window.setTimeout(syncAccountState, 0));
                    document.addEventListener('click', () => window.setTimeout(syncAccountState, 0));
                @endif

                const logoutForm = menuEl?.querySelector?.('form');
                if (typeof window.initAjaxLogout === 'function') {
                    window.initAjaxLogout({ formEl: logoutForm, redirectTo: '/signin', tokenStorageKey: 'api_token' });
                }
            }
        }

        document.addEventListener('DOMContentLoaded', initStudentHeader);
        document.addEventListener('livewire:navigated', initStudentHeader);
    </script>
</body>
</html>
