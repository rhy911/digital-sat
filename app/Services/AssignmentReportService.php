<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;

class AssignmentReportService
{
    public function __construct(private AssignmentModuleTimingService $assignmentTiming) {}

    public function buildRecipient(Assignment $assignment, AssignmentRecipient $recipient): array
    {
        $attempts = $assignment->attempts()
            ->where('user_id', $recipient->student_id)
            ->with([
                'user',
                'currentModule.section',
                'userAnswers.question.answerChoices',
                'userAnswers.question.sprCorrectAnswers',
                'userAnswers.question.explanation',
            ])
            ->orderBy('attempt_number')
            ->get();
        $this->applyLiveElapsed($attempts);
        $completed = $attempts->where('status', 'completed');
        $best = $completed->sortByDesc('total_score')->first();

        return [
            'recipient' => $recipient->loadMissing('student'),
            'attempts' => $attempts,
            'completed_count' => $completed->count(),
            'best' => $best,
            'in_progress' => $attempts->firstWhere('status', 'in_progress'),
            'late' => $best && $assignment->due_at ? $best->completed_at?->gt($assignment->due_at) : false,
        ];
    }

    public function build(Assignment $assignment): array
    {
        $assignment->load(['classroom', 'test']);

        $activeRecipientIds = $assignment->recipients()->where('status', 'active')->pluck('student_id');
        $assignedCount = $activeRecipientIds->count();

        $bestScores = \App\Models\UserTest::where('assignment_id', $assignment->id)
            ->whereIn('user_id', $activeRecipientIds)
            ->where('status', 'completed')
            ->selectRaw('user_id, MAX(total_score) as best_score, MAX(score_reading_writing) as best_rw, MAX(score_math) as best_math')
            ->groupBy('user_id')
            ->get();

        $completedCount = $bestScores->count();
        $averageScore = $completedCount ? (int) round($bestScores->average('best_score')) : null;
        $averageRw = $completedCount ? (int) round($bestScores->average('best_rw')) : null;
        $averageMath = $completedCount ? (int) round($bestScores->average('best_math')) : null;

        $inProgressCount = \App\Models\UserTest::where('assignment_id', $assignment->id)
            ->whereIn('user_id', $activeRecipientIds)
            ->where('status', 'in_progress')
            ->distinct('user_id')
            ->count('user_id');

        $recipientsPaginator = $assignment->recipients()
            ->with('student')
            ->orderBy('id')
            ->paginate(15);

        $attempts = \App\Models\UserTest::where('assignment_id', $assignment->id)
            ->whereIn('user_id', $recipientsPaginator->pluck('student_id'))
            ->with(['currentModule.section'])
            ->orderBy('attempt_number')
            ->get();
            
        $this->applyLiveElapsed($attempts);
        $attemptsByStudent = $attempts->groupBy('user_id');

        $rows = $recipientsPaginator->map(function ($recipient) use ($attemptsByStudent, $assignment) {
            $studentAttempts = $attemptsByStudent->get($recipient->student_id, collect());
            $completed = $studentAttempts->where('status', 'completed');
            $best = $completed->sortByDesc('total_score')->first();
            return [
                'recipient' => $recipient,
                'attempts' => $studentAttempts,
                'completed_count' => $completed->count(),
                'best' => $best,
                'in_progress' => $studentAttempts->firstWhere('status', 'in_progress'),
                'late' => $best && $assignment->due_at ? $best->completed_at?->gt($assignment->due_at) : false,
            ];
        });

        return [
            'paginator' => $recipientsPaginator,
            'rows' => $rows,
            'metrics' => [
                'assigned' => $assignedCount,
                'completed' => $completedCount,
                'in_progress' => $inProgressCount,
                'average_score' => $averageScore,
                'average_rw' => $averageRw,
                'average_math' => $averageMath,
            ],
        ];
    }

    private function applyLiveElapsed($attempts): void
    {
        $attempts
            ->where('status', 'in_progress')
            ->filter(fn ($attempt) => $attempt->assignment_id && $attempt->currentModule && $attempt->current_module_started_at)
            ->each(function ($attempt) {
                $timing = $this->assignmentTiming->timing($attempt, $attempt->currentModule);
                $attempt->current_module_elapsed_seconds = $timing['elapsed_seconds'];
            });
    }
}
