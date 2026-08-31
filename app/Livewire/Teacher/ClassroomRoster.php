<?php

namespace App\Livewire\Teacher;

use App\Models\Classroom;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class ClassroomRoster extends Component
{
    use WithPagination, WithoutUrlPagination;

    public Classroom $classroom;
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage('rosterPage');
    }

    public function mount(Classroom $classroom): void
    {
        $this->classroom = $classroom;
    }

    public function render()
    {
        $pending = $this->classroom->memberships()
            ->where('status', 'pending')
            ->with('student')
            ->oldest()
            ->get();

        $search = trim($this->search);

        $rosterPage = $this->classroom->memberships()
            ->where('status', 'active')
            ->with('student')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                    $q->where('name', 'like', $like)
                      ->orWhere('email', 'like', $like);
                });
            })
            ->orderByDesc('decided_at')
            ->paginate(15, ['*'], 'rosterPage', $this->getPage('rosterPage'));

        return view('livewire.teacher.classroom-roster', [
            'classroom' => $this->classroom,
            'pending' => $pending,
            'rosterPage' => $rosterPage,
        ]);
    }
}
