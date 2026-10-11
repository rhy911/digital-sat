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
                    document.getElementById('username')?.focus();
                });

                backBtn.addEventListener('click', (e) => {
                    if (step2.classList.contains('active')) {
                        e.preventDefault();
                        step2.classList.remove('active');
                        step1.classList.add('active');
                        nextStepBtn?.focus();
                    }
                });

                // Toggle Role Cards
                const roleRadios = document.querySelectorAll('input[name="role"]');
                const updateRoleCards = () => {
                    document.querySelectorAll('.role-option-card').forEach(card => {
                        const radio = card.querySelector('input[type="radio"]');
                        card.classList.toggle('selected', radio && radio.checked);
                    });
                };
                roleRadios.forEach(radio => {
                    radio.addEventListener('change', updateRoleCards);
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

    <form class="w-full" id="signupForm" action="{{ route('signup') }}" method="POST" novalidate>
        @csrf

        <!-- STEP 1: ROLE SELECTION -->
        <div id="step1" class="signup-step active flex-col gap-6">
            <div class="flex flex-col justify-center items-center gap-1">
                <h2 class="text-2xl sm:text-3xl font-bold text-center m-0 text-slate-900">Create Your Account</h2>
                <p class="text-sm sm:text-base text-gray-600 text-center">Join our community. Build confidence one test
                    at a time.
                </p>
            </div>

            <!-- Role Selector -->
            <div class="auth-form-group">
                <label class="form-label mb-2">I am registering as a...</label>
                <div class="role-selector-container">
                    <label class="role-option-card cursor-pointer {{ $defaultRole !== 'teacher' ? 'selected' : '' }}"
                        id="roleCardStudent" for="roleStudent">
                        <input type="radio" name="role" id="roleStudent" value="student"
                            {{ $defaultRole !== 'teacher' ? 'checked' : '' }}>
                        <span class="role-icon">📖</span>
                        <span class="role-title">Student</span>
                        <span class="role-desc">Practice tests & track your progress</span>
                    </label>
                    <label class="role-option-card cursor-pointer" id="roleCardTeacher" for="roleTeacher">
                        <input type="radio" name="role" id="roleTeacher" value="teacher">
                        <span class="role-icon">🎓</span>
                        <span class="role-title">Teacher</span>
                        <span class="role-desc">Create classes after administrator approval</span>
                    </label>
                </div>
            </div>

            <button type="button" class="primary-btn cursor-pointer w-full text-center" id="nextStepBtn">
                <span>Continue</span>
            </button>

            <!-- Sign in link -->
            <div class="links text-center mt-2">
                <a href="{{ route('signin.form', ['role' => $defaultRole]) }}" class="text-sm font-semibold">Already have an account? Sign
                    in</a>
            </div>
        </div>

        <!-- STEP 2: CREDENTIALS -->
        <div id="step2" class="signup-step flex-col">
            <div class="flex flex-col justify-center items-center gap-1 mb-1">
                <h2 class="text-2xl sm:text-3xl font-bold text-center m-0 text-slate-900">Account Details</h2>
                <p class="text-sm sm:text-base text-gray-600 text-center">Set up your username and password.</p>
            </div>

            <div class="auth-form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" autocomplete="username"
                    required>
            </div>

            <div class="auth-form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
            </div>

            <div class="auth-form-group">
                <x-auth.password-field label="Password" input-id="password" name="password" autocomplete="new-password"
                    toggle-id="passwordToggle" target-id="password" required />
            </div>

            <div class="auth-form-group">
                <x-auth.password-field label="Re-enter Password" input-id="password_confirmation"
                    name="password_confirmation" autocomplete="new-password" toggle-id="rePasswordToggle"
                    target-id="password_confirmation" required />
                <div class="invalid-feedback" id="passwordMismatch" role="alert" aria-live="polite">Passwords do not match.</div>
            </div>

            <x-auth.alerts />

            <div class="w-full mt-2">
                <button type="submit" class="submit-btn w-full text-center" id="submitBtn"
                    data-processing-text="Processing...">Create account</button>
            </div>
        </div>
    </form>

    <x-auth.encouraging-notice class="mt-2" />
</x-layouts.auth>
