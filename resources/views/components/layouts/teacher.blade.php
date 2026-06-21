@props(['title' => 'Teacher workspace'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | DigiSAT</title>
    @vite(['resources/css/app.css', 'resources/css/classroom.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="classroom-body">
<div class="teacher-shell">
    <aside class="teacher-nav">
        <x-brand.wordmark href="{{ auth()->user()->role === 'admin' ? route('admin.teacher-applications.index') : route('home') }}" size="md" tone="inverse" label="DigiSAT home" />
        <p class="teacher-nav__label">{{ auth()->user()->role === 'admin' ? 'Admin workspace' : 'Teacher workspace' }}</p>
        <nav aria-label="Teacher navigation">
            <div class="teacher-nav__group teacher-nav__group--local" aria-labelledby="workspace-views-label">
                <p id="workspace-views-label" class="teacher-nav__group-label">Workspace views</p>
                <div class="teacher-nav__group-items">
                    <a href="{{ route('teacher.classes.index') }}" @class(['is-active' => request()->routeIs('teacher.classes.*')]) @if(request()->routeIs('teacher.classes.*')) aria-current="page" @endif>Classes</a>
                    <a href="{{ route('teacher.assignments.index') }}" @class(['is-active' => request()->routeIs('teacher.assignments.*')]) @if(request()->routeIs('teacher.assignments.*')) aria-current="page" @endif>Assignments & reports</a>
                </div>
            </div>

            <div class="teacher-nav__group teacher-nav__group--destinations" aria-labelledby="other-areas-label">
                <p id="other-areas-label" class="teacher-nav__group-label">Other areas</p>
                <div class="teacher-nav__group-items">
                    @if(auth()->user()->role === 'teacher')
                        <a href="{{ route('home') }}">Home</a>
                    @else
                        <a href="{{ route('admin.teacher-applications.index') }}" wire:navigate @class(['is-active' => request()->routeIs('admin.teacher-applications.*')]) @if(request()->routeIs('admin.teacher-applications.*')) aria-current="page" @endif>Applications</a>
                    @endif
                    <a href="{{ route('home-dashboard.index') }}" @if(request()->routeIs('home-dashboard.*')) aria-current="page" @endif>Content Builder</a>
                </div>
            </div>
        </nav>
        <form action="{{ route('logout') }}" method="POST" class="teacher-nav__logout">@csrf<button type="submit">Sign out</button></form>
    </aside>
    <main class="teacher-main">
        <header class="teacher-topbar">
            <div><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->role === 'admin' ? 'Administrator' : 'Approved teacher' }}</span></div>
        </header>
        <div class="teacher-content">
            @if(session('success'))<div class="class-alert class-alert--success" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="class-alert class-alert--error" role="alert">{{ $errors->first() }}</div>@endif
            {{ $slot }}
        </div>
    </main>
</div>
@livewireScripts
</body>
</html>
