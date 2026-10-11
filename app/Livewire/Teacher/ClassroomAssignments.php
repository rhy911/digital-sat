<?php

namespace App\Livewire\Teacher;

use App\Models\Classroom;
use Livewire\Component;
use Livewire\WithPagination;

class ClassroomAssignments extends Component
{
    use WithPagination;

    public Classroom $classroom;

    public function mount(Classroom $classroom): void
    {
        $this->classroom = $classroom;
    }

    public function render()
    {
        $assignmentsPage = $this->classroom->assignments()
            ->with('test')
            ->latest('created_at')
            ->paginate(12, ['*'], 'assign_page');

        return view('livewire.teacher.classroom-assignments', [
            'assignmentsPage' => $assignmentsPage,
        ]);
    }
}
