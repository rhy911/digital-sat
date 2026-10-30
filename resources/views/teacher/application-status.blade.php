<x-layouts.auth title="Teacher account review">
    <div class="w-full space-y-5">
        <h1 class="text-2xl font-bold text-slate-900">Teacher account review</h1>
        @if ($user->teacher_approval_status === 'rejected')
            <p>Your request was not approved.</p>
            @if ($user->teacher_rejection_reason)
                <div class="auth-notice auth-notice--error">{{ $user->teacher_rejection_reason }}</div>
            @endif
        @else
            <p>Your email is verified. An administrator still needs to approve teacher access.</p>
            <div class="auth-notice auth-notice--info">Status: <strong>Pending review</strong></div>
        @endif
        <form id="app-status-logout-form" action="{{ route('logout') }}" method="POST">@csrf<button class="secondary-btn w-full">Sign out</button>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const logoutForm = document.getElementById('app-status-logout-form');
                if (typeof window.initAjaxLogout === 'function') {
                    window.initAjaxLogout({ formEl: logoutForm, redirectTo: '/signin' });
                }
            });
        </script>
    @endpush
</x-layouts.auth>
