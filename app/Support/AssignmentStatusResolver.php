<?php

namespace App\Support;

use App\Models\Assignment;
use Illuminate\Support\Collection;

class AssignmentStatusResolver
{
    public static function resolve(Assignment $assignment, Collection $attempts): array
    {
        $completed = $attempts->where('status', 'completed');
        $inProgress = $attempts->firstWhere('status', 'in_progress');

        $state = $completed->isNotEmpty() ? 'Completed'
            : ($inProgress ? 'In progress'
            : ($assignment->available_at && now()->lt($assignment->available_at) ? 'Upcoming'
            : ($assignment->due_at && now()->gte($assignment->due_at) ? 'Overdue' : 'Open')));

        $variant = match ($state) {
            'Completed' => 'success',
            'In progress' => 'brand',
            'Overdue' => 'danger',
            'Upcoming' => 'neutral',
            default => 'warning',
        };

        $used = $attempts->count();
        $canStart = $inProgress !== null
            || ($assignment->acceptsNewStarts() && $used < $assignment->attempt_limit);

        return [
            'state' => $state,
            'variant' => $variant,
            'completed' => $completed,
            'inProgress' => $inProgress,
            'used' => $used,
            'canStart' => $canStart,
        ];
    }
}
