<x-layouts.auth title="Sign Up">
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const passwordInput = document.getElementById('password');
            const passwordConfirmInput = document.getElementById('password_confirmation');
            const mismatchMsg = document.getElementById('passwordMismatch');

            new AuthForm('signupForm', {
                validate: () => {
                    const passwordsMatch = passwordInput.value === passwordConfirmInput.value;
                    if (passwordConfirmInput.value.trim() !== '' && !passwordsMatch) {
                        passwordConfirmInput.classList.add('is-invalid');
                        mismatchMsg.style.display = 'block';
                    } else {
                        passwordConfirmInput.classList.remove('is-invalid');
                        mismatchMsg.style.display = 'none';
                    }
                    return passwordsMatch;
                },
                onSuccess: (data) => {
                    if (data.token) {
                        localStorage.setItem('api_token', data.token);
                    }
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                }
            });
        });
    </script>
    @endpush
    <!-- Back -->
    <x-auth.back-link href="/" />

    <!-- Title -->
    <h2 class="signin-title">Create a Student Account</h2>

    <!-- Form -->
    <form id="signupForm" action="{{ route('signup') }}" method="POST" novalidate>
        @csrf
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" autocomplete="username" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
        </div>

        <div class="mb-3">
            <x-auth.password-field
                label="Password"
                input-id="password"
                name="password"
                autocomplete="new-password"
                toggle-id="passwordToggle"
                target-id="password"
            />
        </div>

        <div class="mb-4">
            <x-auth.password-field
                label="Re-enter Password"
                input-id="password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                toggle-id="rePasswordToggle"
                target-id="password_confirmation"
            />
            <div class="invalid-feedback" id="passwordMismatch">Passwords do not match.</div>
        </div>

        <x-auth.alerts
            error-style="display: none; color: #dc3545; margin-bottom: 1rem; padding: 0.75rem; background-color: #f8d7da; border-radius: 4px;"
        />

        <button type="submit" class="submit-btn" id="submitBtn" data-processing-text="Processing..." disabled>Create Account</button>
    </form>

    <!-- Sign in link -->
    <p class="signin-link">Already have an account? <a href="/signin">Sign in</a></p>
</x-layouts.auth>