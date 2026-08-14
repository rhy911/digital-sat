<x-layouts.auth title="Your Result">
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-black">Exam Complete</h1>
        <p class="text-sm sm:text-base text-gray-600 text-center">{{ $attempt->test->title }}</p>
    </div>

    <div class="w-11/12 flex flex-col items-center gap-2 my-4">
        <span class="text-5xl font-bold font-mono">{{ $attempt->total_score ?? '—' }}</span>
        <span class="text-sm text-gray-500">Total Score</span>

        @if ($attempt->score_reading_writing !== null || $attempt->score_math !== null)
            <div class="flex gap-8 mt-4">
                <div class="flex flex-col items-center">
                    <span class="text-2xl font-bold font-mono">{{ $attempt->score_reading_writing ?? '—' }}</span>
                    <span class="text-xs text-gray-500">Reading &amp; Writing</span>
                </div>
                <div class="flex flex-col items-center">
                    <span class="text-2xl font-bold font-mono">{{ $attempt->score_math ?? '—' }}</span>
                    <span class="text-xs text-gray-500">Math</span>
                </div>
            </div>
        @endif
    </div>

    <div class="auth-notice auth-notice--info">
        <svg class="auth-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
        </svg>
        <span>
            <strong class="auth-notice__title">This result is not saved yet</strong>
            Sign in or sign up and it moves to your account, alongside your other scores.
        </span>
    </div>

    {{-- `.active` is required on anchors too: .submit-btn's resting state is the
         greyed-out "pending validation" look AuthForm clears, and a link has
         nothing to validate. --}}
    <div class="w-11/12 flex gap-3 mt-3">
        <a href="{{ route('exam-join.link-account', ['destination' => 'signup']) }}"
            class="submit-btn active text-center flex-1">Sign Up</a>
        <a href="{{ route('exam-join.link-account', ['destination' => 'signin']) }}"
            class="btn-sm-ghost text-center flex-1 flex items-center justify-center">Sign In</a>
    </div>
</x-layouts.auth>
