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
    <form id="resetForm" novalidate>
        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
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

        <button type="submit" id="submitBtn" class="btn btn-primary w-100 mt-4" disabled>Reset password</button>
    </form>
</x-layouts.auth>