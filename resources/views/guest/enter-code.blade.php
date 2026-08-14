@php
    $signedIn = auth()->check() && ! (auth()->user()->is_guest ?? false);
@endphp

<x-layouts.auth title="Join an Exam">
    {{-- Explicit href rather than the component's history-back fallback: this
         page is reached from two different places (landing nav when signed out,
         /home when signed in), and a candidate who typed the URL directly has
         no history to go back to. --}}
    <x-auth.back-link :href="$signedIn ? route('home') : route('landing')">
        {{ $signedIn ? 'Back to home' : 'Back' }}
    </x-auth.back-link>

    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-black">Join an exam</h1>
        <p class="text-sm sm:text-base text-gray-600 text-center">
            Enter the six-character code your teacher gave you.
        </p>
    </div>

    @if ($errors->any())
        <div class="auth-notice auth-notice--error" role="alert">
            <svg class="auth-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span>
                <strong class="auth-notice__title">That code did not work</strong>
                {{ $errors->first() }}
            </span>
        </div>
    @endif

    <form class="w-11/12" id="examCodeForm" method="POST" action="{{ route('exam-join.entry.submit') }}" novalidate>
        @csrf

        <div class="auth-form-group">
            <label for="code" class="form-label">Exam code</label>
            {{-- autocapitalize + the uppercasing input handler keep the field
                 matching the codes as they are printed and read aloud; the
                 server uppercases too, so this is presentation only. --}}
            <input type="text" class="form-control text-center font-mono tracking-[0.35em] uppercase" id="code"
                name="code" value="{{ old('code') }}" maxlength="6" minlength="6" required autofocus
                autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="X7K9P2"
                inputmode="latin" @if ($errors->any()) aria-invalid="true" @endif>
        </div>

        <button type="submit" class="submit-btn" id="examCodeSubmit">Continue</button>
    </form>

    @unless ($signedIn)
        <div class="links text-center">
            <a href="{{ route('signin.form', ['role' => 'student']) }}">Have an account? Sign in</a>
        </div>
    @endunless

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('examCodeForm');
                const input = document.getElementById('code');
                const submit = document.getElementById('examCodeSubmit');
                const REQUIRED_LENGTH = 6;

                // .submit-btn renders grey/not-allowed until something adds
                // .active — on the sign-in screens that is AuthForm, which this
                // page does not use. Without this the button looks permanently
                // disabled and never acknowledges anything.
                const syncState = () => {
                    submit.classList.toggle('active', input.value.length === REQUIRED_LENGTH);
                };

                input.addEventListener('input', () => {
                    input.value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                    syncState();
                });

                form.addEventListener('submit', (event) => {
                    if (input.value.length !== REQUIRED_LENGTH) {
                        event.preventDefault();
                        input.focus();
                        return;
                    }

                    // Guard the double-submit and show the click landed; the
                    // POST is a full page navigation, so this state simply ends
                    // when the next document paints.
                    if (submit.dataset.submitting === 'true') {
                        event.preventDefault();
                        return;
                    }

                    submit.dataset.submitting = 'true';
                    submit.classList.add('is-busy');
                    submit.textContent = 'Checking…';
                });

                syncState();
            })();
        </script>
    @endpush
</x-layouts.auth>
