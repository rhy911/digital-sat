<?php

namespace App\Livewire\Teacher;

use App\Models\Assignment;
use App\Services\AssignmentReportService;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class AssignmentStudentResults extends Component
{
    use WithPagination, WithoutUrlPagination;

    public Assignment $assignment;

    public function mount(Assignment $assignment): void
    {
        $this->assignment = $assignment;
    }

    public function render(AssignmentReportService $reports)
    {
        $report = $reports->build($this->assignment, perPage: 15, page: $this->getPage(), includeAnalysis: false);

        return view('livewire.teacher.assignment-student-results', [
            'assignment' => $this->assignment,
            'report' => $report,
        ]);
    }
}
