<x-layouts.auth>
    @push('styles')

    <style>
        .form-control.is-invalid { border-color: #dc3545; }
        .form-control.is-invalid:focus { box-shadow: 0 0 0 3px rgba(220,53,69,0.15); }

        .invalid-feedback {
            font-size: 13px;
            color: #dc3545;
            margin-top: 5px;
        }

        .name-row { display: flex; gap: 14px; }
        .name-row .form-group { flex: 1; }

        .signin-link { text-align: center; font-size: 15px; }
        .signin-link a { color: #324dc7; text-decoration: none; font-weight: 600; }
        .signin-link a:hover { text-decoration: underline; }
    </style>

    @endpush

    @push('scripts')

    <script>
        const firstNameInput  = document.getElementById("firstName");
        const lastNameInput   = document.getElementById("lastName");
        const emailInput      = document.getElementById("email");
        const passwordInput   = document.getElementById("password");
        const rePasswordInput = document.getElementById("rePassword");
        const submitBtn       = document.getElementById("submitBtn");
        const mismatchMsg     = document.getElementById("passwordMismatch");

        function checkFormValidity() {
            const allFilled =
                firstNameInput.value.trim()  !== "" &&
                lastNameInput.value.trim()   !== "" &&
                emailInput.value.trim()      !== "" &&
                passwordInput.value.trim()   !== "" &&
                rePasswordInput.value.trim() !== "";

            const passwordsMatch = passwordInput.value === rePasswordInput.value;

            if (rePasswordInput.value.trim() !== "" && !passwordsMatch) {
                rePasswordInput.classList.add("is-invalid");
                mismatchMsg.style.display = "block";
            } else {
                rePasswordInput.classList.remove("is-invalid");
                mismatchMsg.style.display = "none";
            }

            const isValid = allFilled && passwordsMatch;
            submitBtn.disabled = !isValid;
            submitBtn.classList.toggle("active", isValid);
        }

        [firstNameInput, lastNameInput, emailInput, passwordInput, rePasswordInput]
            .forEach(el => el.addEventListener("input", checkFormValidity));

        checkFormValidity();
        if (typeof window.initPasswordToggles === "function") {
            window.initPasswordToggles();
        }
    </script>

    @endpush
    <div class="signin-container">
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
        <form id="signupForm" novalidate>
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
                <label for="rePassword" class="form-label">Re-enter Password</label>
                <div class="password-field">
                    <input type="password" class="form-control" id="rePassword" name="rePassword" autocomplete="new-password">
                    <button type="button" class="password-toggle" id="rePasswordToggle" data-password-target="rePassword" aria-label="Show password"></button>
                </div>
                <div class="invalid-feedback" id="passwordMismatch">Passwords do not match.</div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn" disabled>Create Account</button>
        </form>

        <!-- Sign in link -->
        <p class="signin-link">Already have an account? <a href="/signin">Sign in</a></p>
    </div>
</x-layouts.auth>