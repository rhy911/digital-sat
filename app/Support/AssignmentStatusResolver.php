<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use Illuminate\Support\Collection;

class AssignmentStatusResolver
{
    /**
     * @param Collection<int, \App\Models\UserTest> $attempts
     * @return array{state: string, variant: string, completed: Collection<int, \App\Models\UserTest>, inProgress: ?\App\Models\UserTest, used: int, canStart: bool, maxAllowed: int, hasBonus: bool, bonusAttempts: int}
     */
    public static function resolve(Assignment $assignment, Collection $attempts, ?AssignmentRecipient $recipient = null): array
    {
        $completed = $attempts->where('status', 'completed');
        $inProgress = $attempts->firstWhere('status', 'in_progress');
        $used = $attempts->count();

        if ($recipient && ! $recipient->relationLoaded('assignment')) {
            $recipient->setRelation('assignment', $assignment);
        }

        $maxAllowed = $recipient ? $recipient->maxAllowedAttempts() : (int) ($assignment->attempt_limit ?? 1);
        $bonusAttempts = (int) ($recipient->extra_attempts ?? 0);
        $hasBonus = $bonusAttempts > 0;

        if ($recipient?->status === 'withdrawn') {
            $state = 'Withdrawn';
        } elseif ($inProgress !== null) {
            $state = 'In progress';
        } elseif ($used >= $maxAllowed) {
            $state = 'Completed';
        } elseif ($completed->isNotEmpty()) {
            $state = $assignment->acceptsNewStarts() ? 'Retry available' : 'Completed';
        } elseif ($assignment->status === 'closed') {
            $state = 'Closed';
        } elseif ($assignment->due_at && now()->gte($assignment->due_at)) {
            $state = 'Overdue';
        } elseif ($assignment->available_at && now()->lt($assignment->available_at)) {
            $state = 'Upcoming';
        } else {
            $state = 'Open';
        }

        $variant = match ($state) {
            'Completed' => 'success',
            'In progress' => 'brand',
            'Overdue', 'Closed', 'Withdrawn' => 'danger',
            'Upcoming' => 'neutral',
            default => 'warning',
        };

        $canStart = $recipient?->status !== 'withdrawn' && (
            $inProgress !== null || ($assignment->acceptsNewStarts() && $used < $maxAllowed)
        );

        return [
            'state' => $state,
            'variant' => $variant,
            'completed' => $completed,
            'inProgress' => $inProgress,
            'used' => $used,
            'canStart' => $canStart,
            'maxAllowed' => $maxAllowed,
            'hasBonus' => $hasBonus,
            'bonusAttempts' => $bonusAttempts,
        ];
    }
}
