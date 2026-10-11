<div>
    <div class="assignments-grid transition-opacity duration-150" wire:loading.class="opacity-40 pointer-events-none">
        @forelse($assignmentsPage as $assignment)
            @php
                $isOverdue = $assignment->due_at && $assignment->due_at->isPast() && $assignment->status !== 'completed';
                $isDueSoon = $assignment->due_at && !$assignment->due_at->isPast() && $assignment->due_at->diffInHours(now()) <= 48 && $assignment->status !== 'completed';
            @endphp
            <a href="{{ route('teacher.assignments.show', $assignment) }}" class="index-card text-inherit no-underline">
                <div class="card-due {{ $isOverdue ? 'is-overdue' : ($isDueSoon ? 'is-due-soon' : '') }}">
                    <x-ui.icon name="clock" class="w-3.5 h-3.5 inline text-slate-400" />
                    <span>{{ $assignment->due_at ? $assignment->due_at->format('D, M j · g:i A') : __('classroom.no_deadline') }}</span>
                </div>
                <div class="card-title">{{ $assignment->title }}</div>
                <div class="card-sub truncate" title="{{ $assignment->test->title }}{{ $assignment->sectionSuffix() }}">
                    {{ $assignment->test->title }}{{ $assignment->sectionSuffix() }}
                </div>

                <div class="flex items-center gap-2 flex-wrap my-2">
                    <!-- Clock Mode Badge -->
                    <span class="clock-mode-pill {{ $assignment->clock_mode === 'paused' ? 'practice' : 'strict' }} inline-flex items-center gap-1.5">
                        <x-ui.icon :name="$assignment->clock_mode === 'paused' ? 'play' : 'clock'" class="w-3 h-3" />
                        <span>{{ $assignment->clock_mode === 'paused' ? 'Practice Homework' : 'Timed Exam' }}</span>
                    </span>
                    <!-- Audience Badge -->
                    <span class="audience-pill inline-flex items-center gap-1.5">
                        <x-ui.icon :name="$assignment->target_audience === 'specific' ? 'target' : 'people'" class="w-3 h-3 text-slate-500" />
                        <span>{{ $assignment->target_audience === 'specific' ? 'Targeted' : 'Whole Class' }}</span>
                    </span>
                    <span class="type-tag">{{ $assignment->sectionShort() }}</span>
                    <x-ui.status-badge :status="$assignment->status === 'published' ? 'brand' : ($assignment->status === 'completed' ? 'success' : 'neutral')">
                        {{ ucfirst($assignment->status) }}
                    </x-ui.status-badge>
                </div>

                <div class="card-meta">
                    <span>{{ trans_choice('classroom.attempts_count', $assignment->attempt_limit, ['count' => $assignment->attempt_limit]) }}</span>
                    <span class="font-bold text-xs text-blue-700">View Submissions →</span>
                </div>
            </a>
        @empty
            <div class="empty-grid-cell">
                {{ __('classroom.no_assignments_teacher') }}
            </div>
        @endforelse
    </div>

    @if ($assignmentsPage->hasPages())
        <div class="mt-6">
            {{ $assignmentsPage->links() }}
        </div>
    @endif
</div>
