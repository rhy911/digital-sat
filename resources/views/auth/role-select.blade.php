<x-layouts.auth>
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-3xl md:text-2xl font-bold text-center m-0 text-slate-900">Digital SAT Practice</h1>
        <p class="text-base text-gray-600 text-center">Build confidence one test at a time.</p>
    </div>

    <div class="flex flex-col gap-2 w-full">
        <a href="{{ route('signin.form', ['role' => 'student']) }}" class="primary-btn no-underline text-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="feather feather-book-open">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
            </svg>
            <span>Continue as Student</span>
        </a>

        <hr class="text-divider" data-content="OR">

        <a href="{{ route('signin.form', ['role' => 'teacher']) }}" class="secondary-btn no-underline text-center block">
            Continue as Teacher / Educator
        </a>
    </div>

    <div class="links text-center flex flex-col gap-3">
        <a href="{{ route('signin.form', ['role' => 'admin']) }}">Administrator?</a>
        <a href="{{ route('signup') }}">Don't have an account? Sign up</a>
    </div>

    <x-auth.encouraging-notice />
</x-layouts.auth>
