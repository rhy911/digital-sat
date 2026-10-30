<x-layouts.auth title="Email verified">
    <div class="flex w-full flex-col items-start gap-5">
        <div class="success-checkmark" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <div class="auth-heading">
            <h1>Email verified</h1>
            <p>Your account is ready. Continue to your saved result or account page.</p>
        </div>

        <a href="{{ $continueUrl ?? route('dashboard') }}" class="primary-btn">Continue</a>
    </div>
</x-layouts.auth>
