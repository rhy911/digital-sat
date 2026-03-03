<x-layouts.auth title="Sign In">
    @push('styles')
        <style>
        .forgot-link {
            color: #324dc7;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .forgot-link:hover { text-decoration: underline; }

        .help-link {
            text-align: center;
            color: #324dc7;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            display: block;
        }
        .help-link:hover { text-decoration: underline; }
        </style>
    @endpush

    @push('scripts')
        <script>
            const emailInput    = document.getElementById("email");
            const passwordInput = document.getElementById("password");
            const submitBtn     = document.getElementById("submitBtn");

            function checkFormValidity() {
                const filled = emailInput.value.trim() !== "" && passwordInput.value.trim() !== "";
                submitBtn.disabled = !filled;
                submitBtn.classList.toggle("active", filled);
            }

            emailInput.addEventListener("input", checkFormValidity);
            passwordInput.addEventListener("input", checkFormValidity);
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
    <h2 class="signin-title">Sign In with a Student Account</h2>

    <!-- Form -->
    <form id="signinForm" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" autocomplete="email">
        </div>

        <div class="mb-2">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password">
        </div>

        <div class="mb-4">
            <a href="#" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn" disabled>Submit</button>
    </form>

    <!-- Help -->
    <a href="#" class="help-link">Need help signing in?</a>
</div>
</x-layouts.auth>