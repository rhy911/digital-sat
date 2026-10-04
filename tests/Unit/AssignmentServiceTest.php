<?php

namespace Tests\Unit;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Services\AssignmentService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssignmentServiceTest extends TestCase
{
    public function test_assignment_service_resolves_from_container_with_attempt_engine(): void
    {
        $service = app(AssignmentService::class);
        $this->assertInstanceOf(AssignmentService::class, $service);
    }

    public function test_close_rejects_archived_classroom(): void
    {
        $service = app(AssignmentService::class);
        $classroom = new Classroom(['status' => 'archived']);
        $assignment = new Assignment();
        $assignment->setRelation('classroom', $classroom);

        $this->expectException(ValidationException::class);
        $service->close($assignment);
    }

    public function test_reopen_rejects_archived_classroom(): void
    {
        $service = app(AssignmentService::class);
        $classroom = new Classroom(['status' => 'archived']);
        $assignment = new Assignment(['due_at' => now()->addDay()]);
        $assignment->setRelation('classroom', $classroom);

        $this->expectException(ValidationException::class);
        $service->reopen($assignment);
    }

    public function test_reopen_rejects_past_due_date(): void
    {
        $service = app(AssignmentService::class);
        $classroom = new Classroom(['status' => 'active']);
        $assignment = new Assignment(['due_at' => now()->subDay()]);
        $assignment->setRelation('classroom', $classroom);

        $this->expectException(ValidationException::class);
        $service->reopen($assignment);
    }
}
