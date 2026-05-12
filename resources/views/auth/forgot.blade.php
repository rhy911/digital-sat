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
        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
            <div class="invalid-feedback">Please enter your email address.</div>
        </div>

        <x-auth.alerts
            :show-success="true"
            error-style="display: none; color: #dc3545; margin-bottom: 1rem; padding: 0.75rem; background-color: #f8d7da; border-radius: 4px;"
            success-style="display: none; color: #155724; margin-bottom: 1rem; padding: 0.75rem; background-color: #d4edda; border-radius: 4px;"
        />

        <button type="submit" id="submitBtn" class="btn btn-primary w-100 mt-4" data-processing-text="Sending..." disabled>Send Reset Link</button>
    </form>
</x-layouts.auth>