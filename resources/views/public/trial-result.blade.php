<x-layouts.landing title="Your DFET SAT practice result">
    <section class="bg-[#F6F8FC] px-5 py-14 sm:px-6">
        <div class="mx-auto max-w-4xl">
            <a href="{{ route('public-trial.index') }}" class="font-semibold text-blue-700 underline">← Try another experience</a>
            <header class="mt-8 rounded-2xl bg-[#10234A] p-7 text-white sm:p-10">
                <p class="font-bold uppercase tracking-wide text-[#F6B93B]">Practice result</p>
                <h1 class="mt-2 text-3xl font-extrabold">{{ $userTest->test->title }}</h1>
                <p class="mt-3 text-blue-100">This is practice performance, not an official College Board score.</p>
                @if (auth()->user()?->is_guest && $userTest->guest_trial_expires_at)
                    <p class="mt-2 text-sm text-blue-100">This guest result is available until {{ $userTest->guest_trial_expires_at->format('M j, Y g:i A') }} unless you save it to an account.</p>
                @endif
                <div class="mt-7 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-white/10 p-4"><span class="block text-sm text-blue-100">Correct</span><strong class="mt-1 block text-3xl tabular-nums">{{ $stats['total']['correct'] }} / {{ $stats['total']['questions'] }}</strong></div>
                    @if ($scorePresentation['type'] === 'full' && $scorePresentation['score'] !== null)
                        <div class="rounded-xl bg-white/10 p-4"><span class="block text-sm text-blue-100">Estimated total score</span><strong class="mt-1 block text-3xl tabular-nums">{{ $scorePresentation['score'] }}</strong></div>
                        <div class="rounded-xl bg-white/10 p-4"><span class="block text-sm text-blue-100">Estimated range</span><strong class="mt-1 block text-2xl tabular-nums">{{ $scorePresentation['rangeFormatted'] ?? 'Not available' }}</strong></div>
                    @else
                        @if (($scorePresentation['type'] ?? '') === 'raw_section' && ($scorePresentation['sectionType'] ?? '') === 'rw')
                            <div class="rounded-xl bg-white/10 p-4"><span class="block text-sm text-blue-100">Reading and Writing performance</span><strong class="mt-1 block text-2xl tabular-nums">{{ $stats['sections']['reading_and_writing']['correct'] }} / {{ $stats['sections']['reading_and_writing']['total'] }}</strong></div>
                        @elseif (($scorePresentation['type'] ?? '') === 'raw_section')
                            <div class="rounded-xl bg-white/10 p-4"><span class="block text-sm text-blue-100">Math performance</span><strong class="mt-1 block text-2xl tabular-nums">{{ $stats['sections']['math']['correct'] }} / {{ $stats['sections']['math']['total'] }}</strong></div>
                        @else
                            <div class="rounded-xl bg-white/10 p-4"><span class="block text-sm text-blue-100">Reading and Writing</span><strong class="mt-1 block text-2xl tabular-nums">{{ $stats['sections']['reading_and_writing']['correct'] }} / {{ $stats['sections']['reading_and_writing']['total'] }}</strong></div>
                            <div class="rounded-xl bg-white/10 p-4"><span class="block text-sm text-blue-100">Math</span><strong class="mt-1 block text-2xl tabular-nums">{{ $stats['sections']['math']['correct'] }} / {{ $stats['sections']['math']['total'] }}</strong></div>
                        @endif
                    @endif
                </div>
                <p class="mt-5 text-sm text-blue-100">{{ $scorePresentation['scoreDisclosure'] ?? 'Practice performance based on your submitted answers.' }} {{ $scorePresentation['unofficialNotice'] ?? 'Not an official College Board score.' }}</p>
            </header>

            @if ($sampleReview)
                <article class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 question-content" data-math-container>
                    <p class="text-sm font-bold uppercase tracking-wide text-blue-700">Sample answer review</p>
                    <h2 class="mt-2 text-xl font-bold text-[#10234A]">One question from your test</h2>
                    @if ($sampleReview['passage'])
                        <div class="prose prose-slate mt-5 max-w-none rounded-lg border border-slate-200 bg-slate-50 p-4 text-slate-800 leading-relaxed">{!! $sampleReview['passage'] !!}</div>
                    @endif
                    <div class="prose prose-slate mt-5 max-w-none text-slate-900 leading-relaxed">{!! $sampleReview['stem'] !!}</div>
                    @if (! empty($sampleReview['choices']))
                        <div class="mt-5 space-y-2">
                            @foreach ($sampleReview['choices'] as $choice)
                                @php
                                    $isStudentChoice = $sampleReview['selected_answer'] === $choice['label'];
                                    $isCorrectChoice = ! empty($choice['is_correct']);
                                @endphp
                                <div class="flex items-start gap-3 rounded-lg border p-3 text-sm {{ $isCorrectChoice ? 'border-emerald-300 bg-emerald-50 text-emerald-950 font-medium' : ($isStudentChoice ? 'border-rose-300 bg-rose-50 text-rose-950' : 'border-slate-200 bg-white text-slate-800') }}">
                                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-bold {{ $isCorrectChoice ? 'bg-emerald-600 text-white' : ($isStudentChoice ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-700') }}">{{ $choice['label'] }}</span>
                                    <div class="prose prose-slate max-w-none flex-1 leading-normal">{!! $choice['content'] !!}</div>
                                    @if ($isCorrectChoice)
                                        <span class="text-xs font-bold text-emerald-700 uppercase tracking-wide shrink-0">Correct</span>
                                    @elseif ($isStudentChoice)
                                        <span class="text-xs font-bold text-rose-700 uppercase tracking-wide shrink-0">Your answer</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <p class="mt-4 text-sm text-slate-700">
                        Your answer: <strong>{{ ($sampleReview['selected_answer'] !== null && $sampleReview['selected_answer'] !== '') ? $sampleReview['selected_answer'] : 'No answer' }}</strong>
                        &middot;
                        Correct answer: <strong>{{ $sampleReview['correct_answer'] }}</strong>
                    </p>
                    @if ($sampleReview['explanation'])
                        <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-700 border border-slate-200">
                            <p class="font-bold text-slate-900 mb-1">Explanation</p>
                            <div class="prose prose-slate max-w-none leading-relaxed">{!! $sampleReview['explanation'] !!}</div>
                        </div>
                    @endif
                </article>
            @else
                <p class="mt-6 rounded-xl border border-slate-200 bg-white p-5 text-slate-600">A sample question review is not available for this attempt. Create an account to open the full report when the saved answer data is available.</p>
            @endif

            <section class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-[#10234A]">Keep your result and unlock the full review.</h2>
                <p class="mt-3 leading-7 text-slate-700">Create an account to save this test, review every answer, and see your skill analysis and mistake patterns.</p>
                <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                    <form method="POST" action="{{ route('public-trial.account', [$userTest, 'signup']) }}" data-trial-transfer>@csrf<x-ui.button type="submit">Create a free account</x-ui.button></form>
                    <form method="POST" action="{{ route('public-trial.account', [$userTest, 'signin']) }}" data-trial-transfer>@csrf<x-ui.button type="submit" variant="secondary">Sign in to save this result</x-ui.button></form>
                </div>
            </section>
        </div>
    </section>
    @push('scripts')
        @vite(['resources/js/question-renderer.js'])
        <script>
            document.querySelectorAll('[data-trial-transfer]').forEach((form) => form.addEventListener('submit', () => {
                const prefix = `sat_state_{{ $userTest->id }}_`;
                for (const key of Object.keys(sessionStorage)) if (key.startsWith(prefix)) sessionStorage.removeItem(key);
            }));
        </script>
    @endpush
</x-layouts.landing>
