<?php

namespace Tests\Unit;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\UserTest;
use App\Support\AssignmentStatusResolver;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AssignmentStatusResolverTest extends TestCase
{
    private function makeAssignment(array $attributes = []): Assignment
    {
        $assignment = new Assignment(array_merge([
            'status' => 'published',
            'attempt_limit' => 1,
            'available_at' => now()->subDay(),
            'due_at' => now()->addDay(),
        ], $attributes));

        $assignment->setRelation('classroom', new Classroom(['status' => 'active']));

        return $assignment;
    }

    public function test_in_progress_attempt_takes_strict_top_precedence(): void
    {
        $assignment = $this->makeAssignment([
            'status' => 'closed',
            'due_at' => now()->subHour(),
            'attempt_limit' => 1,
        ]);

        $attempts = collect([
            new UserTest(['status' => 'completed']),
            new UserTest(['status' => 'in_progress']),
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, $attempts);

        $this->assertSame('In progress', $resolved['state']);
        $this->assertSame('brand', $resolved['variant']);
        $this->assertTrue($resolved['canStart']);
    }

    public function test_completed_state_when_all_attempts_used(): void
    {
        $assignment = $this->makeAssignment(['attempt_limit' => 1]);
        $attempts = collect([
            new UserTest(['status' => 'completed']),
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, $attempts);

        $this->assertSame('Completed', $resolved['state']);
        $this->assertSame('success', $resolved['variant']);
        $this->assertFalse($resolved['canStart']);
        $this->assertSame(1, $resolved['maxAllowed']);
        $this->assertFalse($resolved['hasBonus']);
        $this->assertSame(0, $resolved['bonusAttempts']);
    }

    public function test_retry_available_when_completed_and_attempts_remain(): void
    {
        $assignment = $this->makeAssignment(['attempt_limit' => 2]);
        $attempts = collect([
            new UserTest(['status' => 'completed']),
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, $attempts);

        $this->assertSame('Retry available', $resolved['state']);
        $this->assertSame('warning', $resolved['variant']);
        $this->assertTrue($resolved['canStart']);
        $this->assertSame(2, $resolved['maxAllowed']);
    }

    public function test_recipient_bonus_attempts_factor_into_max_allowed_and_retry(): void
    {
        $assignment = $this->makeAssignment(['attempt_limit' => 1]);
        $recipient = new AssignmentRecipient([
            'extra_attempts' => 2,
        ]);
        $recipient->setRelation('assignment', $assignment);

        $attempts = collect([
            new UserTest(['status' => 'completed']),
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, $attempts, $recipient);

        $this->assertSame('Retry available', $resolved['state']);
        $this->assertSame('warning', $resolved['variant']);
        $this->assertTrue($resolved['canStart']);
        $this->assertSame(3, $resolved['maxAllowed']);
        $this->assertTrue($resolved['hasBonus']);
        $this->assertSame(2, $resolved['bonusAttempts']);
    }

    public function test_recipient_bonus_attempts_completed_once_exhausted(): void
    {
        $assignment = $this->makeAssignment(['attempt_limit' => 1]);
        $recipient = new AssignmentRecipient([
            'extra_attempts' => 1,
        ]);
        $recipient->setRelation('assignment', $assignment);

        $attempts = collect([
            new UserTest(['status' => 'completed']),
            new UserTest(['status' => 'completed']),
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, $attempts, $recipient);

        $this->assertSame('Completed', $resolved['state']);
        $this->assertSame('success', $resolved['variant']);
        $this->assertFalse($resolved['canStart']);
        $this->assertSame(2, $resolved['maxAllowed']);
    }

    public function test_closed_status(): void
    {
        $assignment = $this->makeAssignment([
            'status' => 'closed',
            'attempt_limit' => 2,
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, collect());

        $this->assertSame('Closed', $resolved['state']);
        $this->assertSame('danger', $resolved['variant']);
        $this->assertFalse($resolved['canStart']);
    }

    public function test_overdue_status(): void
    {
        $assignment = $this->makeAssignment([
            'due_at' => now()->subMinute(),
            'attempt_limit' => 2,
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, collect());

        $this->assertSame('Overdue', $resolved['state']);
        $this->assertSame('danger', $resolved['variant']);
        $this->assertFalse($resolved['canStart']);
    }

    public function test_upcoming_status(): void
    {
        $assignment = $this->makeAssignment([
            'available_at' => now()->addDay(),
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, collect());

        $this->assertSame('Upcoming', $resolved['state']);
        $this->assertSame('neutral', $resolved['variant']);
        $this->assertFalse($resolved['canStart']);
    }

    public function test_open_status_for_fresh_assignment(): void
    {
        $assignment = $this->makeAssignment();

        $resolved = AssignmentStatusResolver::resolve($assignment, collect());

        $this->assertSame('Open', $resolved['state']);
        $this->assertSame('warning', $resolved['variant']);
        $this->assertTrue($resolved['canStart']);
    }

    public function test_completed_state_when_assignment_is_overdue_and_student_has_completed_attempts(): void
    {
        $assignment = $this->makeAssignment([
            'attempt_limit' => 2,
            'due_at' => now()->subHour(),
        ]);

        $attempts = collect([
            new UserTest(['status' => 'completed']),
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, $attempts);

        $this->assertSame('Completed', $resolved['state']);
        $this->assertSame('success', $resolved['variant']);
        $this->assertFalse($resolved['canStart']);
    }

    public function test_completed_state_when_assignment_is_closed_and_student_has_completed_attempts(): void
    {
        $assignment = $this->makeAssignment([
            'attempt_limit' => 2,
            'status' => 'closed',
        ]);

        $attempts = collect([
            new UserTest(['status' => 'completed']),
        ]);

        $resolved = AssignmentStatusResolver::resolve($assignment, $attempts);

        $this->assertSame('Completed', $resolved['state']);
        $this->assertSame('success', $resolved['variant']);
        $this->assertFalse($resolved['canStart']);
    }

    public function test_recipient_bonus_attempts_without_preloaded_assignment_relation_resolves_in_memory(): void
    {
        $assignment = $this->makeAssignment(['attempt_limit' => 2]);
        $recipient = new AssignmentRecipient([
            'extra_attempts' => 1,
        ]);
        // Note: $recipient->setRelation('assignment', ...) is deliberately NOT called here.

        $resolved = AssignmentStatusResolver::resolve($assignment, collect(), $recipient);

        $this->assertSame(3, $resolved['maxAllowed']);
        $this->assertTrue($resolved['hasBonus']);
        $this->assertSame(1, $resolved['bonusAttempts']);
    }

    public function test_csv_attempt_column_formatting_with_and_without_bonus(): void
    {
        $assignment = $this->makeAssignment(['attempt_limit' => 2]);
        $recipientWithBonus = new AssignmentRecipient(['extra_attempts' => 2]);
        $recipientWithBonus->setRelation('assignment', $assignment);

        $recipientWithoutBonus = new AssignmentRecipient(['extra_attempts' => 0]);
        $recipientWithoutBonus->setRelation('assignment', $assignment);

        $maxAllowedWithBonus = $recipientWithBonus->maxAllowedAttempts();
        $this->assertSame(4, $maxAllowedWithBonus);

        $formatAttempts = fn ($completed, $recipient) => $completed . ' / ' . $recipient->maxAllowedAttempts() . ($recipient->extra_attempts > 0 ? ' (+' . $recipient->extra_attempts . ' bonus)' : '');

        $this->assertSame('1 / 4 (+2 bonus)', $formatAttempts(1, $recipientWithBonus));
        $this->assertSame('1 / 2', $formatAttempts(1, $recipientWithoutBonus));
    }

    public function test_assignment_is_section_assignment(): void
    {
        $fullAssignment = new Assignment(['assign_type' => 'full']);
        $sectionAssignment = new Assignment(['assign_type' => 'section']);

        $this->assertFalse($fullAssignment->isSectionAssignment());
        $this->assertTrue($sectionAssignment->isSectionAssignment());
    }
}
