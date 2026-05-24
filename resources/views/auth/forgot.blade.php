<x-layouts.auth title="Forgot Password">
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new AuthForm('forgotForm', {
                onSuccess: (data) => {
                    const successMsg = document.getElementById('successMessage');
                    const forgotForm = document.getElementById('forgotForm');
                    if (successMsg) {
                        successMsg.textContent = data.message || 'Password reset link sent! Check your email.';
                        successMsg.style.display = 'block';
                    }
                    if (forgotForm) forgotForm.reset();
                }
            });
        });
    </script>
    @endpush

    <!-- Back -->
    <x-auth.back-link href="/" />

    <!-- Title -->
    <h2 class="signin-title">Forgot Your Password?</h2>

    <!-- Form -->
    <form id="forgotForm" action="{{ route('forgot') }}" method="POST" novalidate>
        @csrf
        <div class="mb-6">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
            <div class="invalid-feedback">Please enter your email address.</div>
        </div>

        <x-auth.alerts :show-success="true" />

        <button type="submit" id="submitBtn" class="submit-btn" data-processing-text="Sending..." disabled>Send Reset Link</button>
    </form>

    <!-- Help -->
    <a href="/signin" class="help-link">Back to sign in</a>
</x-layouts.auth>
