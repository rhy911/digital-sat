<!DOCTYPE html>
<html lang="en" class="motion-safe:scroll-smooth scroll-pt-20">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DigiSAT — Authentic Digital SAT Practice & Educator Tools' }}</title>
    <x-head.favicons />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1.3.0/dist/fonts/geist.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-geist bg-slate-50 text-slate-900 selection:bg-slate-950 selection:text-white">
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-white focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-white">
        Skip to content
    </a>

    <nav aria-label="Main navigation" class="fixed top-0 z-50 w-full border-b border-slate-200 bg-white/90 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6">
            <a href="/" aria-label="DigiSAT home" class="inline-flex items-center">
                <img src="/brand/logo-horizontal.svg" alt="" class="h-9 w-auto">
            </a>

            <div class="hidden items-center gap-2 text-sm font-medium text-slate-600 md:flex">
                <a href="#fidelity"
                    class="rounded-md px-3 py-2 transition-colors hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">Immersion</a>
                <a href="#teachers"
                    class="rounded-md px-3 py-2 transition-colors hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">Educator
                    Tools</a>
                <a href="#scoring"
                    class="rounded-md px-3 py-2 transition-colors hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">IRT
                    Science</a>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                {{-- Walk-in candidates arrive with a code read out to them and no
                     account; without this the only way in is a link they do not have. --}}
                <a href="{{ route('exam-join.entry') }}"
                    class="inline-flex min-h-10 items-center rounded-md px-3 py-2 text-sm font-semibold transition-colors hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">Have
                    a code?</a>
                <a href="/signin"
                    class="inline-flex min-h-10 items-center rounded-md px-3 py-2 text-sm font-semibold transition-colors hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">Sign
                    in</a>
                <a href="/signup"
                    class="hidden min-h-11 items-center rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand active:scale-[0.96] sm:inline-flex">
                    Start free test
                </a>
            </div>
        </div>
    </nav>

    <main id="main-content" class="pt-20">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white px-5 py-20 sm:px-6">
        <div class="mx-auto grid max-w-7xl gap-14 md:grid-cols-12">
            <div class="md:col-span-5">
                <div class="mb-8 flex items-center gap-2 text-slate-900">
                    <img src="/brand/logo-horizontal.svg" alt="DigiSAT" class="h-7 w-auto">
                </div>
                <p class="max-w-sm leading-relaxed text-slate-600">
                    The definitive testing platform and management suite for Digital SAT preparation.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-12 sm:grid-cols-3 md:col-span-7">
                <div>
                    <h4 class="mb-6 text-sm font-bold text-slate-900">System</h4>
                    <ul class="space-y-4 text-sm text-slate-600">
                        <li><a href="#fidelity" class="transition-colors hover:text-brand">Immersion</a></li>
                        <li><a href="#features" class="transition-colors hover:text-brand">Adaptive Routing</a></li>
                        <li><a href="#scoring" class="transition-colors hover:text-brand">IRT Model</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="mb-6 text-sm font-bold text-slate-900">Portals</h4>
                    <ul class="space-y-4 text-sm text-slate-600">
                        <li><a href="/signup" class="transition-colors hover:text-brand">Student Portal</a></li>
                        <li><a href="/signin" class="transition-colors hover:text-brand">Educator CMS</a></li>
                        <li><a href="/signin" class="transition-colors hover:text-brand">Classroom Admin</a></li>
                    </ul>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <h4 class="mb-6 text-sm font-bold text-slate-900">Resources</h4>
                    <ul class="space-y-4 text-sm text-slate-600">
                        <li><a href="#teachers" class="transition-colors hover:text-brand">Item Bank</a></li>
                        <li><a href="#scoring" class="transition-colors hover:text-brand">Analytics</a></li>
                        <li><a href="#fidelity" class="transition-colors hover:text-brand">Exam Fidelity</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            class="mx-auto mt-20 flex max-w-7xl flex-col items-start justify-between gap-6 border-t border-slate-100 pt-8 md:flex-row md:items-center">
            <p class="text-xs text-slate-600">&copy; {{ date('Y') }} DigiSAT.</p>
            <p class="max-w-md text-xs leading-relaxed text-slate-600 md:text-right">
                SAT&reg; is a trademark registered by the College Board, which is not affiliated with, and does not
                endorse, this product.
            </p>
        </div>
    </footer>

    @stack('scripts')
</body>

</html>
