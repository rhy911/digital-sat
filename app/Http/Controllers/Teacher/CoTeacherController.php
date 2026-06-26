<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CoTeacherController extends Controller
{
    public function store(Request $request, Classroom $classroom)
    {
        $this->authorize('manageTeam', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');

        $validated = $request->validate([
            'teacher_id' => 'nullable|integer|exists:users,id',
            'email' => 'nullable|string|email',
        ]);

        $teacher = $this->resolveTeacher($validated);

        if (! $teacher || $teacher->role !== 'teacher' || ! $teacher->isApprovedTeacher()) {
            throw ValidationException::withMessages(['teacher' => 'Select an approved teacher to add.']);
        }

        if ((int) $teacher->id === (int) $classroom->owner_id) {
            throw ValidationException::withMessages(['teacher' => 'The class owner already has full access.']);
        }

        $classroom->coTeachers()->syncWithoutDetaching([
            $teacher->id => ['added_by' => $request->user()->id],
        ]);

        return back()->with('success', 'Co-teacher added.');
    }

    public function destroy(Classroom $classroom, User $teacher)
    {
        $this->authorize('manageTeam', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');

        $classroom->coTeachers()->detach($teacher->id);

        return back()->with('success', 'Co-teacher removed.');
    }

    private function resolveTeacher(array $validated): ?User
    {
        if (! empty($validated['teacher_id'])) {
            return User::find($validated['teacher_id']);
        }

        if (! empty($validated['email'])) {
            return User::where('email', $validated['email'])->first();
        }

        return null;
    }
}
