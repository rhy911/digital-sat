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
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function mount(Assignment $assignment): void
    {
        $this->assignment = $assignment;
    }

    public function render(AssignmentReportService $reports): \Illuminate\Contracts\View\View
    {
        $report = $reports->build(
            $this->assignment,
            perPage: 15,
            page: $this->getPage(),
            includeAnalysis: false,
            search: $this->search,
            sortField: $this->sortField,
            sortDirection: $this->sortDirection
        );

        return view('livewire.teacher.assignment-student-results', [
            'assignment' => $this->assignment,
            'report' => $report,
        ]);
    }
}
