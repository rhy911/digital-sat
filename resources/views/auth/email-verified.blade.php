<x-layouts.auth title="Email Verified Successfully">
    @push('styles')
        <style>
            .success-checkmark {
                border: 2px solid #bbf7d0;
                box-shadow: 0 10px 25px rgba(22, 163, 74, 0.1);
                animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            }

            .success-checkmark svg {
                stroke-dasharray: 100;
                stroke-dashoffset: 100;
                animation: drawCheck 0.6s ease-in-out 0.3s forwards;
            }

            .redirect-timer {
                border: 1px solid #e2e8f0;
                animation: fadeIn 0.4s ease-in-out forwards;
            }

            @keyframes popIn {
                0% {
                    transform: scale(0.6);
                    opacity: 0;
                }

                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }

            @keyframes drawCheck {
                to {
                    stroke-dashoffset: 0;
                }
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(8px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>
    @endpush

    <div class="flex flex-col items-center justify-center gap-6 w-full text-center">
        <!-- Success Icon -->
        <div class="success-checkmark w-20 h-20 mx-auto bg-green-50 rounded-full flex items-center justify-center">
            <svg class="text-green-600 w-10 h-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <!-- Title & Subtitle -->
        <div class="flex flex-col justify-center items-center gap-1.5">
            <h1 class="text-3xl font-bold text-center m-0 text-black">Email Verified!</h1>
            <p class="text-base text-gray-600 text-center">Your account has been successfully verified. Welcome to
                PrepSat™.</p>
        </div>

        <!-- Timer / Redirect notice -->
        <div
            class="redirect-timer bg-slate-50 py-2.5 px-5 rounded-full text-sm font-medium text-slate-500 flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-[#4361EE]" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <span>Redirecting to home in <strong id="countdown" class="text-indigo-600 text-lg">99</strong>
                seconds...</span>
        </div>

        <!-- Direct button just in case -->
        <a href="{{ route('home') }}" class="primary-btn no-underline text-center w-full active mt-2" role="button">
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
                        window.location.href = "{{ route('home') }}";
                    }
                }, 1000);
            });
        </script>
    @endpush
</x-layouts.auth>