<x-layouts.auth>
    <!-- Back -->
    <a href="/" class="back-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        Back
    </a>

    <!-- Title -->
    <h2 class="signin-title">Reset Your Password</h2>

    <!-- Form -->
    <form id="resetForm" action="{{ route('reset-password') }}" method="POST" novalidate>
        <!-- Hidden fields for token and email from the URL -->
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <div class="password-field">
                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                <button type="button" class="password-toggle" id="passwordToggle" data-password-target="password" aria-label="Show password"></button>
            </div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Re-enter Password</label>
            <div class="password-field">
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
                <button type="button" class="password-toggle" id="rePasswordToggle" data-password-target="password_confirmation" aria-label="Show password"></button>
            </div>
            <div class="invalid-feedback" id="passwordMismatch">Passwords do not match.</div>
        </div>

        <div id="errorMessage" style="display: none; color: #dc3545; margin-bottom: 1rem; padding: 0.75rem; background-color: #f8d7da; border-radius: 4px;"></div>

        <button type="submit" id="submitBtn" class="btn btn-primary w-100 mt-4" data-processing-text="Processing..." disabled>Reset password</button>
    </form>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const passwordInput = document.getElementById('password');
            const passwordConfirmInput = document.getElementById('password_confirmation');
            const mismatchMsg = document.getElementById('passwordMismatch');

            new AuthForm('resetForm', {
                validate: () => {
                    const passwordsMatch = passwordInput.value === passwordConfirmInput.value;
                    if (passwordConfirmInput.value.trim() !== "" && !passwordsMatch) {
                        passwordConfirmInput.classList.add("is-invalid");
                        mismatchMsg.style.display = "block";
                    } else {
                        passwordConfirmInput.classList.remove("is-invalid");
                        mismatchMsg.style.display = "none";
                    }
                    return passwordsMatch;
                },
                onSuccess: (data) => {
                    window.location.href = '{{ route("signin") }}';
                }
            });
        });
    </script>
    @endpush
</x-layouts.auth>