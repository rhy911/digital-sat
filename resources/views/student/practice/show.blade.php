<x-layouts.student :user="$user" header-type="progress" title="My Practice - Digital SAT" :cancel-route="route('home')">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/student/practice.css'])
    @endpush

    <div class="ds-home ds-practice-page">
        <!-- Header Section -->
        <section class="ds-workspace-head" aria-labelledby="my-practice-title">
            <div class="ds-profile-panel">
                <div>
                    <h1 id="my-practice-title">My Practice</h1>
                    <p class="ds-workspace-copy">
                        Review your practice test scores, dig deeper into your performance, and learn your strengths
                        before test day.
                    </p>
                </div>
            </div>

            <div class="ds-workspace-actions">
                <a href="{{ route('home') }}" class="ds-button ds-button--secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                        stroke="currentColor" class="w-4 h-4 mr-2" aria-hidden="true"
                        style="margin-right: 0.5rem; display: inline-block; width: 1rem; height: 1rem; vertical-align: text-bottom;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to progress
                </a>
            </div>
        </section>

        <!-- Main Cards / Lists Section -->
        <section class="ds-dashboard-grid" aria-labelledby="practice-library-title">
            <article class="ds-card ds-practice-library">
                <div class="ds-card__header">
                    <div>
                        <h2 id="practice-library-title" class="ds-card-title">Completed Practice Tests</h2>
                    </div>
                    @if ($completedTests->isNotEmpty())
                        <span class="ds-card-note">{{ $completedTests->count() }}
                            {{ \Illuminate\Support\Str::plural('test', $completedTests->count()) }}</span>
                    @endif
                </div>

                @if ($completedTests->isNotEmpty())
                    <div class="ds-practice-card-grid">
                        @foreach ($completedTests as $test)
                            <x-student.cards.practice-test-card :test="$test" />
                        @endforeach
                    </div>
                @else
                    <div class="ds-empty ds-empty--compact">
                        <h4>No completed tests yet</h4>
                        <p>Complete a full-length practice test to see your score report, domain breakdown, and correct
                            answers.</p>
                        <div style="margin-top: 1.25rem;">
                            <a href="{{ route('home.practice') }}" class="ds-button ds-button--primary"
                                style="display: inline-flex;">
                                Start a Practice Test
                            </a>
                        </div>
                    </div>
                @endif
            </article>
        </section>
    </div>

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof window.initPracticeDashboardPage === 'function') {
                    window.initPracticeDashboardPage();
                }
            });
        </script>
    </x-slot>
</x-layouts.student>
