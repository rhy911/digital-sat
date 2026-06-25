<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TestShareController extends Controller
{
    public function index(Test $test)
    {
        $this->authorize('manageSharing', $test);

        return response()->json([
            'status' => 'success',
            'data' => $test->shares()
                ->with('teacher:id,name,username,email')
                ->latest()
                ->get()
                ->map(fn ($share) => [
                    'id' => $share->id,
                    'user_id' => $share->user_id,
                    'name' => $share->teacher?->name ?? $share->teacher?->username ?? $share->teacher?->email,
                    'email' => $share->teacher?->email,
                    'shared_at' => $share->created_at?->toISOString(),
                ]),
        ]);
    }

    public function store(Request $request, Test $test)
    {
        $this->authorize('manageSharing', $test);

        $validated = $request->validate([
            'teacher_id' => 'nullable|integer|exists:users,id',
            'email' => 'nullable|string|email',
        ]);

        $teacher = $this->resolveTeacher($validated);
        if (! $teacher) {
            throw ValidationException::withMessages(['teacher' => 'Select an approved teacher to share with.']);
        }
        if ((int) $teacher->id === (int) $test->created_by) {
            throw ValidationException::withMessages(['teacher' => 'The owner already has full access.']);
        }
        if (! $teacher->isApprovedTeacher()) {
            throw ValidationException::withMessages(['teacher' => 'Only approved teachers can receive shared tests.']);
        }

        $share = $test->shares()->firstOrCreate(
            ['user_id' => $teacher->id],
            ['shared_by' => $request->user()->id]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher can now view, clone, and assign this test.',
            'data' => [
                'id' => $share->id,
                'user_id' => $teacher->id,
                'name' => $teacher->name ?? $teacher->username ?? $teacher->email,
                'email' => $teacher->email,
            ],
        ], $share->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Test $test, User $teacher)
    {
        $this->authorize('manageSharing', $test);

        $test->shares()->where('user_id', $teacher->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Shared access removed.',
        ]);
    }

    public function searchTeachers(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $teachers = User::query()
            ->where('role', 'teacher')
            ->where(function ($query) {
                $query->whereNull('teacher_approval_status')
                    ->orWhere('teacher_approval_status', 'approved');
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('email', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('username', 'like', $like);
                });
            })
            ->orderBy('name')
            ->orderBy('email')
            ->limit(12)
            ->get(['id', 'name', 'username', 'email']);

        return response()->json([
            'data' => $teachers->map(fn (User $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->name ?? $teacher->username ?? $teacher->email,
                'email' => $teacher->email,
            ]),
        ]);
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
