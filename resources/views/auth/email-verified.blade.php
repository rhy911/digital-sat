<x-layouts.auth title="Email Verified Successfully">
    <div class="flex flex-col items-center justify-center gap-6 w-full text-center">
        <!-- Success Icon -->
        <div class="success-checkmark w-20 h-20 mx-auto bg-emerald-50 rounded-full flex items-center justify-center">
            <svg class="text-emerald-600 w-10 h-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <!-- Title & Subtitle -->
        <div class="flex flex-col justify-center items-center gap-1.5">
            <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-black">Email Verified!</h1>
            <p class="text-sm sm:text-base text-gray-600 text-center">Your account has been successfully verified.
                Welcome to
                DigiSAT.</p>
        </div>

        <!-- Timer / Redirect notice -->
        <div
            class="redirect-timer bg-slate-50 py-2.5 px-5 rounded-full text-sm font-medium text-slate-500 flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-brand" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                </circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <span>Redirecting to home in <strong id="countdown" class="text-brand text-lg">99</strong>
                seconds...</span>
        </div>

        <!-- Direct button just in case -->
        <a href="{{ route('dashboard') }}" class="primary-btn no-underline text-center w-full active mt-2"
            role="button">
            Continue Now
        </a>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                let seconds = 99;
                const countdownEl = document.getElementById('countdown');
                const interval = setInterval(() => {
                    seconds--;
                    if (countdownEl) countdownEl.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = "{{ route('dashboard') }}";
                    }
                }, 1000);
            });
        </script>
    @endpush
</x-layouts.auth>
