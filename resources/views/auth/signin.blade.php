@php
    $role = request()->query('role');
    if ($role === 'teacher') {
        $titleText = "Sign In as Teacher";
        $subtitleText = "Continue tracking student progress.";
    } elseif ($role === 'admin') {
        $titleText = "Administrator Sign In";
        $subtitleText = "System management and configuration.";
    } else {
        $titleText = "Sign In as Student";
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
                        window.location.href = '{{ route('home') }}';
                    }
                });
            });
        </script>
    @endpush
    <!-- Back -->
    <x-auth.back-link href="/" />

    <!-- Title -->
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-3xl font-bold text-center m-0 text-black">{{ $titleText }}</h1>
        <p class="text-base text-gray-600 text-center">{{ $subtitleText }}</p>
    </div>

    <!-- Form -->
    <form class="w-11/12" id="signinForm" action="{{ route('signin') }}" method="POST" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email" autocomplete="email">
        </div>

        <div class="mb-2">
            <x-auth.password-field label="Password" input-id="password" name="password" autocomplete="current-password"
                toggle-id="passwordToggle" target-id="password" />
        </div>

        <div class="links mb-6 flex justify-between items-center w-full">
            <label class="flex items-center gap-2 cursor-pointer text-sm font-semibold text-slate-500 select-none">
                <input type="checkbox" name="remember" id="remember"
                    class="w-4 h-4 rounded border-slate-300 accent-[#4361EE] cursor-pointer">
                <span>Remember me</span>
            </label>
            <a href="{{ route('forgot') }}">Forgot password?</a>
        </div>

        <x-auth.alerts />

        <button type="submit" class="submit-btn" id="submitBtn" data-processing-text="Processing..."
            disabled>Submit</button>
    </form>

    <!-- Help -->
    @if ($role !== 'admin')
        <div class="links text-center">
            <a href="/signup?role={{ $role ?? 'student' }}">Don't have an account?</a>
        </div>
    @endif

    <div class="flex items-center justify-center gap-2 text-sm text-[#94a3b8]">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981"
            stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="feather feather-check-circle">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>Your progress is automatically saved.</span>
    </div>
</x-layouts.auth>