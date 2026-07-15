<x-layouts.student :user="$user" header-type="progress" :cancel-route="route('home')">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
        <style>
            .merge-workspace {
                max-width: 800px;
                margin: 2rem auto;
                padding: 0 1.5rem;
            }
            .merge-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 1.5rem;
                margin-bottom: 1.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }
            .merge-card h3 {
                font-size: 1.25rem;
                font-weight: 600;
                color: #111827;
                margin-bottom: 0.5rem;
            }
            .merge-card p {
                font-size: 0.875rem;
                color: #4b5563;
                margin-bottom: 1.25rem;
            }
            .merge-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
                margin-bottom: 1.25rem;
            }
            @media (max-width: 640px) {
                .merge-grid {
                    grid-template-columns: 1fr;
                }
            }
            .select-group {
                display: flex;
                flex-direction: column;
                gap: 0.375rem;
            }
            .select-group label {
                font-size: 0.875rem;
                font-weight: 500;
                color: #374151;
            }
            .select-group select {
                width: 100%;
                padding: 0.625rem;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                background-color: #f9fafb;
                color: #1f2937;
                font-size: 0.875rem;
                transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            }
            .select-group select:focus {
                outline: none;
                border-color: #2563eb;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            }
            .empty-state {
                text-align: center;
                padding: 3rem 1.5rem;
                background: #f9fafb;
                border: 1px dashed #d1d5db;
                border-radius: 12px;
            }
            .empty-state h4 {
                font-size: 1.125rem;
                font-weight: 600;
                color: #374151;
                margin-bottom: 0.5rem;
            }
            .empty-state p {
                font-size: 0.875rem;
                color: #6b7280;
                max-width: 500px;
                margin: 0 auto;
            }
        </style>
    @endpush

    <div class="merge-workspace">
        <div style="margin-bottom: 2rem;">
            <a class="back-link" wire:navigate href="{{ route('home') }}">Back to Progress</a>
            <h1 style="font-size: 1.75rem; font-weight: 700; color: #111827; margin-top: 0.5rem;">Consolidate Section Scores</h1>
            <p style="color: #4b5563; margin-top: 0.25rem;">Combine completed section-only attempts of the same test to view a full SAT Score Report.</p>
        </div>

        @if ($errors->any())
            <div class="class-alert class-alert--error" style="margin-bottom: 1.5rem;">
                {{ $errors->first() }}
            </div>
        @endif

        @if($mergeableTests->isNotEmpty())
            <h2 style="font-size: 1.125rem; font-weight: 600; color: #374151; margin-bottom: 1rem;">Available Tests for Merging</h2>
            @foreach($mergeableTests as $item)
                <div class="merge-card">
                    <h3>{{ $item['test']->title }}</h3>
                    <p>{{ $item['test']->description ?: 'Complete your SAT score overview by consolidating your separate Reading & Writing and Math section attempts.' }}</p>

                    <form method="POST" action="{{ route('student.scores.merge.store') }}">
                        @csrf
                        <div class="merge-grid">
                            <div class="select-group">
                                <label for="rw-select-{{ $item['test']->id }}">Reading & Writing Attempt</label>
                                <select id="rw-select-{{ $item['test']->id }}" name="rw_attempt_ulid" required>
                                    @foreach($item['rw_attempts'] as $attempt)
                                        <option value="{{ $attempt->ulid }}">
                                            Attempt {{ $attempt->attempt_number }} - Score: {{ $attempt->score_reading_writing }} ({{ $attempt->completed_at?->format('M j, Y') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="select-group">
                                <label for="math-select-{{ $item['test']->id }}">Math Attempt</label>
                                <select id="math-select-{{ $item['test']->id }}" name="math_attempt_ulid" required>
                                    @foreach($item['math_attempts'] as $attempt)
                                        <option value="{{ $attempt->ulid }}">
                                            Attempt {{ $attempt->attempt_number }} - Score: {{ $attempt->score_math }} ({{ $attempt->completed_at?->format('M j, Y') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="class-button class-button--primary">
                                Generate Consolidated Report
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        @endif

        @if($incompleteTests->isNotEmpty())
            <h2 style="font-size: 1.125rem; font-weight: 600; color: #374151; margin-top: 2.5rem; margin-bottom: 1rem;">Incomplete Sections</h2>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach($incompleteTests as $item)
                    <div class="merge-card" style="background-color: #f9fafb; opacity: 0.85;">
                        <h3 style="font-size: 1.125rem; color: #4b5563;">{{ $item['test']->title }}</h3>
                        <p style="margin-bottom: 0;">
                            To merge scores, you need both sections completed. You have currently finished:
                            <span style="font-weight: 600; color: #111827;">{{ $item['rw_count'] }} RW</span> and
                            <span style="font-weight: 600; color: #111827;">{{ $item['math_count'] }} Math</span> attempts.
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        @if($mergeableTests->isEmpty() && $incompleteTests->isEmpty())
            <div class="empty-state">
                <h4>No section attempts found</h4>
                <p>Complete section-only assignments or practice tests first. Once you have finished both Reading & Writing and Math sections of the same test, you can merge them here.</p>
            </div>
        @endif
    </div>
</x-layouts.student>
