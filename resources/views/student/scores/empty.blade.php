<x-layouts.student :user="$user" header-type="progress" title="My Scores - Digital SAT" :cancel-route="route('home')">
    @push('styles')
        @vite(['resources/css/student/analytics.css'])
    @endpush

    <div class="ds-home">
        <section class="ds-workspace-head" aria-labelledby="scores-title">
            <div class="ds-profile-panel">
                <div>
                    <h1 id="scores-title">My Scores</h1>
                    <p class="ds-workspace-copy">
                        Review your practice test scores, dig deeper into your performance, and learn your strengths
                        before test day.
                    </p>
                </div>
            </div>
        </section>

        <div class="ds-empty">
            <h4>No completed tests yet</h4>
            <p>Complete a full-length practice test to see your score report, domain breakdown, and correct
                answers.</p>
            <div style="margin-top: 1.25rem;">
                <x-ui.button href="{{ route('home.practice') }}" variant="primary" style="display: inline-flex;">
                    Start a Practice Test
                </x-ui.button>
            </div>
        </div>
    </div>
</x-layouts.student>
