@php
    $role = request()->query('role');
    if ($role === 'teacher') {
        $titleText = 'Sign in as teacher';
        $subtitleText = 'Continue tracking student progress.';
    } elseif ($role === 'admin') {
        $titleText = 'Administrator sign in';
        $subtitleText = 'System management and configuration.';
    } else {
        $titleText = 'Sign in as student';
            $subtitleText = "Ready for today's practice?";
    }
@endphp

<x-layouts.auth title="{{ $titleText }}">
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new AuthForm('signinForm', {
                    onSuccess: (data) => {
                        if (data.token) {
                            localStorage.setItem('api_token', data.token);
                        }
                        window.location.href = data.redirect || '{{ route('home') }}';
                    }
                });
            });
        </script>
    @endpush
    <!-- Back -->
    <x-auth.back-link />

    <!-- Title -->
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-slate-900">{{ $titleText }}</h1>
        <p class="text-sm sm:text-base text-gray-600 text-center">{{ $subtitleText }}</p>
    </div>

    <!-- Form -->
    <form class="w-full" id="signinForm" action="{{ route('signin') }}" method="POST" novalidate>
        @csrf
        <div class="auth-form-group">
            <label for="role" class="form-label">Sign in as</label>
            <select class="form-control" id="role" name="role">
                @if ($role === 'admin')
                    <option value="admin" selected>Administrator</option>
                @else
                    <option value="student" @selected($role !== 'teacher')>Student</option>
                    <option value="teacher" @selected($role === 'teacher')>Teacher</option>
                @endif
            </select>
        </div>

        <div class="auth-form-group">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
        </div>

        <div class="auth-form-group">
            <x-auth.password-field label="Password" input-id="password" name="password" autocomplete="current-password"
                toggle-id="passwordToggle" target-id="password" required />
        </div>

        <div class="auth-form-links flex justify-between items-center w-full">
            <label class="flex items-center gap-2 cursor-pointer text-sm font-semibold text-slate-600 select-none">
                <input type="checkbox" name="remember" id="remember" value="1"
                    class="w-4 h-4 rounded border-slate-300 accent-brand cursor-pointer">
                <span>Remember me</span>
            </label>
            <a href="{{ route('forgot') }}" class="text-sm font-semibold text-brand">Forgot password?</a>
        </div>

        <x-auth.alerts />

        <button type="submit" class="submit-btn" id="submitBtn" data-processing-text="Processing...">Sign
            in</button>
    </form>

    <!-- Help -->
    @if ($role !== 'admin')
        <div class="links text-center">
            <a href="{{ route('signup', ['role' => $role ?? 'student']) }}">Don't have an account? Sign up</a>
        </div>
    @endif

    <x-auth.encouraging-notice class="mt-2" />
</x-layouts.auth>
