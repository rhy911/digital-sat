<x-layouts.auth>
    @push('styles')

    <style>
        .form-control.is-invalid {
            border-color: #dc3545;
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15);
        }

        .invalid-feedback {
            font-size: 13px;
            color: #dc3545;
            margin-top: 5px;
        }

        .name-row {
            display: flex;
            gap: 14px;
        }

        .name-row .form-group {
            flex: 1;
        }

        .signin-link {
            text-align: center;
            font-size: 15px;
        }

        .signin-link a {
            color: #324dc7;
            text-decoration: none;
            font-weight: 600;
        }

        .signin-link a:hover {
            text-decoration: underline;
        }
    </style>

    @endpush

    @push('scripts')

    <script>
        const nameInput = document.getElementById("name");
        const emailInput = document.getElementById("email");
        const passwordInput = document.getElementById("password");
        const rePasswordInput = document.getElementById("password_confirmation");
        const submitBtn = document.getElementById("submitBtn");
        const mismatchMsg = document.getElementById("passwordMismatch");

        function checkFormValidity() {
            if (!nameInput || !emailInput || !passwordInput || !rePasswordInput) return;

            const allFilled =
                nameInput.value.trim() !== "" &&
                emailInput.value.trim() !== "" &&
                passwordInput.value.trim() !== "" &&
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

        [nameInput, emailInput, passwordInput, rePasswordInput]
        .filter(el => el !== null)
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

        @if ($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Form -->
        <form id="signupForm" method="POST" novalidate>
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" autocomplete="name">
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
                    <button type="button" class="password-toggle" id="password_confirmationToggle" data-password-target="password_confirmation" aria-label="Show password"></button>
                </div>
                <div class="invalid-feedback" id="passwordMismatch">Passwords do not match.</div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">Create Account</button>
        </form>

        <!-- Sign in link -->
        <p class="signin-link">Already have an account? <a href="/login">Sign in</a></p>
    </div>
</x-layouts.auth>