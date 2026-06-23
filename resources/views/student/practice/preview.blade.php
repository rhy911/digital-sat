<x-layouts.student :user="$user" header-type="progress" title="Test Preview" :cancel-route="route('home')">
    @push('styles')
        @vite(['resources/css/student/analytics.css'])
    @endpush

    <div class="ds-home ds-preview-page">
        <section class="ds-workspace-head" aria-labelledby="test-preview-title">
            <div class="ds-profile-panel">
                <div>
                    <span class="ds-page-kicker">Preview</span>
                    <h1 id="test-preview-title">Test preview</h1>
                    <p class="ds-workspace-copy">
                        Explore the digital test interface before starting scored practice.
                    </p>
                </div>
            </div>

            <div class="ds-workspace-actions">
                <a href="{{ route('home.practice') }}" class="ds-button ds-button--secondary">
                    Choose practice
                </a>
                <a href="{{ route('engine.session') }}" class="ds-button ds-button--primary">
                    Open preview
                </a>
            </div>
        </section>

        <section class="ds-dashboard-grid" aria-label="Test preview details">
            <article class="ds-card ds-preview-list" aria-labelledby="preview-details-title">
                <div class="ds-card__header">
                    <div>
                        <span class="ds-card-label">Before you begin</span>
                        <h2 id="preview-details-title" class="ds-card-title">What this preview does</h2>
                    </div>
                </div>

                <div class="ds-info-list">
                    <div class="ds-info-item">
                        <span class="ds-info-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                        <div>
                            <h3>Explore the interface</h3>
                            <p>Try sample questions and testing tools without creating a scored result.</p>
                        </div>
                    </div>

                    <div class="ds-info-item">
                        <span class="ds-info-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </span>
                        <div>
                            <h3>Take your time</h3>
                            <p>The preview is untimed. Full practice sessions use the standard SAT timing model.</p>
                        </div>
                    </div>

                    <div class="ds-info-item">
                        <span class="ds-info-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <polyline points="17 11 19 13 23 9"></polyline>
                            </svg>
                        </span>
                        <div>
                            <h3>Check assistive technology</h3>
                            <p>Practice with any accessibility tools you expect to use during a real testing session.
                            </p>
                        </div>
                    </div>

                    <div class="ds-info-item">
                        <span class="ds-info-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
                            </svg>
                        </span>
                        <div>
                            <h3>No device lock</h3>
                            <p>The preview does not block other apps. The full test experience may be stricter.</p>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </div>
</x-layouts.student>
