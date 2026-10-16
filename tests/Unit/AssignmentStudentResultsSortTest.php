<?php

namespace Tests\Unit;

use App\Livewire\Teacher\AssignmentStudentResults;
use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\User;
use App\Models\UserTest;
use App\Services\AssignmentReportService;
use Mockery;
use Tests\TestCase;

class AssignmentStudentResultsSortTest extends TestCase
{
    public function test_sort_by_toggles_direction_and_changes_field(): void
    {
        $component = new AssignmentStudentResults();
        $this->assertSame('name', $component->sortField);
        $this->assertSame('asc', $component->sortDirection);

        // Toggle same field -> desc
        $component->sortBy('name');
        $this->assertSame('name', $component->sortField);
        $this->assertSame('desc', $component->sortDirection);

        // Toggle same field -> asc
        $component->sortBy('name');
        $this->assertSame('name', $component->sortField);
        $this->assertSame('asc', $component->sortDirection);

        // Change field -> asc
        $component->sortBy('score');
        $this->assertSame('score', $component->sortField);
        $this->assertSame('asc', $component->sortDirection);

        // Toggle score -> desc
        $component->sortBy('score');
        $this->assertSame('score', $component->sortField);
        $this->assertSame('desc', $component->sortDirection);
    }

    public function test_render_delegates_sorting_and_pagination_to_service(): void
    {
        $assignment = new Assignment(['status' => 'published', 'assign_type' => 'full']);
        $fakeReport = [
            'rows' => collect([['id' => 1], ['id' => 2]]),
            'metrics' => [],
            'paginator' => null,
        ];

        $reportsMock = Mockery::mock(AssignmentReportService::class);
        $reportsMock->shouldReceive('build')
            ->once()
            ->with($assignment, 15, 1, false, '', 'name', 'asc')
            ->andReturn($fakeReport);

        $component = new AssignmentStudentResults();
        $component->mount($assignment);

        $view = $component->render($reportsMock);
        $data = $view->getData();
        $this->assertSame($fakeReport, $data['report']);

        // Toggle score sort -> delegates sortField='score', sortDirection='asc'
        $reportsMock->shouldReceive('build')
            ->once()
            ->with($assignment, 15, 1, false, '', 'score', 'asc')
            ->andReturn($fakeReport);

        $component->sortBy('score');
        $view = $component->render($reportsMock);
        $this->assertSame($fakeReport, $view->getData()['report']);

        // Toggle score again -> delegates sortField='score', sortDirection='desc'
        $reportsMock->shouldReceive('build')
            ->once()
            ->with($assignment, 15, 1, false, '', 'score', 'desc')
            ->andReturn($fakeReport);

        $component->sortBy('score');
        $view = $component->render($reportsMock);
        $this->assertSame($fakeReport, $view->getData()['report']);
    }

    public function test_sort_by_resets_pagination_page(): void
    {
        $component = new AssignmentStudentResults();
        $component->setPage(3);
        $this->assertSame(3, $component->getPage());

        $component->sortBy('score');
        $this->assertSame(1, $component->getPage());
    }

    public function test_updated_search_resets_pagination_page(): void
    {
        $component = new AssignmentStudentResults();
        $component->setPage(3);
        $component->search = 'Bob';
        $component->updatedSearch();
        $this->assertSame(1, $component->getPage());
    }
}

