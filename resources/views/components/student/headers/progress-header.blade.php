@props([
    'user' => null,
])

@php
    $canUseTeacherTools = $user && in_array($user->role, ['admin', 'teacher']);
    $navItems = [
        [
            'label' => 'Progress',
            'href' => route('home'),
            'active' => request()->routeIs('home', 'my-practice', 'my-practice.score', 'engine.submit-status'),
            'current' => request()->routeIs('home'),
        ],
        [
            'label' => 'Practice',
            'href' => route('home.practice'),
            'active' => request()->routeIs('home.practice', 'test.preview', 'engine.session', 'engine.test.attempt-options'),
            'current' => request()->routeIs('home.practice'),
        ],
    ];

    if ($canUseTeacherTools) {
        $navItems[] = [
            'label' => 'Teacher tools',
            'href' => route('home-dashboard.index'),
            'active' => request()->routeIs('home-dashboard.*'),
            'current' => request()->routeIs('home-dashboard.index'),
        ];
    }
@endphp

<header class="ds-topbar">
    <div class="ds-topbar__inner">
        <x-brand.wordmark href="{{ route('home') }}" size="md" tone="dark" label="DigiSAT progress home" />

        <nav class="ds-topbar__nav" aria-label="Primary">
            @foreach($navItems as $item)
                <a
                    href="{{ $item['href'] }}"
                    @class(['is-active' => $item['active']])
                    @if($item['current']) aria-current="page" @endif
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="ds-account">
            <button type="button" class="ds-account__button" id="progressUserDropdown" aria-haspopup="menu" aria-expanded="false">
                <span class="ds-account__name">{{ $user->username ?? 'Guest' }}</span>
                <img src="{{ asset('images/default_avt.jpg') }}" alt="" class="ds-account__avatar">
            </button>

            <div class="ds-account__menu" id="progressDropdownMenu" role="menu">
                @if($user && in_array($user->role, ['admin', 'teacher']))
                    <a href="{{ route('home-dashboard.index') }}" class="dropdown-item" role="menuitem">Test Dashboard</a>
                @endif

                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="dropdown-item logout-btn" role="menuitem">Sign Out</button>
                </form>
            </div>
        </div>
    </div>

    <nav class="ds-mobile-nav" aria-label="Primary mobile">
        @foreach($navItems as $item)
            <a
                href="{{ $item['href'] }}"
                @class(['is-active' => $item['active']])
                @if($item['current']) aria-current="page" @endif
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</header>
