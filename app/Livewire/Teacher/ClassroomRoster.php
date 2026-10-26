<?php

namespace App\Livewire\Teacher;

use App\Models\Classroom;
use App\Models\User;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class ClassroomRoster extends Component
{
    use WithPagination, WithoutUrlPagination;

    public Classroom $classroom;
    public string $search = '';
    public string $sortField = 'joined';
    public string $sortDirection = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage('rosterPage');
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage('rosterPage');
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'name' ? 'asc' : 'desc';
        }

        $this->resetPage('rosterPage');
    }

    public function mount(Classroom $classroom): void
    {
        $this->classroom = $classroom;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $pending = $this->classroom->memberships()
            ->where('status', 'pending')
            ->with('student')
            ->oldest()
            ->get();

        $search = trim($this->search);
        $direction = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

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
            ->when($this->sortField === 'name', function ($query) use ($direction) {
                $query->orderBy(
                    User::select('name')->whereColumn('users.id', 'classroom_memberships.student_id'),
                    $direction
                );
            }, function ($query) use ($direction) {
                $query->orderByRaw("COALESCE(decided_at, created_at) {$direction}");
            })
            ->paginate(15, ['*'], 'rosterPage', $this->getPage('rosterPage'));

        return view('livewire.teacher.classroom-roster', [
            'classroom' => $this->classroom,
            'pending' => $pending,
            'rosterPage' => $rosterPage,
            'search' => $this->search,
            'sortField' => $this->sortField,
            'sortDirection' => $this->sortDirection,
        ]);
    }
}
