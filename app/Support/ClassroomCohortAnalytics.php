<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Support\Facades\DB;

class ClassroomCohortAnalytics
{
    /**
     * Compute cohort-wide SAT metrics and domain mastery for a classroom.
     *
     * @return array{
     *     composite_avg: ?int,
     *     rw_avg: ?int,
     *     math_avg: ?int,
     *     completed_tests_count: int,
     *     domain_proficiencies: array<string, array{name: string, accuracy_pct: int, correct: int, total: int}>
     * }
     */
    public static function forClassroom(Classroom $classroom): array
    {
        $activeStudentIds = $classroom->activeMemberships()->pluck('student_id');
        $assignmentIds = $classroom->assignments()->pluck('id');

        if ($activeStudentIds->isEmpty() || $assignmentIds->isEmpty()) {
            return self::emptyStats();
        }

        $completedAttempts = UserTest::whereIn('user_id', $activeStudentIds)
            ->whereIn('assignment_id', $assignmentIds)
            ->where('status', 'completed')
            ->get(['id', 'user_id', 'total_score', 'score_reading_writing', 'score_math']);

        if ($completedAttempts->isEmpty()) {
            return self::emptyStats();
        }

        $scores = $completedAttempts->pluck('total_score')->filter(fn ($s) => is_numeric($s) && $s > 0);
        $rwScores = $completedAttempts->pluck('score_reading_writing')->filter(fn ($s) => is_numeric($s) && $s > 0);
        $mathScores = $completedAttempts->pluck('score_math')->filter(fn ($s) => is_numeric($s) && $s > 0);

        $attemptIds = $completedAttempts->pluck('id');
        $domainAccuracies = self::calculateDomainAccuracies($attemptIds);

        return [
            'composite_avg' => $scores->isNotEmpty() ? (int) round($scores->average()) : null,
            'rw_avg' => $rwScores->isNotEmpty() ? (int) round($rwScores->average()) : null,
            'math_avg' => $mathScores->isNotEmpty() ? (int) round($mathScores->average()) : null,
            'completed_tests_count' => $completedAttempts->count(),
            'domain_proficiencies' => $domainAccuracies,
        ];
    }

    /**
     * Calculate question accuracy across standard SAT skill domains.
     *
     * @param \Illuminate\Support\Collection<int, int> $attemptIds
     * @return array<string, array{name: string, accuracy_pct: int, correct: int, total: int}>
     */
    private static function calculateDomainAccuracies($attemptIds): array
    {
        if ($attemptIds->isEmpty()) {
            return [];
        }

        $domainStats = UserTestAnswer::whereIn('user_test_id', $attemptIds)
            ->join('questions', 'user_test_answers.question_id', '=', 'questions.id')
            ->whereNotNull('questions.skill_domain')
            ->selectRaw('questions.skill_domain, COUNT(*) as total_answers, SUM(CASE WHEN user_test_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers')
            ->groupBy('questions.skill_domain')
            ->get();

        $results = [];
        foreach ($domainStats as $stat) {
            $key = $stat->skill_domain;
            $label = SatTaxonomy::domainLabel($key);
            $pct = $stat->total_answers > 0 ? (int) round(($stat->correct_answers / $stat->total_answers) * 100) : 0;

            $results[$key] = [
                'name' => $label,
                'accuracy_pct' => $pct,
                'correct' => (int) $stat->correct_answers,
                'total' => (int) $stat->total_answers,
            ];
        }

        return $results;
    }

    /**
     * @return array{
     *     composite_avg: ?int,
     *     rw_avg: ?int,
     *     math_avg: ?int,
     *     completed_tests_count: int,
     *     domain_proficiencies: array<string, array{name: string, accuracy_pct: int, correct: int, total: int}>
     * }
     */
    private static function emptyStats(): array
    {
        return [
            'composite_avg' => null,
            'rw_avg' => null,
            'math_avg' => null,
            'completed_tests_count' => 0,
            'domain_proficiencies' => [],
        ];
    }
}
