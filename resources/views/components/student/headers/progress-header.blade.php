@props([
    'user' => null,
])

@php
    $hasTeacherHomeTabs = $user?->role === 'teacher' && $user->isApprovedTeacher();
    $isTeacherHome = $hasTeacherHomeTabs && request()->routeIs('home');
    $storedTeacherTab = session('teacher_home.tab', 'progress');
    $teacherHomeTab = in_array($storedTeacherTab, ['progress', 'classes', 'reports'], true) ? $storedTeacherTab : 'progress';

    if ($hasTeacherHomeTabs) {
        $navItems = [
            ['label' => 'Progress', 'tab' => 'progress', 'href' => route('teacher.progress'), 'active' => false],
            ['label' => 'Classes', 'tab' => 'classes', 'href' => route('teacher.classes.index'), 'active' => request()->routeIs('teacher.classes.*'), 'current' => request()->routeIs('teacher.classes.show')],
            ['label' => 'Reports', 'tab' => 'reports', 'href' => route('teacher.assignments.index'), 'active' => request()->routeIs('teacher.assignments.*'), 'current' => request()->routeIs('teacher.assignments.show')],
            ['label' => 'Practice', 'href' => route('home.practice'), 'active' => request()->routeIs('home.practice', 'test.preview', 'engine.session', 'engine.test.attempt-options')],
            ['label' => 'Test Builder', 'href' => route('home-dashboard.index'), 'active' => request()->routeIs('home-dashboard.*'), 'external' => true],
        ];
    } else {
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
    }

    if ($user?->role === 'student') {
        array_splice($navItems, 1, 0, [[
            'label' => 'Assignments',
            'href' => route('student.assignments.index'),
            'active' => request()->routeIs('student.assignments.*'),
            'current' => request()->routeIs('student.assignments.index'),
            'navigate' => true,
        ], [
            'label' => 'Classes',
            'href' => route('student.classes.index'),
            'active' => request()->routeIs('student.classes.*'),
            'current' => request()->routeIs('student.classes.index'),
            'navigate' => true,
        ]]);
    }

    if ($user?->role === 'admin') {
        $navItems[] = [
            'label' => 'Applications',
            'href' => route('admin.teacher-applications.index'),
            'active' => request()->routeIs('admin.teacher-applications.*'),
            'current' => request()->routeIs('admin.teacher-applications.index'),
        ];
        $navItems[] = [
            'label' => 'Test Builder',
            'href' => route('home-dashboard.index'),
            'active' => request()->routeIs('home-dashboard.*'),
            'external' => true,
        ];
    }
@endphp

<header class="ds-topbar">
    <div class="ds-topbar__inner">
        <x-brand.wordmark href="{{ route('home') }}" size="md" tone="dark" label="DigiSAT progress home" />

        <nav class="ds-topbar__nav" aria-label="Primary" @if($isTeacherHome) x-data="{ tab: '{{ $teacherHomeTab }}' }" @teacher-home-tab-requested.window="tab = $event.detail.tab" @teacher-home-tab-changed.window="tab = $event.detail.tab" @endif>
            @foreach($navItems as $item)
                @if($isTeacherHome && isset($item['tab']))
                    <button type="button"
                        @if($item['tab'] === 'progress')
                            @click="tab = 'progress'; $dispatch('teacher-home-tab-requested', { tab: 'progress' }); Livewire.dispatch('teacher-home-progress')"
                        @elseif($item['tab'] === 'classes')
                            @click="tab = 'classes'; $dispatch('teacher-home-tab-requested', { tab: 'classes' }); Livewire.dispatch('teacher-workspace-section', { section: 'classes' })"
                        @else
                            @click="tab = 'reports'; $dispatch('teacher-home-tab-requested', { tab: 'reports' }); Livewire.dispatch('teacher-workspace-section', { section: 'assignments' })"
                        @endif
                        :class="{ 'is-active': tab === '{{ $item['tab'] }}' }"
                        :aria-current="tab === '{{ $item['tab'] }}' ? 'page' : null"
                    >{{ $item['label'] }}</button>
                @else
                    <a href="{{ $item['href'] }}"
                        @if($item['navigate'] ?? false) wire:navigate @endif
                        @class(['is-active' => $item['active'] ?? false, 'ds-nav-destination' => $item['external'] ?? false])
                        @if($item['current'] ?? false) aria-current="page" @endif
                    >{{ $item['label'] }}</a>
                @endif
            @endforeach
        </nav>

        <div class="ds-account">
            <button type="button" class="ds-account__button" id="progressUserDropdown" aria-haspopup="menu" aria-expanded="false">
                <span class="ds-account__name">{{ $user->username ?? 'Guest' }}</span>
                <img src="{{ asset('images/default_avt.jpg') }}" alt="" class="ds-account__avatar">
            </button>

            <div class="ds-account__menu" id="progressDropdownMenu" role="menu">
                <a href="{{ route('profile') }}" class="dropdown-item" role="menuitem" style="display: block; width: 100%; text-align: left; padding: 10px 16px; color: var(--cw-ink); text-decoration: none; font-weight: 700;">My Profile</a>
                <div style="border-top: 1px solid var(--cw-line); margin: 4px 0;"></div>
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="dropdown-item logout-btn" role="menuitem">Sign Out</button>
                </form>
            </div>
        </div>
    </div>

    <nav class="ds-mobile-nav" aria-label="Primary mobile" @if($isTeacherHome) x-data="{ tab: '{{ $teacherHomeTab }}' }" @teacher-home-tab-requested.window="tab = $event.detail.tab" @teacher-home-tab-changed.window="tab = $event.detail.tab" @endif>
        @foreach($navItems as $item)
            @if($isTeacherHome && isset($item['tab']))
                <button type="button"
                    @if($item['tab'] === 'progress')
                        @click="tab = 'progress'; $dispatch('teacher-home-tab-requested', { tab: 'progress' }); Livewire.dispatch('teacher-home-progress')"
                    @elseif($item['tab'] === 'classes')
                        @click="tab = 'classes'; $dispatch('teacher-home-tab-requested', { tab: 'classes' }); Livewire.dispatch('teacher-workspace-section', { section: 'classes' })"
                    @else
                        @click="tab = 'reports'; $dispatch('teacher-home-tab-requested', { tab: 'reports' }); Livewire.dispatch('teacher-workspace-section', { section: 'assignments' })"
                    @endif
                    :class="{ 'is-active': tab === '{{ $item['tab'] }}' }"
                    :aria-current="tab === '{{ $item['tab'] }}' ? 'page' : null"
                >{{ $item['label'] }}</button>
            @else
                <a href="{{ $item['href'] }}"
                    @if($item['navigate'] ?? false) wire:navigate @endif
                    @class(['is-active' => $item['active'] ?? false, 'ds-nav-destination' => $item['external'] ?? false])
                    @if($item['current'] ?? false) aria-current="page" @endif
                >{{ $item['label'] }}</a>
            @endif
        @endforeach
    </nav>
</header>
