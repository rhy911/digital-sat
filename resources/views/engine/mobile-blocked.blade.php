<x-layouts.landing title="Switch devices to continue — DigiSAT">
    <div class="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center px-5 py-16 text-center sm:px-6">
        <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-brand">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8" aria-hidden="true">
                <rect x="4" y="2" width="16" height="20" rx="2" />
                <line x1="4" y1="18" x2="20" y2="18" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-slate-900">This test needs a bigger screen</h1>
        <p class="mt-3 max-w-sm text-sm leading-relaxed text-slate-600">
            Your current screen is too small to run the test engine. Please switch to a device with a larger
            display — most tablets and all laptops/desktops work fine — to start or continue your test.
        </p>

        <a href="{{ route('home') }}"
            class="mt-8 inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800">
            Back to dashboard
        </a>
    </div>
</x-layouts.landing>
