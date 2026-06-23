<x-layouts.auth title="Verify Email">
    @push('styles')
        <style>
            .signin-container {
                gap: 20px;
            }
        </style>
    @endpush
    <!-- Title -->
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-black">Verify Your Email</h1>
        <p class="text-sm sm:text-base text-gray-600 text-center">We sent a verification link to your email address.
            Please check your inbox.</p>
    </div>

    @auth
        <p class="text-center font-bold text-base text-[#1e293b]">
            {{ auth()->user()->email }}
        </p>
    @endauth

    <x-auth.alerts :show-success="true" />

    <form id="resendForm" action="{{ route('verification.send') }}" method="POST" novalidate class="w-full">
        @csrf
        <button type="submit" id="resendBtn" class="submit-btn w-full mt-2" data-processing-text="Sending...">Resend
            Verification Email</button>
    </form>

    <form id="logoutForm" method="POST" action="{{ route('logout') }}" class="links text-center w-full">
        @csrf
        <button type="submit"
            class="text-sm font-semibold text-[#324dc7] bg-transparent border-none p-0 cursor-pointer hover:underline">Logout</button>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const resendBtn = document.getElementById('resendBtn');
                let countdownInterval;

                function startCountdown(seconds) {
                    resendBtn.disabled = true;
                    resendBtn.classList.remove('active');
                    clearInterval(countdownInterval);

                    resendBtn.textContent = `Vui lòng đợi ${seconds}s`;

                    countdownInterval = setInterval(() => {
                        seconds--;
                        if (seconds <= 0) {
                            clearInterval(countdownInterval);
                            resendBtn.disabled = false;
                            resendBtn.classList.add('active');
                            resendBtn.textContent = 'Resend Verification Email';
                        } else {
                            resendBtn.textContent = `Vui lòng đợi ${seconds}s`;
                        }
                    }, 1000);
                }

                new AuthForm('resendForm', {
                    onSuccess: (data) => {
                        const successMsg = document.getElementById('successMessage');
                        if (successMsg) {
                            successMsg.textContent = data.message ||
                                'Verification email sent! Check your inbox.';
                            successMsg.style.display = 'block';
                        }
                        if (data.cooldown) {
                            setTimeout(() => startCountdown(data.cooldown), 10);
                        }
                    },
                    onError: (data, status) => {
                        if (data.cooldown) {
                            setTimeout(() => startCountdown(data.cooldown), 10);
                        }
                    }
                });

                // Handle logout form
                const logoutForm = document.getElementById('logoutForm');
                if (typeof window.initAjaxLogout === 'function' && logoutForm) {
                    window.initAjaxLogout({
                        formEl: logoutForm
                    });
                }
            });
        </script>
    @endpush
</x-layouts.auth>
