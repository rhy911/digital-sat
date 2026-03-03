<x-layouts.auth>
    <div class="signin-container">
        <!-- Back -->
        <a href="authentication.php" class="back-link">
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
                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
            </div>

            <div class="mb-4">
                <label for="rePassword" class="form-label">Re-enter Password</label>
                <input type="password" class="form-control" id="rePassword" name="rePassword" autocomplete="new-password">
                <div class="invalid-feedback" id="passwordMismatch">Passwords do not match.</div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn" disabled>Create Account</button>
        </form>

        <!-- Sign in link -->
        <p class="signin-link">Already have an account? <a href="student_signin.php">Sign in</a></p>
    </div>
</x-layouts.auth>