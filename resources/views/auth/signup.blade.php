@php($defaultRole = $defaultRole ?? 'student')

<x-layouts.auth title="Create account">
    <x-auth.back-link :href="route('signin')">Sign in</x-auth.back-link>

    <div class="auth-heading">
        <h1>Create your account</h1>
        <p>Save your results and see your full analysis.</p>
    </div>

    <form class="w-full" id="signupForm" action="{{ route('signup') }}" method="POST">
        @csrf

        <fieldset class="auth-form-group auth-role-group">
            <legend class="form-label">Account type</legend>
            <div class="role-selector-container">
                <label class="role-option-card {{ $defaultRole === 'student' ? 'selected' : '' }}" for="roleStudent">
                    <input type="radio" name="role" id="roleStudent" value="student" required
                        @checked($defaultRole === 'student')>
                    <span class="role-title">Student</span>
                    <span class="role-desc">Practice and track your progress</span>
                </label>
                <label class="role-option-card {{ $defaultRole === 'teacher' ? 'selected' : '' }}" for="roleTeacher">
                    <input type="radio" name="role" id="roleTeacher" value="teacher"
                        @checked($defaultRole === 'teacher')>
                    <span class="role-title">Teacher</span>
                    <span class="role-desc">Request access to classroom tools</span>
                </label>
            </div>
            <p class="auth-role-note" id="teacherApprovalNote" @if ($defaultRole !== 'teacher') hidden @endif>
                Teacher accounts need administrator approval before classroom tools are available.
            </p>
            <div class="invalid-feedback" id="role-error"></div>
        </fieldset>

        <div class="auth-form-group">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" autocomplete="username"
                value="{{ old('username') }}" maxlength="255" required aria-describedby="username-error"
                @error('username') aria-invalid="true" @enderror>
            <div class="invalid-feedback" id="username-error">@error('username'){{ $message }}@enderror</div>
        </div>

        <div class="auth-form-group">
            <label for="email" class="form-label">Email address</label>
            <input type="email" class="form-control" id="email" name="email" autocomplete="email"
                value="{{ old('email') }}" maxlength="255" required aria-describedby="email-error"
                @error('email') aria-invalid="true" @enderror>
            <div class="invalid-feedback" id="email-error">@error('email'){{ $message }}@enderror</div>
        </div>

        <div class="auth-form-group">
            <x-auth.password-field label="Password" input-id="password" name="password" autocomplete="new-password"
                toggle-id="passwordToggle" target-id="password" minlength="8" required
                aria-describedby="password-hint password-error" />
            <p class="auth-field-hint" id="password-hint">Use at least 8 characters.</p>
        </div>

        <div class="auth-form-group">
            <x-auth.password-field label="Confirm password" input-id="password_confirmation"
                name="password_confirmation" autocomplete="new-password" toggle-id="rePasswordToggle"
                target-id="password_confirmation" required aria-describedby="password_confirmation-error" />
        </div>

        <x-auth.alerts />

        <button type="submit" class="submit-btn w-full" id="submitBtn"
            data-processing-text="Creating account…">Create account</button>
    </form>

    <div class="links text-center">
        <a href="{{ route('signin') }}">Already have an account? Sign in</a>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('signupForm');
                const password = document.getElementById('password');
                const confirmation = document.getElementById('password_confirmation');
                const roleNote = document.getElementById('teacherApprovalNote');
                const roleInputs = form.querySelectorAll('input[name="role"]');

                const updateRole = () => {
                    roleInputs.forEach(input => {
                        input.closest('.role-option-card').classList.toggle('selected', input.checked);
                    });
                    roleNote.hidden = form.querySelector('input[name="role"]:checked')?.value !== 'teacher';
                };

                roleInputs.forEach(input => input.addEventListener('change', updateRole));
                const validateConfirmation = () => {
                    confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value
                        ? 'Passwords must match.' : '');
                };
                [password, confirmation].forEach(input => input.addEventListener('input', validateConfirmation));

                new AuthForm('signupForm', {
                    validate: () => {
                        validateConfirmation();
                        return confirmation.checkValidity();
                    },
                    onSuccess: (data) => {
                        if (data.token) {
                            try { localStorage.setItem('api_token', data.token); } catch {}
                        }
                        window.location.href = data.redirect || '{{ route('verify.email.notice') }}';
                    }
                });
            });
        </script>
    @endpush
</x-layouts.auth>
