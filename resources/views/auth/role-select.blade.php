<x-layouts.auth>
    <div class="flex flex-col justify-center items-center gap-1">
        <h1 class="text-3xl md:text-2xl font-bold text-center m-0 text-black">Digital SAT Practice</h1>
        <p class="text-base text-gray-600 text-center">Build confidence one test at a time.</p>
    </div>

    <div class="flex flex-col gap-2 w-11/12">
        <a href="{{ route('signin.form', ['role' => 'student']) }}" class="primary-btn no-underline text-center"
            role="button">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="feather feather-book-open">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
            </svg>
            <span>Continue as Student</span>
        </a>

        <hr class="text-divider" data-content="OR">

        <a href="{{ route('signin.form', ['role' => 'teacher']) }}" class="secondary-btn no-underline text-center block"
            role="button">
            Continue as Teacher / Educator
        </a>
    </div>

    <div class="links text-center flex flex-col gap-3">
        <a href="{{ route('signin.form', ['role' => 'admin']) }}">Administrator?</a>
        <a href="/signup">Don't have an account?</a>
    </div>

    <div class="flex items-center justify-center gap-2 text-sm text-[#94a3b8]">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
            stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
            class="feather feather-check-circle">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>Your answers auto-save during practice tests.</span>
    </div>
</x-layouts.auth>
