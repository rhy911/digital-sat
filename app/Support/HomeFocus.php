<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The one sentence under the greeting on /home.
 *
 * Both ladders return the most urgent statement that is currently true, so the
 * line moves with the user's situation instead of restating a fixed greeting.
 * Every count passed in must come from a real aggregate — the card lists on the
 * page are truncated and include finished work, so counting those would lie.
 *
 * Deliberately abstract: no assignment or test titles, because those are
 * already visible in the cards below. The subtitle ranks; the cards enumerate.
 */
class HomeFocus
{
    /**
     * @param  int  $open  Assignments still finishable: published, available, not yet completed.
     * @param  int  $overdue  Subset of $open whose due date has passed.
     * @param  int  $dueSoon  Subset of $open due within the next two days.
     * @param  bool  $hasAnyAssignment  Whether the student has ever been assigned work.
     */
    public static function forStudent(
        int $open,
        int $overdue,
        int $dueSoon,
        bool $hasAnyAssignment,
        bool $hasAttemptInProgress,
        int $completedCount,
        ?int $bestScore,
    ): string {
        // Past due states the fact rather than an instruction: whether the work
        // can still be submitted depends on the attempt, and the card below says so.
        if ($overdue > 0) {
            return $overdue.' class '.Str::plural('assignment', $overdue).' past due.';
        }

        if ($dueSoon > 0) {
            return 'Finish '.$dueSoon.' class '.Str::plural('assignment', $dueSoon).' due in the next two days.';
        }

        if ($open > 0) {
            return $open.' class '.Str::plural('assignment', $open).' left to finish.';
        }

        // Ranked above "caught up" so an abandoned attempt never hides behind a
        // reassuring line about class work.
        if ($hasAttemptInProgress) {
            return 'Pick up the practice test you left unfinished.';
        }

        if ($hasAnyAssignment) {
            return 'You are caught up on class work.';
        }

        if ($completedCount === 0) {
            return 'Take your first practice test to unlock scores and study tips.';
        }

        return $completedCount.' practice '.Str::plural('test', $completedCount).' completed'
            .($bestScore ? ', best score '.$bestScore.'.' : '.');
    }

    /**
     * @param  array{activeClassesCount:int, enrolledStudentsCount:int, pendingRequestsCount:int, publishedAssignmentsCount:int}  $stats
     */
    public static function forTeacher(array $stats): string
    {
        $pending = (int) $stats['pendingRequestsCount'];
        $classes = (int) $stats['activeClassesCount'];
        $students = (int) $stats['enrolledStudentsCount'];
        $assignments = (int) $stats['publishedAssignmentsCount'];

        if ($pending > 0) {
            return 'Approve '.$pending.' '.Str::plural('student', $pending).' waiting to join.';
        }

        if ($classes === 0) {
            return 'Create your first class to start assigning tests.';
        }

        if ($students === 0) {
            return 'Share your class code to enrol your first students.';
        }

        if ($assignments === 0) {
            return 'Assign a test to start collecting scores.';
        }

        return $students.' '.Str::plural('student', $students)
            .' across '.$classes.' active '.Str::plural('class', $classes).'.';
    }
}
