@props([
    'title' => 'DFET SAT — Digital SAT Practice & Progress Tracking',
    'description' => 'Practice for the Digital SAT with realistic tests, clear score reports, and progress tracking. Find your weak spots and plan what to study next.',
])

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#10234A">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <x-head.favicons />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-site">
    <a href="#main-content" class="public-skip-link">Skip to content</a>
    <header class="public-header" x-data="{ mobileNavOpen: false }" @keydown.escape.window="if (mobileNavOpen) { mobileNavOpen = false; $refs.menuBtn.focus(); }">
        <div class="public-wrap public-header__main">
            <a href="/" class="public-brand" aria-label="DFET SAT home"><img src="/brand/logo-horizontal.svg" alt="DFET SAT"></a>
            <nav aria-label="Main navigation" class="public-nav">
                <a href="/#students">For students</a>
                <a href="/#score-reports">Score reports</a>
                <a href="/#tutors">For tutors</a>
            </nav>
            <div class="public-header__actions">
                <a class="public-header__exam" href="{{ route('exam-join.entry') }}">Join an exam</a>
                <a class="public-header__signin" href="{{ route('signin') }}">Sign in</a>
                <a class="public-header__try" href="{{ route('public-trial.index') }}">Try DFET SAT</a>
                <button type="button"
                        class="public-header__menu-btn"
                        x-ref="menuBtn"
                        :aria-expanded="mobileNavOpen.toString()"
                        aria-controls="public-mobile-dropdown"
                        aria-label="Toggle navigation menu"
                        @click="mobileNavOpen = !mobileNavOpen">
                    <svg x-show="!mobileNavOpen" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    <svg x-show="mobileNavOpen" x-cloak width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
        </div>
        <div id="public-mobile-dropdown"
             class="public-mobile-dropdown"
             x-show="mobileNavOpen"
             x-cloak
             @click.outside="mobileNavOpen = false"
             style="display: none;">
            <nav aria-label="Mobile navigation" class="public-mobile-links">
                <a href="/#students" @click="mobileNavOpen = false">For students</a>
                <a href="/#score-reports" @click="mobileNavOpen = false">Score reports</a>
                <a href="/#tutors" @click="mobileNavOpen = false">For tutors</a>
                <a href="{{ route('exam-join.entry') }}" @click="mobileNavOpen = false">Join an exam</a>
                <a href="{{ route('signin') }}" @click="mobileNavOpen = false">Sign in</a>
                <a href="{{ route('signup') }}" @click="mobileNavOpen = false">Create account</a>
            </nav>
        </div>
    </header>
    <main id="main-content">{{ $slot }}</main>
    <footer class="public-footer">
        <div class="public-wrap public-footer__grid">
            <div class="public-footer__brand">
                <a href="/" aria-label="DFET SAT home"><img src="/brand/logo-horizontal.svg" alt="DFET SAT"></a>
                <p>Digital SAT practice for students, families, and independent tutors.</p>
            </div>
            <div><h2>Practice</h2><a href="{{ route('public-trial.index') }}">Try DFET SAT</a><a href="/#students">Student experience</a><a href="/#score-reports">Score reports</a></div>
            <div><h2>Accounts</h2><a href="{{ route('signup') }}">Create an account</a><a href="{{ route('signin') }}">Sign in</a><a href="{{ route('exam-join.entry') }}">Join an exam</a></div>
            <div><h2>Educators</h2><a href="{{ route('signup', ['role' => 'teacher']) }}">Apply for teacher access</a><a href="/#faq">Frequently asked questions</a></div>
        </div>
        <div class="public-wrap public-footer__bottom"><p>© {{ date('Y') }} DFET SAT</p><p>SAT® is a trademark registered by the College Board, which is not affiliated with and does not endorse this product.</p></div>
    </footer>
    @stack('scripts')
</body>
</html>
