<x-layouts.auth title="Join Exam Session">
    {{-- A candidate who followed a wrong or stale code needs a way back to the
         code form; there is no other exit from this screen. --}}
    <x-auth.back-link :href="route('exam-join.entry')">Enter a different code</x-auth.back-link>

    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-black">{{ $session->title }}</h1>
        <p class="text-sm sm:text-base text-gray-600 text-center">{{ $session->test->title }}</p>
    </div>

    @if (session('error') || $errors->any())
        <div class="auth-notice auth-notice--error" role="alert">
            <svg class="auth-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span>{{ session('error') ?: $errors->first() }}</span>
        </div>
    @endif

    <div class="auth-notice auth-notice--info">
        <svg class="auth-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
        </svg>
        <span>
            <strong class="auth-notice__title">The timer starts as soon as you begin</strong>
            It keeps running even if you close the tab, so start when you are ready to sit the whole exam.
        </span>
    </div>

    <form class="w-11/12" id="examJoinForm" method="POST" action="{{ route('exam-join.process', $session->code) }}"
        novalidate>
        @csrf

        @if ($alreadySignedIn)
            <p class="text-sm text-gray-600 text-center mb-4">
                Joining as <strong>{{ auth()->user()->name }}</strong>.
            </p>
        @else
            <div class="auth-form-group">
                <label for="display_name" class="form-label">Your Name</label>
                <input type="text" class="form-control" id="display_name" name="display_name" maxlength="100"
                    value="{{ old('display_name') }}" autofocus required
                    @if ($errors->any()) aria-invalid="true" @endif>
            </div>
        @endif

        <button type="submit" class="submit-btn" id="examJoinSubmit">Start Exam</button>
    </form>

    @unless ($alreadySignedIn)
        <div class="links text-center">
            <a href="{{ route('signin.form', ['role' => 'student']) }}">Already have an account? Sign in, then come back to this link.</a>
        </div>
    @endunless

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('examJoinForm');
                const submit = document.getElementById('examJoinSubmit');
                // Absent when the candidate is already signed in — that form has
                // nothing to fill in, so the button is valid from the start.
                const name = document.getElementById('display_name');

                // .submit-btn is grey/not-allowed until .active is added. The
                // sign-in screens get that from AuthForm; this page posts a
                // plain form, so it has to drive the state itself.
                const syncState = () => {
                    submit.classList.toggle('active', !name || name.value.trim().length > 0);
                };

                name?.addEventListener('input', syncState);

                form.addEventListener('submit', (event) => {
                    if (name && name.value.trim().length === 0) {
                        event.preventDefault();
                        name.focus();
                        return;
                    }

                    // Starting an exam is not idempotent from the candidate's
                    // point of view — a double click must not fire two joins.
                    if (submit.dataset.submitting === 'true') {
                        event.preventDefault();
                        return;
                    }

                    submit.dataset.submitting = 'true';
                    submit.classList.add('is-busy');
                    submit.textContent = 'Starting…';
                });

                syncState();
            })();
        </script>
    @endpush
</x-layouts.auth>
