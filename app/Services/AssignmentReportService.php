<?php

namespace App\Services;

use App\Models\Assignment;

class AssignmentReportService
{
    public function build(Assignment $assignment): array
    {
        $assignment->load([
            'classroom', 'test', 'recipients.student',
            'attempts' => fn ($query) => $query->with(['user', 'userAnswers.question.answerChoices', 'userAnswers.question.sprCorrectAnswers', 'userAnswers.question.explanation'])->orderBy('attempt_number'),
        ]);

        $attemptsByStudent = $assignment->attempts->groupBy('user_id');
        $rows = $assignment->recipients->map(function ($recipient) use ($attemptsByStudent, $assignment) {
            $attempts = $attemptsByStudent->get($recipient->student_id, collect());
            $completed = $attempts->where('status', 'completed');
            $best = $completed->sortByDesc('total_score')->first();
            return [
                'recipient' => $recipient,
                'attempts' => $attempts,
                'completed_count' => $completed->count(),
                'best' => $best,
                'in_progress' => $attempts->firstWhere('status', 'in_progress'),
                'late' => $best && $assignment->due_at ? $best->completed_at?->gt($assignment->due_at) : false,
            ];
        });

        $activeRows = $rows->where(fn ($row) => $row['recipient']->status === 'active');
        $scores = $activeRows->pluck('best.total_score')->filter(fn ($score) => $score !== null);
        $rwScores = $activeRows->pluck('best.score_reading_writing')->filter(fn ($score) => $score !== null);
        $mathScores = $activeRows->pluck('best.score_math')->filter(fn ($score) => $score !== null);

        return [
            'rows' => $rows,
            'metrics' => [
                'assigned' => $activeRows->count(),
                'completed' => $activeRows->where(fn ($row) => $row['best'] !== null)->count(),
                'in_progress' => $activeRows->where(fn ($row) => $row['in_progress'] !== null)->count(),
                'average_score' => $scores->isEmpty() ? null : (int) round($scores->average()),
                'average_rw' => $rwScores->isEmpty() ? null : (int) round($rwScores->average()),
                'average_math' => $mathScores->isEmpty() ? null : (int) round($mathScores->average()),
            ],
        ];
    }
}
