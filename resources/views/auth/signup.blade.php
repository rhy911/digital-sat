@php
    $defaultRole = 'student';
@endphp

<x-layouts.auth title="Create Account">
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const signupForm = document.getElementById('signupForm');
                const passwordInput = document.getElementById('password');
                const passwordConfirmInput = document.getElementById('password_confirmation');
                const mismatchMsg = document.getElementById('passwordMismatch');

                const step1 = document.getElementById('step1');
                const step2 = document.getElementById('step2');
                const nextStepBtn = document.getElementById('nextStepBtn');
                const backBtn = document.getElementById('backBtn');

                // Step Navigation
                nextStepBtn.addEventListener('click', () => {
                    step1.classList.remove('active');
                    step2.classList.add('active');
                });

                backBtn.addEventListener('click', (e) => {
                    if (step2.classList.contains('active')) {
                        e.preventDefault();
                        step2.classList.remove('active');
                        step1.classList.add('active');
                    }
                });

                // Toggle Role Cards
                const roleCards = document.querySelectorAll('.role-option-card');
                roleCards.forEach(card => {
                    card.addEventListener('click', () => {
                        if (card.classList.contains('disabled')) return;
                        roleCards.forEach(c => c.classList.remove('selected'));
                        card.classList.add('selected');
                        const radio = card.querySelector('input[type="radio"]');
                        if (radio) radio.checked = true;
                    });
                });

                const validatePasswords = () => {
                    const passwordsMatch = passwordInput.value === passwordConfirmInput.value;
                    const hasConfirmValue = passwordConfirmInput.value.trim() !== '';

                    if (hasConfirmValue) {
                        if (!passwordsMatch) {
                            passwordConfirmInput.classList.add('is-invalid');
                            passwordConfirmInput.classList.remove('is-valid');
                            mismatchMsg.style.display = 'block';
                        } else {
                            passwordConfirmInput.classList.remove('is-invalid');
                            passwordConfirmInput.classList.add('is-valid');
                            mismatchMsg.style.display = 'none';
                        }
                    } else {
                        passwordConfirmInput.classList.remove('is-invalid', 'is-valid');
                        mismatchMsg.style.display = 'none';
                    }
                    return passwordsMatch;
                };

                new AuthForm('signupForm', {
                    validate: validatePasswords,
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
    <x-auth.back-link id="backBtn" />

    <form class="w-11/12" id="signupForm" action="{{ route('signup') }}" method="POST" novalidate>
        @csrf

        <!-- STEP 1: ROLE SELECTION -->
        <div id="step1" class="signup-step active flex-col gap-6">
            <div class="flex flex-col justify-center items-center gap-1">
                <h2 class="text-2xl sm:text-3xl font-bold text-center m-0 text-black">Create Your Account</h2>
                <p class="text-sm sm:text-base text-gray-600 text-center">Join our community. Build confidence one test
                    at a time.
                </p>
            </div>

            <!-- Role Selector -->
            <div class="auth-form-group">
                <label class="form-label mb-2">I am registering as a...</label>
                <div class="role-selector-container">
                    <div class="role-option-card {{ $defaultRole !== 'teacher' ? 'selected' : '' }}"
                        id="roleCardStudent">
                        <input type="radio" name="role" id="roleStudent" value="student" {{ $defaultRole !== 'teacher' ? 'checked' : '' }}>
                        <span class="role-icon">📖</span>
                        <span class="role-title">Student</span>
                        <span class="role-desc">Practice tests & track your progress</span>
                    </div>
                    <div class="role-option-card disabled" id="roleCardTeacher" aria-disabled="true"
                        title="Teacher signup is temporarily unavailable.">
                        <input type="radio" name="role" id="roleTeacher" value="teacher" disabled>
                        <span class="role-icon">🎓</span>
                        <span class="role-title">Teacher</span>
                        <span class="role-desc">Teacher signup is temporarily unavailable</span>
                    </div>
                </div>
            </div>

            <button type="button" class="primary-btn cursor-pointer w-full text-center" id="nextStepBtn">
                <span>Continue</span>
            </button>

            <!-- Sign in link -->
            <div class="links text-center mt-2">
                <a href="/signin?role={{ $defaultRole }}" class="text-sm font-semibold">Already have an account? Sign
                    In</a>
            </div>
        </div>

        <!-- STEP 2: CREDENTIALS -->
        <div id="step2" class="signup-step flex-col">
            <div class="flex flex-col justify-center items-center gap-1 mb-1">
                <h2 class="text-2xl sm:text-3xl font-bold text-center m-0 text-black">Account Details</h2>
                <p class="text-sm sm:text-base text-gray-600 text-center">Set up your username and password.</p>
            </div>

            <div class="auth-form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" autocomplete="username" required>
            </div>

            <div class="auth-form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
            </div>

            <div class="auth-form-group">
                <x-auth.password-field label="Password" input-id="password" name="password" autocomplete="new-password"
                    toggle-id="passwordToggle" target-id="password" />
            </div>

            <div class="auth-form-group">
                <x-auth.password-field label="Re-enter Password" input-id="password_confirmation"
                    name="password_confirmation" autocomplete="new-password" toggle-id="rePasswordToggle"
                    target-id="password_confirmation" />
                <div class="invalid-feedback" id="passwordMismatch">Passwords do not match.</div>
            </div>

            <x-auth.alerts />

            <div class="w-full mt-2">
                <button type="submit" class="submit-btn w-full text-center" id="submitBtn"
                    data-processing-text="Processing..." disabled>Create Account</button>
            </div>
        </div>
    </form>

    <div class="encouraging-footer mt-2">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
            class="feather feather-check-circle">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>Your answers auto-save during practice tests.</span>
    </div>
</x-layouts.auth>
