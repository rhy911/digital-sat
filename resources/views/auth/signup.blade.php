<x-layouts.auth title="Sign Up">
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const firstNameInput = document.getElementById("firstName");
            const lastNameInput = document.getElementById("lastName");
            const passwordInput = document.getElementById("password");
            const passwordConfirmInput = document.getElementById("password_confirmation");
            const mismatchMsg = document.getElementById("passwordMismatch");

            new AuthForm('signupForm', {
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
                prepareData: (formData) => {
                    const name = firstNameInput.value.trim() + ' ' + lastNameInput.value.trim();
                    formData.set('name', name);
                    formData.delete('firstName');
                    formData.delete('lastName');
                    return formData;
                },
                onSuccess: (data) => {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
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
    <h2 class="signin-title">Create a Student Account</h2>

    <!-- Form -->
    <form id="signupForm" action="{{ route('signup') }}" method="POST" novalidate>
        <div class="name-row mb-3">
            <div class="form-group">
                <label for="firstName" class="form-label">First Name</label>
                <input type="text" class="form-control" id="firstName" name="firstName" autocomplete="given-name">
            </div>
            <div class="form-group">
                <label for="lastName" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="lastName" name="lastName" autocomplete="family-name">
            </div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" autocomplete="email">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
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

        <button type="submit" class="submit-btn" id="submitBtn" data-processing-text="Processing..." disabled>Create Account</button>
    </form>

    <!-- Sign in link -->
    <p class="signin-link">Already have an account? <a href="/signin">Sign in</a></p>
</x-layouts.auth>