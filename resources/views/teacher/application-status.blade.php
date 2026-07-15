<x-layouts.auth title="Teacher account review">
    <div class="w-11/12 text-center space-y-5">
        <h1 class="text-3xl font-bold">Teacher account review</h1>
        @if ($user->teacher_approval_status === 'rejected')
            <p>Your request was not approved.</p>
            @if ($user->teacher_rejection_reason)
                <div class="p-4 border border-rose-200 bg-rose-50 rounded-xl text-left">
                    {{ $user->teacher_rejection_reason }}</div>
            @endif
        @else
            <p>Your email is verified. An administrator still needs to approve teacher access.</p>
            <div class="p-4 border border-slate-200 rounded-xl">Status: <strong>Pending review</strong></div>
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
