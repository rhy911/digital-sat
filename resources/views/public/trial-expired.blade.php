<x-layouts.landing title="Practice trial expired — DigiSAT">
    <section class="bg-[#F6F8FC] px-5 py-20 sm:px-6">
        <div class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-12">
            <p class="font-bold uppercase tracking-wide text-blue-700">Practice trial ended</p>
            <h1 class="mt-3 text-3xl font-extrabold text-[#10234A]">This guest trial is no longer available.</h1>
            <p class="mt-4 leading-7 text-slate-600">Guest practice is kept for up to 30 minutes without activity and 24 hours after it starts. You can begin a new practice experience or sign in to view results already saved to an account.</p>
            <div class="mt-7 flex flex-wrap gap-3">
                <x-ui.button href="{{ route('public-trial.index') }}">Choose another practice</x-ui.button>
                <x-ui.button href="{{ route('signin.form') }}" variant="secondary">Sign in</x-ui.button>
            </div>
        </div>
    </section>
    @if ($attemptId)
        <script>
            for (const storage of [sessionStorage, localStorage]) {
                for (const key of Object.keys(storage)) {
                    if (key.startsWith(`sat_state_{{ $attemptId }}_`)) storage.removeItem(key);
                }
            }
        </script>
    @endif
</x-layouts.landing>
