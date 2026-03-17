<x-layouts.auth title="Forgot Password">
    @push('scripts')

    <script>
        const emailInput = document.getElementById("email");
        const submitBtn  = document.getElementById("submitBtn");

        function checkFormValidity() {
            const emailFilled = emailInput.value.trim() !== "";
            submitBtn.disabled = !emailFilled;
            submitBtn.classList.toggle("active", emailFilled);
        }

        emailInput.addEventListener("input", checkFormValidity);
        checkFormValidity();
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
    <form id="forgetForm" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" placeholder="Enter your email address" required>
            <div class="invalid-feedback">Please enter your email address.</div>
        </div>

        <button type="submit" id="submitBtn" class="btn btn-primary w-100 mt-4" disabled>Send Reset Link</button>
        <a href="/reset-password" class="btn btn-link">Temp navigation link</a>
    </form>
</x-layouts.auth>