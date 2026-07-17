<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\UserTest;

class ClassroomRosterStats
{
    /**
     * Per-student completion ratio and latest score, scoped to this classroom's
     * assignments so teacher and student views report the same numbers.
     *
     * @return array<int, array{completed_ratio: string, latest_score: int|string}>
     */
    public static function forClassroom(Classroom $classroom): array
    {
        $activeMemberships = $classroom->memberships->where('status', 'active');
        $studentIds = $activeMemberships->pluck('student_id');
        $assignmentIds = $classroom->assignments->pluck('id');

        if ($studentIds->isEmpty()) {
            return [];
        }

        $attemptsByStudent = UserTest::whereIn('user_id', $studentIds)
            ->whereIn('assignment_id', $assignmentIds)
            ->where('status', 'completed')
            ->get(['user_id', 'assignment_id', 'total_score', 'completed_at'])
            ->groupBy('user_id');

        $totalCount = $assignmentIds->count();
        $stats = [];

        foreach ($activeMemberships as $membership) {
            $attempts = $attemptsByStudent->get($membership->student_id, collect());

            $stats[$membership->student_id] = [
                'completed_ratio' => $attempts->unique('assignment_id')->count()."/{$totalCount}",
                'latest_score' => $attempts->sortByDesc('completed_at')->first()?->total_score ?? '—',
            ];
        }

        return $stats;
    }

    /**
     * Highest latest-score entry across the roster, or null when no numeric scores exist.
     *
     * @param  array<int, array{completed_ratio: string, latest_score: int|string}>  $rosterStats
     * @return array{name: string, score: int}|null
     */
    public static function topScore(array $rosterStats, Classroom $classroom): ?array
    {
        $students = $classroom->memberships->where('status', 'active')->keyBy('student_id');
        $top = null;

        foreach ($rosterStats as $studentId => $stat) {
            $score = $stat['latest_score'];

            if (is_numeric($score) && (!$top || $score > $top['score'])) {
                $top = [
                    'name' => $students->get($studentId)?->student?->name ?? 'Unknown',
                    'score' => (int) $score,
                ];
            }
        }

        return $top;
    }
}
