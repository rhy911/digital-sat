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
        <h1 class="text-3xl font-bold text-center m-0 text-black">Verify Your Email</h1>
        <p class="text-base text-gray-600 text-center">We sent a verification link to your email address. Please check your inbox.</p>
    </div>

    @auth
        <p style="text-align: center; font-size: 16px;">
            <strong>{{ auth()->user()->email }}</strong>
        </p>
    @endauth

    <x-auth.alerts :show-success="true" />

    <form id="resendForm" action="{{ route('verification.send') }}" method="POST" novalidate>
        @csrf
        <button type="submit" id="resendBtn" class="submit-btn w-full mt-4" data-processing-text="Sending...">Resend
            Verification Email</button>
    </form>

    <form id="logoutForm" method="POST" action="{{ route('logout') }}" class="links text-center w-full">
        @csrf
        <button type="submit" style="background: none; border: none; padding: 0; font: inherit; cursor: pointer; color: #324dc7; font-weight: 500; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Logout</button>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new AuthForm('resendForm', {
                    onSuccess: (data) => {
                        const successMsg = document.getElementById('successMessage');
                        if (successMsg) {
                            successMsg.textContent = data.message || 'Verification email sent! Check your inbox.';
                            successMsg.style.display = 'block';
                        }
                    }
                });

                // Handle logout form
                const logoutForm = document.getElementById('logoutForm');
                if (typeof window.initAjaxLogout === 'function' && logoutForm) {
                    window.initAjaxLogout({ formEl: logoutForm });
                }
            });
        </script>
    @endpush
</x-layouts.auth>