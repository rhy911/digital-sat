<?php

namespace App\Livewire\Teacher;

use App\Models\Assignment;
use App\Models\Classroom;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class Workspace extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $section = 'classes';

    public string $classStatus = 'active';

    public function mount(): void
    {
        $homeTab = session('teacher_home.tab', 'progress');
        $section = session('teacher_workspace.section', 'classes');
        $status = session('teacher_workspace.class_status', 'active');

        if ($homeTab === 'classes') {
            $section = 'classes';
        } elseif ($homeTab === 'reports') {
            $section = 'assignments';
        }

        $this->section = in_array($section, ['classes', 'assignments'], true) ? $section : 'classes';
        $this->classStatus = in_array($status, ['active', 'archived'], true) ? $status : 'active';
    }

    #[On('teacher-workspace-section')]
    public function showSection(string $section): void
    {
        abort_unless(in_array($section, ['classes', 'assignments'], true), 404);

        $this->section = $section;
        session(['teacher_workspace.section' => $section]);
        session(['teacher_home.tab' => $section === 'assignments' ? 'reports' : 'classes']);
        $this->resetPage(pageName: 'classesPage');
        $this->resetPage(pageName: 'assignmentsPage');
        $this->dispatch('teacher-workspace-section-changed', section: $section);
        $this->dispatch('teacher-home-tab-changed', tab: $section === 'assignments' ? 'reports' : 'classes');
    }

    #[On('teacher-home-progress')]
    public function showProgress(): void
    {
        session(['teacher_home.tab' => 'progress']);
        $this->dispatch('teacher-home-tab-changed', tab: 'progress');
    }

    public function showClassStatus(string $status): void
    {
        abort_unless(in_array($status, ['active', 'archived'], true), 404);

        $this->classStatus = $status;
        session(['teacher_workspace.class_status' => $status]);
        $this->resetPage(pageName: 'classesPage');
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->role, ['admin', 'teacher'], true), 403);
        abort_if($user->role === 'teacher' && ! $user->isApprovedTeacher(), 403);

        $classes = null;
        $assignments = null;

        if ($this->section === 'classes') {
            $classes = Classroom::query()
                ->when($user->role !== 'admin', fn ($query) => $query->where('owner_id', $user->id))
                ->where('status', $this->classStatus)
                ->with('owner')
                ->withCount([
                    'activeMemberships',
                    'assignments',
                    'memberships as pending_memberships_count' => fn ($query) => $query->where('status', 'pending'),
                ])
                ->latest()
                ->paginate(12, pageName: 'classesPage');
        } else {
            $assignments = Assignment::query()
                ->when($user->role !== 'admin', fn ($query) => $query->where('teacher_id', $user->id))
                ->with(['classroom', 'test'])
                ->withCount(['recipients', 'attempts'])
                ->latest()
                ->paginate(24, pageName: 'assignmentsPage');
        }

        return view('livewire.teacher.workspace', compact('classes', 'assignments'));
    }
}
