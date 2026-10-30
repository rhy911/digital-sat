<x-layouts.auth title="Sign in">
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new AuthForm('signinForm', {
                    onSuccess: (data) => {
                        if (data.token) {
                            try { localStorage.setItem('api_token', data.token); } catch {}
                        }
                        window.location.href = data.redirect || '{{ route('dashboard') }}';
                    }
                });
            });
        </script>
    @endpush

    <x-auth.back-link :href="route('landing')">Home</x-auth.back-link>

    <div class="auth-heading">
        <h1>Sign in</h1>
        <p>Continue your practice and review your progress.</p>
    </div>

    <form class="w-full" id="signinForm" action="{{ route('signin') }}" method="POST">
        @csrf
        <div class="auth-form-group">
            <label for="email" class="form-label">Email address</label>
            <input type="email" class="form-control" id="email" name="email" autocomplete="username"
                value="{{ old('email') }}" required autofocus aria-describedby="email-error"
                @error('email') aria-invalid="true" @enderror>
            <div class="invalid-feedback" id="email-error">@error('email'){{ $message }}@enderror</div>
        </div>

        <div class="auth-form-group">
            <x-auth.password-field label="Password" input-id="password" name="password" autocomplete="current-password"
                toggle-id="passwordToggle" target-id="password" required aria-describedby="password-error" />
        </div>

        <div class="auth-form-links flex justify-between items-center w-full">
            <label class="flex items-center gap-2 cursor-pointer text-sm font-semibold text-slate-600">
                <input type="checkbox" name="remember" id="remember" value="1"
                    class="w-4 h-4 rounded border-slate-300 accent-brand cursor-pointer"
                    @checked(old('remember'))>
                <span>Remember me</span>
            </label>
            <a href="{{ route('forgot') }}" class="text-sm font-semibold text-brand">Forgot password?</a>
        </div>

        <x-auth.alerts :show-success="true" />

        <button type="submit" class="submit-btn" id="submitBtn" data-processing-text="Signing in…">Sign in</button>
    </form>

    <div class="links text-center">
        <a href="{{ route('signup') }}">New to DFET SAT? Create an account</a>
    </div>
</x-layouts.auth>
