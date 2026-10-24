@php
    $viewer = $authUser ?? auth()->user();
    $isTeacherView = $viewer && $viewer->role !== 'student';
    $userInitials = $viewer->initials ?? 'U';
@endphp

<x-layouts.student :user="$viewer" title="Score Report Unavailable — {{ $userTest->test->title }}" header-type="none" ui="product" :body-class="$isTeacherView ? 'antialiased teacher-workspace' : 'antialiased'">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/scores.css'])
    @endpush

    <div class="app-shell">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($viewer)" :avatar-label="$userInitials"
            :items="\App\Support\NavRail::forUser($viewer, $isTeacherView ? 'reports' : 'scores')" />

        <div class="ledger-pane no-corkboard">
            <div class="binder-panel" style="max-width: 800px; margin: 40px auto; text-align: center; padding: 48px 32px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--ds-warning-soft, #fef3c7); color: var(--ds-warning-strong, #92400e); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--ds-text-primary, #0f172a); margin-bottom: 8px;">Score Report Unavailable</h1>
                <p style="font-size: 0.95rem; color: var(--ds-text-secondary, #64748b); margin-bottom: 24px;">
                    This practice attempt for <strong>{{ $userTest->test->title }}</strong> is currently <strong>{{ str_replace('_', ' ', $userTest->status) }}</strong>.
                </p>
                <div style="background: var(--ds-surface-soft, #f8fafc); border: 1px solid var(--ds-border-subtle, #e2e8f0); border-radius: 12px; padding: 16px 20px; text-align: left; margin-bottom: 28px; font-size: 0.875rem; color: var(--ds-text-primary, #0f172a);">
                    <div>Student: <strong>{{ $user->name ?? 'Student' }}</strong> ({{ $user->email ?? '—' }})</div>
                    @if ($userTest->assignment)
                        <div style="margin-top: 4px;">Classroom: <strong>{{ $userTest->assignment->classroom?->name ?: ($userTest->assignment->title ?: 'Assignment') }}</strong></div>
                    @elseif ($userTest->examSession)
                        <div style="margin-top: 4px;">Session: <strong>{{ $userTest->examSession->title ?: 'Exam Session' }}</strong></div>
                    @endif
                    <div style="margin-top: 4px;">Status: <span style="font-weight: 600; color: var(--ds-warning-strong, #92400e);">{{ ucfirst(str_replace('_', ' ', $userTest->status)) }}</span></div>
                    <div style="margin-top: 8px; color: var(--ds-text-secondary, #64748b); font-size: 0.8rem;">
                        @if ($userTest->status === 'abandoned')
                            This attempt was abandoned before completion. Score reports are only available for completed attempts.
                        @else
                            Reports, diagnostics, and answer keys are strictly revealed only after the student completes the entire attempt.
                        @endif
                    </div>
                </div>
                <div>
                    @if ($userTest->assignment)
                        <a href="{{ route('teacher.assignments.students.show', [$userTest->assignment, $userTest->user_id]) }}"
                           class="sd-action-btn primary">
                            Return to Student Attempt
                        </a>
                    @elseif ($userTest->examSession)
                        <a href="{{ route('teacher.exam-sessions.show', $userTest->examSession) }}"
                           class="sd-action-btn primary">
                            Return to Exam Session
                        </a>
                    @else
                        <button type="button" onclick="history.back()"
                                class="sd-action-btn primary"
                                style="cursor: pointer; border: none;">
                            Go Back
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.student>
