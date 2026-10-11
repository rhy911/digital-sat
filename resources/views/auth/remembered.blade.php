<x-layouts.auth title="Welcome Back">
    <!-- Back Link -->
    <x-auth.back-link />

    <!-- Title -->
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-slate-900">Welcome Back!</h1>
        <p class="text-sm sm:text-base text-gray-600 text-center">You are currently signed in.</p>
    </div>

    <!-- Content Block -->
    <div class="w-full flex flex-col gap-6 items-center">
        <!-- Account Info Card -->
        <div
            class="w-full p-5 rounded-xl border-[1.5px] border-slate-200 bg-slate-50/50 flex flex-col gap-2 text-center">
            <div class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Signed In Account</div>
            <div class="text-lg font-bold text-slate-900 leading-tight">{{ $user->username ?? $user->email }}</div>
            <div
                class="inline-flex items-center justify-center gap-1.5 self-center mt-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider text-brand bg-[var(--color-brand-soft)] border-[1.5px] border-brand/20">
                <span>{{ $user->role ?? 'student' }}</span>
            </div>
        </div>

        <div class="w-full flex flex-col gap-3">
            <a href="{{ route('home') }}"
                class="primary-btn text-center flex items-center justify-center font-bold no-underline w-full">
                Go to Dashboard
            </a>

            <!-- Sign Out Form -->
            <form id="logoutForm" action="{{ route('logout') }}" method="POST" class="w-full m-0">
                @csrf
                <button type="submit"
                    class="secondary-btn w-full font-semibold text-sm transition-all cursor-pointer">
                    Sign out & use different account
                </button>
            </form>
        </div>
    </div>

    <x-auth.encouraging-notice message="Your session is secured and verified." />

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const logoutForm = document.getElementById('logoutForm');
                if (logoutForm && typeof window.initAjaxLogout === 'function') {
                    window.initAjaxLogout({
                        formEl: logoutForm,
                        redirectTo: '/signin'
                    });
                }
            });
        </script>
    @endpush
</x-layouts.auth>
