<x-layouts.auth title="Sign In">
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new AuthForm('signinForm', {
                onSuccess: (data) => {
                    if (data.token) {
                        localStorage.setItem('api_token', data.token);
                    }
                    window.location.href = '{{ route('home') }}';
                }
            });
        });
    </script>
    @endpush
    <!-- Back -->
    <x-auth.back-link href="/" />

    <!-- Title -->
    <h1 class="signin-title">Sign In with a Student Account</h1>

    <!-- Form -->
    <form id="signinForm" action="{{ route('signin') }}" method="POST" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" autocomplete="email">
        </div>

        <div class="mb-2">
            <x-auth.password-field
                label="Password"
                input-id="password"
                name="password"
                autocomplete="current-password"
                toggle-id="passwordToggle"
                target-id="password"
            />
        </div>

        <div class="mb-6">
            <a href="{{ route('forgot') }}" class="help-link text-start">Forgot password?</a>
        </div>

        <x-auth.alerts />

        <button type="submit" class="submit-btn" id="submitBtn" data-processing-text="Processing..." disabled>Submit</button>
    </form>

    <!-- Help -->
    <a href="/signup" class="help-link">Don't have an account?</a>
</x-layouts.auth>