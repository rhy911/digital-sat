<x-layouts.auth title="Teacher account review">
    <div class="w-11/12 text-center space-y-5">
        <h1 class="text-3xl font-bold">Teacher account review</h1>
        @if ($user->teacher_approval_status === 'rejected')
            <p>Your request was not approved.</p>
            @if ($user->teacher_rejection_reason)
                <div class="p-4 border border-red-200 bg-red-50 rounded-xl text-left">
                    {{ $user->teacher_rejection_reason }}</div>
            @endif
        @else
            <p>Your email is verified. An administrator still needs to approve teacher access.</p>
            <div class="p-4 border border-slate-200 rounded-xl">Status: <strong>Pending review</strong></div>
        @endif
        <form action="{{ route('logout') }}" method="POST">@csrf<button class="secondary-btn w-full">Sign out</button>
        </form>
    </div>
</x-layouts.auth>
