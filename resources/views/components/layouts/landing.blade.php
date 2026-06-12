<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'PrepFlow | The Definitive Digital SAT Experience' }}</title>
    <!-- Geist Font -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1.3.0/dist/fonts/geist.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-geist bg-slate-50 text-slate-900 selection:bg-blue-100 selection:text-brand">

    <!-- Navigation -->
    <nav class="fixed top-0 w-full z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center">
                <div class="flex flex-col leading-none">
                    <span class="font-bold text-2xl tracking-wider"><span
                            class="text-slate-900 italic">Digi</span>SAT</span>
                </div>
            </div>

            <div class="hidden md:flex items-center gap-10 text-sm font-medium text-slate-600">
                <a href="#fidelity" class="hover:text-brand transition-colors">Immersion</a>
                <a href="#teachers" class="hover:text-brand transition-colors">Educator Tools</a>
                <a href="#scoring" class="hover:text-brand transition-colors">IRT Science</a>
            </div>

            <div class="flex items-center gap-4">
                <a href="/login" class="text-sm font-medium hover:text-brand transition-colors">Sign in</a>
                <a href="/register"
                    class="bg-brand text-white px-5 py-2.5 rounded-full text-sm font-semibold hover:opacity-90 transition-all shadow-lg shadow-blue-500/20 active:scale-95">
                    Start Free Session
                </a>
            </div>
        </div>
    </nav>

    <main class="pt-20">
        {{ $slot }}
    </main>

    <footer class="py-24 px-6 border-t border-slate-200 bg-white">
        <div class="max-w-7xl mx-auto grid md:grid-cols-12 gap-16">
            <div class="md:col-span-5">
                <div class="flex items-center gap-2 mb-8">
                    <div class="w-8 h-8 bg-brand rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold italic">D</span>
                    </div>
                    <span class="font-bold text-xl tracking-tight">DigiSAT</span>
                </div>
                <p class="text-slate-500 leading-relaxed max-w-sm mb-10">
                    The definitive testing platform and management suite for Digital SAT preparation.
                </p>
            </div>

            <div class="md:col-span-7 grid grid-cols-2 sm:grid-cols-3 gap-12">
                <div>
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-widest mb-8">System</h4>
                    <ul class="space-y-4 text-sm text-slate-500">
                        <li><a href="#" class="hover:text-brand transition-colors">Immersion</a></li>
                        <li><a href="#" class="hover:text-brand transition-colors">Adaptive Routing</a></li>
                        <li><a href="#" class="hover:text-brand transition-colors">IRT Model</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-widest mb-8">Portals</h4>
                    <ul class="space-y-4 text-sm text-slate-500">
                        <li><a href="#" class="hover:text-brand transition-colors">Student Portal</a></li>
                        <li><a href="#" class="hover:text-brand transition-colors">Educator CMS</a></li>
                        <li><a href="#" class="hover:text-brand transition-colors">Classroom Admin</a></li>
                    </ul>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-widest mb-8">Resources</h4>
                    <ul class="space-y-4 text-sm text-slate-500">
                        <li><a href="#" class="hover:text-brand transition-colors">Item Bank</a></li>
                        <li><a href="#" class="hover:text-brand transition-colors">Analytics</a></li>
                        <li><a href="#" class="hover:text-brand transition-colors">Security</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            class="max-w-7xl mx-auto mt-24 pt-8 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-6">
            <p class="text-xs text-slate-400">© {{ date('Y') }} PrepFlow. Built with Laravel & Tailwind v4.</p>
            <p class="text-[10px] text-slate-300 max-w-md md:text-right italic leading-relaxed">SAT® is a trademark
                registered by the College Board, which is not affiliated with, and does not endorse, this product.</p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
