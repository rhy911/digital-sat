<x-layouts.auth title="Verify Email">
    <!-- Title -->
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-slate-900">Verify Your Email</h1>
        <p class="text-sm sm:text-base text-gray-600 text-center">Verify your email to finish setting up your account. If you do not see a message, you can request another one below.</p>
    </div>

    @auth
        <p class="auth-account-email">{{ auth()->user()->email }}</p>
    @endauth

    <x-auth.alerts :show-success="true" />

    <form id="resendForm" action="{{ route('verification.send') }}" method="POST" class="w-full">
        @csrf
        <button type="submit" id="resendBtn" class="submit-btn w-full mt-2" data-processing-text="Sending...">Resend
            verification email</button>
        <p id="resendCooldown" class="auth-field-hint" aria-live="off"></p>
        <p id="resendCooldownAnnouncement" class="sr-only" role="status" aria-live="polite"></p>
    </form>

    <form id="logoutForm" method="POST" action="{{ route('logout') }}" class="links text-center w-full">
        @csrf
        <button type="submit"
            class="text-sm font-semibold text-brand bg-transparent border-none p-0 cursor-pointer hover:underline">Sign out</button>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const resendBtn = document.getElementById('resendBtn');
                const cooldownText = document.getElementById('resendCooldown');
                const cooldownAnnouncement = document.getElementById('resendCooldownAnnouncement');
                let countdownInterval;

                function startCountdown(seconds) {
                    const deadline = Date.now() + seconds * 1000;
                    resendBtn.disabled = true;
                    resendBtn.classList.remove('active');
                    clearInterval(countdownInterval);
                    cooldownAnnouncement.textContent = `Please wait ${seconds} seconds before requesting another verification email.`;

                    const updateCountdown = () => {
                        const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
                        if (remaining === 0) {
                            clearInterval(countdownInterval);
                            resendBtn.disabled = false;
                            resendBtn.classList.add('active');
                            cooldownText.textContent = '';
                            cooldownAnnouncement.textContent = 'You can request another verification email now.';
                            return;
                        }
                        cooldownText.textContent = `You can request another email in ${remaining} seconds.`;
                    };

                    updateCountdown();
                    countdownInterval = setInterval(() => {
                        updateCountdown();
                    }, 1000);
                }

                const authForm = new AuthForm('resendForm', {
                    onSuccess: (data) => {
                        authForm.showSuccess(data.message || 'Verification email sent. Check your inbox.');
                        if (data.cooldown) authForm.setCooldown(data.cooldown);
                    },
                    onError: (data, status) => {
                        if (data.cooldown) authForm.setCooldown(data.cooldown);
                    }
                });

                authForm.config.onCooldown = startCountdown;

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
