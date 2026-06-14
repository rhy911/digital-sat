<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DigiSAT' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1.3.0/dist/fonts/geist.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-geist bg-slate-50 text-slate-900 selection:bg-slate-950 selection:text-white">
    <nav class="fixed top-0 z-50 w-full border-b border-slate-200 bg-white/90 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-6">
            <x-brand.wordmark href="/" size="md" tone="dark" />

            <div class="hidden items-center gap-10 text-sm font-medium text-slate-600 md:flex">
                <a href="#fidelity" data-scroll-target="#fidelity"
                    class="rounded-md transition-colors hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">Immersion</a>
                <a href="#teachers" data-scroll-target="#teachers"
                    class="rounded-md transition-colors hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">Educator Tools</a>
                <a href="#scoring" data-scroll-target="#scoring"
                    class="rounded-md transition-colors hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">IRT Science</a>
            </div>

            <div class="flex items-center gap-3 sm:gap-4">
                <a href="/signin"
                    class="rounded-md text-sm font-semibold transition-colors hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">Sign in</a>
                <a href="/signup"
                    class="hidden min-h-11 items-center rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand active:scale-[0.98] sm:inline-flex">
                    Start Free Session
                </a>
            </div>
        </div>
    </nav>

    <main class="pt-20">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white px-5 py-20 sm:px-6">
        <div class="mx-auto grid max-w-7xl gap-14 md:grid-cols-12">
            <div class="md:col-span-5">
                <div class="mb-8 flex items-center gap-2 text-slate-900">
                    <x-brand.wordmark size="sm" tone="dark" />
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
                        <li><a href="#features" class="transition-colors hover:text-brand">Security</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            class="mx-auto mt-20 flex max-w-7xl flex-col items-start justify-between gap-6 border-t border-slate-100 pt-8 md:flex-row md:items-center">
            <p class="text-xs text-slate-500">&copy; {{ date('Y') }} DigiSAT.</p>
            <p class="max-w-md text-xs leading-relaxed text-slate-500 md:text-right">
                SAT&reg; is a trademark registered by the College Board, which is not affiliated with, and does not
                endorse, this product.
            </p>
        </div>
    </footer>

    @stack('scripts')
    <script>
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-scroll-target]');
            if (!trigger) return;

            const target = document.querySelector(trigger.dataset.scrollTarget);
            if (!target) return;

            event.preventDefault();
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            target.scrollIntoView({
                behavior: prefersReducedMotion ? 'auto' : 'smooth',
                block: 'start',
            });
        });
    </script>
</body>

</html>
