<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreClassroomRequest;
use App\Http\Requests\Teacher\UpdateClassroomRequest;
use App\Models\Classroom;
use App\Models\Test;
use App\Services\ClassroomService;

class ClassroomController extends Controller
{
    public function index()
    {
        session(['teacher_workspace.section' => 'classes']);
        session(['teacher_home.tab' => 'classes']);

        return redirect()->route('home');
    }

    public function workspace()
    {
        session(['teacher_workspace.section' => 'classes']);
        session(['teacher_home.tab' => 'classes']);

        return redirect()->route('home');
    }

    public function progress()
    {
        session(['teacher_home.tab' => 'progress']);

        return redirect()->route('home');
    }

    public function store(StoreClassroomRequest $request)
    {
        Classroom::create($request->validated() + ['owner_id' => $request->user()->id]);
        return back()->with('success', 'Class created. Share its code when ready.');
    }

    public function show(Classroom $classroom)
    {
        $this->authorize('view', $classroom);
        $classroom->load([
            'owner',
            'coTeachers',
            'memberships.student',
            'assignments.test',
            'documents.creator',
        ])->loadCount([
            'activeMemberships',
            'coTeachers',
            'documents',
            'assignments',
            'memberships as pending_memberships_count' => fn ($query) => $query->where('status', 'pending'),
        ]);
        $assignableTeacher = auth()->user()->role === 'admin' ? $classroom->owner : auth()->user();
        $tests = Test::assignableTo($assignableTeacher)
            ->with('shares')
            ->latest()
            ->get(['id', 'title', 'content_locked_at', 'created_by']);
        return view('teacher.classes.show', compact('classroom', 'tests'));
    }

    public function update(UpdateClassroomRequest $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');
        $classroom->update($request->validated());
        return back()->with('success', 'Class details updated.');
    }

    public function rotateCode(Classroom $classroom)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');
        $classroom->update(['join_code' => Classroom::generateJoinCode(), 'join_code_rotated_at' => now()]);
        return back()->with('success', 'Join code rotated.');
    }

    public function archive(Classroom $classroom, ClassroomService $service)
    {
        $this->authorize('manageTeam', $classroom);
        $service->archive($classroom);
        return redirect()->route('teacher.classes.index')->with('success', 'Class archived. History remains available.');
    }

    public function restore(Classroom $classroom)
    {
        $this->authorize('manageTeam', $classroom);
        $classroom->update(['status' => 'active']);
        return back()->with('success', 'Class restored. Closed assignments remain closed.');
    }
}
