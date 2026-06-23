<x-layouts.auth title="Forgot Password">
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new AuthForm('forgotForm', {
                    onSuccess: (data) => {
                        const successMsg = document.getElementById('successMessage');
                        const forgotForm = document.getElementById('forgotForm');
                        if (successMsg) {
                            successMsg.textContent = data.message ||
                                'Password reset link sent! Check your email.';
                            successMsg.style.display = 'block';
                        }
                        if (forgotForm) forgotForm.reset();
                    }
                });
            });
        </script>
    @endpush

    <!-- Back -->
    <x-auth.back-link />

    <!-- Title -->
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-black">Forgot Your Password?</h1>
        <p class="text-sm sm:text-base text-gray-600 text-center">We'll help you get back on track.</p>
    </div>

    <!-- Form -->
    <form id="forgotForm" action="{{ route('forgot') }}" method="POST" novalidate class="w-11/12">
        @csrf
        <div class="auth-form-group">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email"
                placeholder="Enter your email address" required>
            <div class="invalid-feedback">Please enter your email address.</div>
        </div>

        <x-auth.alerts :show-success="true" />

        <button type="submit" id="submitBtn" class="submit-btn" data-processing-text="Sending..." disabled>Send Reset
            Link</button>
    </form>

    <!-- Help -->
    <div class="links text-center">
        <a href="/signin" class="text-sm font-semibold">Back to sign in</a>
    </div>
</x-layouts.auth>
