<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\UserTest;

class ClassroomLeaderboard
{
    public static function topScores(Classroom $classroom, int $days = 7, int $limit = 3): array
    {
        $studentIds = $classroom->memberships->where('status', 'active')->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return [];
        }

        $best = UserTest::whereIn('user_id', $studentIds)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays($days))
            ->whereNotNull('total_score')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($attempts) => (int) $attempts->max('total_score'));

        $students = $classroom->memberships
            ->where('status', 'active')
            ->keyBy('student_id');

        return $best->sortDesc()
            ->take($limit)
            ->map(fn ($score, $studentId) => [
                'student_id' => $studentId,
                'name' => $students->get($studentId)?->student?->name ?? 'Unknown',
                'score' => $score,
            ])
            ->values()
            ->all();
    }
}
