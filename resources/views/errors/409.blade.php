@php
    // A 409 in the engine means the browser and the server disagree about which
    // module is current — a stale tab, a bookmarked module URL, or a session that
    // finished elsewhere. Laravel's bare error page left students with nothing to
    // click, so resolve their real position and offer it.
    $attempt = null;

    if (auth()->check()) {
        $attempt = \App\Models\UserTest::where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->latest('updated_at')
            ->first();
    }

    $resumeUrl = null;
    if ($attempt?->currentModule) {
        $resumeUrl = route('engine.session', ['ulid' => $attempt->currentModule->ulid]).'?attempt='.$attempt->ulid;
    }
@endphp

<x-layouts.landing title="This page has moved on — DigiSAT">
    <div class="mx-auto flex min-h-[60vh] max-w-lg flex-col items-center justify-center px-5 py-16 text-center sm:px-6">
        <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <line x1="12" y1="8" x2="12" y2="13" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-slate-900">Your test has already moved on</h1>
        <p class="mt-3 max-w-sm text-sm leading-relaxed text-slate-600">
            This page is no longer the active part of your test — it was most likely left open in another tab, or
            opened from an old link. <strong class="font-semibold text-slate-800">Your answers are saved.</strong>
        </p>

        @if ($resumeUrl)
            <a href="{{ $resumeUrl }}"
                class="mt-8 inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800">
                Continue where you left off
            </a>
            <a href="{{ route('home') }}" class="mt-3 text-sm font-medium text-slate-500 underline hover:text-slate-700">
                Back to dashboard
            </a>
        @else
            <a href="{{ route('home') }}"
                class="mt-8 inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800">
                Back to dashboard
            </a>
        @endif
    </div>
</x-layouts.landing>
