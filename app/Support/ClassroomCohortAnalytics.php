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
     *     average_score: ?int,
     *     average_rw: ?int,
     *     average_math: ?int,
     *     total_completed: int,
     *     highest_score: ?int,
     *     domain_accuracies: array<string, array{label: string, percentage: int, count: int}>
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
            'average_score' => $scores->isNotEmpty() ? (int) round($scores->average()) : null,
            'composite_avg' => $scores->isNotEmpty() ? (int) round($scores->average()) : null,
            'average_rw' => $rwScores->isNotEmpty() ? (int) round($rwScores->average()) : null,
            'rw_avg' => $rwScores->isNotEmpty() ? (int) round($rwScores->average()) : null,
            'average_math' => $mathScores->isNotEmpty() ? (int) round($mathScores->average()) : null,
            'math_avg' => $mathScores->isNotEmpty() ? (int) round($mathScores->average()) : null,
            'total_completed' => $completedAttempts->count(),
            'completed_tests_count' => $completedAttempts->count(),
            'highest_score' => $scores->isNotEmpty() ? (int) $scores->max() : null,
            'domain_accuracies' => $domainAccuracies,
            'domain_proficiencies' => $domainAccuracies,
        ];
    }

    /**
     * Calculate question accuracy across standard SAT skill domains.
     *
     * @param \Illuminate\Support\Collection<int, int> $attemptIds
     * @return array<string, array{label: string, percentage: int, count: int}>
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

        $domainLabels = [
            'algebra' => 'Algebra',
            'advanced_math' => 'Advanced Math',
            'problem_solving' => 'Problem-Solving & Data',
            'geometry_trigonometry' => 'Geometry & Trig',
            'craft_structure' => 'Craft & Structure',
            'information_ideas' => 'Information & Ideas',
            'standard_english' => 'Standard English',
            'expression_of_ideas' => 'Expression of Ideas',
        ];

        $results = [];
        foreach ($domainStats as $stat) {
            $key = $stat->skill_domain;
            $label = $domainLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
            $pct = $stat->total_answers > 0 ? (int) round(($stat->correct_answers / $stat->total_answers) * 100) : 0;

            $results[$key] = [
                'name' => $label,
                'label' => $label,
                'accuracy_pct' => $pct,
                'percentage' => $pct,
                'correct' => (int) $stat->correct_answers,
                'total' => (int) $stat->total_answers,
                'count' => (int) $stat->total_answers,
            ];
        }

        return $results;
    }

    private static function emptyStats(): array
    {
        return [
            'average_score' => null,
            'composite_avg' => null,
            'average_rw' => null,
            'rw_avg' => null,
            'average_math' => null,
            'math_avg' => null,
            'total_completed' => 0,
            'completed_tests_count' => 0,
            'highest_score' => null,
            'domain_accuracies' => [],
            'domain_proficiencies' => [],
        ];
    }
}
