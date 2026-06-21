<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TeacherApprovalDecisionNotification;
use Illuminate\Http\Request;

class TeacherApplicationController extends Controller
{
    public function index()
    {
        $applications = User::where('role', 'teacher')->whereNotNull('email_verified_at')->whereIn('teacher_approval_status', ['pending', 'rejected'])->latest()->paginate(30);
        return view('admin.teacher-applications.index', compact('applications'));
    }
    public function decide(Request $request, User $teacher)
    {
        abort_unless($teacher->role === 'teacher', 404);
        if (!$teacher->hasVerifiedEmail()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['teacher' => 'Teacher must verify their email before approval.']);
        }
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'reason' => 'nullable|string|max:2000']);
        $teacher->update(['teacher_approval_status' => $data['decision'], 'teacher_reviewed_by' => $request->user()->id, 'teacher_reviewed_at' => now(), 'teacher_rejection_reason' => $data['decision'] === 'rejected' ? ($data['reason'] ?? null) : null]);
        $teacher->notify(new TeacherApprovalDecisionNotification($data['decision'] === 'approved', $data['reason'] ?? null));
        return back()->with('success', 'Teacher application updated.');
    }
}
