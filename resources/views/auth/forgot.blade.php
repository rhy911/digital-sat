<x-layouts.auth title="Forgot Password">
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new AuthForm('forgetForm', {
                onSuccess: (data) => {
                    const successMsg = document.getElementById('successMessage');
                    const forgetForm = document.getElementById('forgetForm');
                    if (successMsg) {
                        successMsg.textContent = data.message || 'Password reset link sent! Check your email.';
                        successMsg.style.display = 'block';
                    }
                    if (forgetForm) forgetForm.reset();
                }
            });
        });
    </script>
    @endpush

    <!-- Back -->
    <a href="/" class="back-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        Back
    </a>

    <!-- Title -->
    <h2 class="signin-title">Forgot Your Password?</h2>

    <!-- Form -->
    <form id="forgetForm" action="{{ route('forgot') }}" method="POST" novalidate>
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
            <div class="invalid-feedback">Please enter your email address.</div>
        </div>

        <div id="errorMessage" style="display: none; color: #dc3545; margin-bottom: 1rem; padding: 0.75rem; background-color: #f8d7da; border-radius: 4px;"></div>
        <div id="successMessage" style="display: none; color: #155724; margin-bottom: 1rem; padding: 0.75rem; background-color: #d4edda; border-radius: 4px;"></div>

        <button type="submit" id="submitBtn" class="btn btn-primary w-100 mt-4" data-processing-text="Sending..." disabled>Send Reset Link</button>
    </form>
</x-layouts.auth>