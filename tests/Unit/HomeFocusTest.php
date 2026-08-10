<?php

namespace Tests\Unit;

use App\Support\HomeFocus;
use PHPUnit\Framework\TestCase;

class HomeFocusTest extends TestCase
{
    private function student(array $overrides = []): string
    {
        return HomeFocus::forStudent(
            open: $overrides['open'] ?? 0,
            overdue: $overrides['overdue'] ?? 0,
            dueSoon: $overrides['dueSoon'] ?? 0,
            hasAnyAssignment: $overrides['hasAnyAssignment'] ?? false,
            hasAttemptInProgress: $overrides['hasAttemptInProgress'] ?? false,
            completedCount: $overrides['completedCount'] ?? 0,
            bestScore: $overrides['bestScore'] ?? null,
        );
    }

    private function teacher(array $overrides = []): string
    {
        return HomeFocus::forTeacher([
            'activeClassesCount' => $overrides['activeClassesCount'] ?? 0,
            'enrolledStudentsCount' => $overrides['enrolledStudentsCount'] ?? 0,
            'pendingRequestsCount' => $overrides['pendingRequestsCount'] ?? 0,
            'publishedAssignmentsCount' => $overrides['publishedAssignmentsCount'] ?? 0,
        ]);
    }

    public function test_overdue_outranks_every_other_student_signal(): void
    {
        $line = $this->student([
            'open' => 4, 'overdue' => 1, 'dueSoon' => 2,
            'hasAnyAssignment' => true, 'hasAttemptInProgress' => true,
            'completedCount' => 9, 'bestScore' => 1400,
        ]);

        $this->assertSame('1 class assignment past due.', $line);
    }

    public function test_due_soon_outranks_merely_open(): void
    {
        $this->assertSame(
            'Finish 2 class assignments due in the next two days.',
            $this->student(['open' => 5, 'dueSoon' => 2, 'hasAnyAssignment' => true]),
        );
    }

    public function test_open_assignments_reported_when_nothing_is_urgent(): void
    {
        $this->assertSame(
            '3 class assignments left to finish.',
            $this->student(['open' => 3, 'hasAnyAssignment' => true]),
        );
    }

    public function test_unfinished_attempt_is_ranked_above_the_caught_up_line(): void
    {
        // The reassuring "caught up" line must never hide an abandoned attempt.
        $this->assertSame(
            'Pick up the practice test you left unfinished.',
            $this->student(['hasAnyAssignment' => true, 'hasAttemptInProgress' => true, 'completedCount' => 2]),
        );
    }

    public function test_caught_up_only_claimed_when_assignments_exist_and_none_are_open(): void
    {
        $this->assertSame(
            'You are caught up on class work.',
            $this->student(['hasAnyAssignment' => true, 'completedCount' => 2, 'bestScore' => 1200]),
        );

        // No assignments at all is not "caught up" — it is a different situation.
        $this->assertSame(
            'Take your first practice test to unlock scores and study tips.',
            $this->student(['hasAnyAssignment' => false]),
        );
    }

    public function test_steady_state_reports_real_totals_and_omits_a_missing_best_score(): void
    {
        $this->assertSame(
            '7 practice tests completed, best score 1340.',
            $this->student(['completedCount' => 7, 'bestScore' => 1340]),
        );

        $this->assertSame(
            '1 practice test completed.',
            $this->student(['completedCount' => 1, 'bestScore' => null]),
        );
    }

    public function test_pending_join_requests_outrank_every_other_teacher_signal(): void
    {
        $this->assertSame(
            'Approve 2 students waiting to join.',
            $this->teacher([
                'pendingRequestsCount' => 2, 'activeClassesCount' => 3,
                'enrolledStudentsCount' => 40, 'publishedAssignmentsCount' => 5,
            ]),
        );
    }

    public function test_teacher_ladder_walks_setup_in_order(): void
    {
        $this->assertSame('Create your first class to start assigning tests.', $this->teacher());

        $this->assertSame(
            'Share your class code to enrol your first students.',
            $this->teacher(['activeClassesCount' => 1]),
        );

        $this->assertSame(
            'Assign a test to start collecting scores.',
            $this->teacher(['activeClassesCount' => 1, 'enrolledStudentsCount' => 12]),
        );
    }

    public function test_teacher_steady_state_pluralises_classes_correctly(): void
    {
        $this->assertSame(
            '12 students across 1 active class.',
            $this->teacher([
                'activeClassesCount' => 1, 'enrolledStudentsCount' => 12, 'publishedAssignmentsCount' => 3,
            ]),
        );

        $this->assertSame(
            '30 students across 2 active classes.',
            $this->teacher([
                'activeClassesCount' => 2, 'enrolledStudentsCount' => 30, 'publishedAssignmentsCount' => 3,
            ]),
        );
    }
}
