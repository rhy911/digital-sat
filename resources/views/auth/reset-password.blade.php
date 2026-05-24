<x-layouts.auth title="Reset Password">
    <!-- Back -->
    <x-auth.back-link href="/" />

    <!-- Title -->
    <h2 class="signin-title">Reset Your Password</h2>

    <!-- Form -->
    <form id="resetForm" action="{{ route('password.update') }}" method="POST" novalidate>
        @csrf
        <!-- Hidden fields for token and email from the URL -->
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="mb-3">
            <x-auth.password-field 
                id="password" 
                name="password" 
                label="New Password" 
                placeholder="Enter new password" 
                autocomplete="new-password"
                toggle-id="passwordToggle"
                target-id="password"
            />
        </div>

        <div class="mb-4">
            <x-auth.password-field 
                id="password_confirmation" 
                name="password_confirmation" 
                label="Re-enter Password" 
                placeholder="Confirm new password" 
                autocomplete="new-password"
                toggle-id="rePasswordToggle"
                target-id="password_confirmation"
            />
            <div class="invalid-feedback" id="passwordMismatch">Passwords do not match.</div>
        </div>

        <x-auth.alerts />

        <button type="submit" id="submitBtn" class="submit-btn w-full mt-4" data-processing-text="Processing..." disabled>Reset password</button>
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
