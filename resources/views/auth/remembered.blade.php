<x-layouts.auth title="Welcome Back">
    <!-- Back Link -->
    <x-auth.back-link />

    <!-- Title -->
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-center m-0 text-black">Welcome Back!</h1>
        <p class="text-sm sm:text-base text-gray-600 text-center">You are currently signed in.</p>
    </div>

    <!-- Content Block -->
    <div class="w-11/12 flex flex-col gap-6 items-center">
        <!-- Account Info Card -->
        <div
            class="w-full p-5 rounded-xl border-[1.5px] border-slate-200 bg-slate-50/50 flex flex-col gap-2 text-center">
            <div class="text-[10px] text-gray-500 font-extrabold uppercase tracking-wider">Signed In Account</div>
            <div class="text-lg font-bold text-[#1e293b] leading-tight">{{ $user->username ?? $user->email }}</div>
            <div
                class="inline-flex items-center justify-center gap-1.5 self-center mt-2 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider text-[#4361EE] bg-[#f0f3ff] border-[1.5px] border-[#4361EE]/20">
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
                    class="secondary-btn w-full font-bold text-[10px] uppercase tracking-wider transition-all cursor-pointer">
                    Sign Out & Use Different Account
                </button>
            </form>
        </div>
    </div>

    <!-- Encouragement Info -->
    <div class="flex items-center justify-center gap-2 text-sm text-[#94a3b8]">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
            class="feather feather-check-circle">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>Your session is secured and verified.</span>
    </div>

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
