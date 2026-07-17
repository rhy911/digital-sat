<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassroomMembership;
use App\Notifications\AssignmentPublishedNotification;
use App\Notifications\MembershipDecisionNotification;
use App\Services\ClassroomService;

class MembershipController extends Controller
{
    public function approve(ClassroomMembership $membership, ClassroomService $service)
    {
        $this->authorize('manage', $membership->classroom);
        abort_if($membership->classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $membership = $service->decide($membership, auth()->user(), true);
        $this->notifyApproved($membership);
        return back()->with('success', 'Student approved.');
    }
    public function bulkApprove(\Illuminate\Http\Request $request, \App\Models\Classroom $classroom, ClassroomService $service)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only.');

        $validated = $request->validate([
            'membership_ids' => ['required', 'array', 'min:1'],
            'membership_ids.*' => ['integer'],
        ]);

        $memberships = ClassroomMembership::where('classroom_id', $classroom->id)
            ->where('status', 'pending')
            ->whereIn('id', $validated['membership_ids'])
            ->get();

        if ($memberships->isEmpty()) {
            return back()->with('success', 'No pending requests to approve.');
        }

        $approved = $service->bulkApprove($memberships, auth()->user());

        foreach ($approved as $membership) {
            $this->notifyApproved($membership);
        }

        return back()->with('success', $approved->count().' student(s) approved.');
    }

    /**
     * Notify a newly-approved student of enrollment and any open published assignments.
     */
    private function notifyApproved(ClassroomMembership $membership): void
    {
        $membership->student->notify(new MembershipDecisionNotification($membership->classroom, true));

        $membership->classroom->assignments()
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>', now()))
            ->each(fn ($assignment) => $membership->student->notify(
                new AssignmentPublishedNotification($assignment->load('classroom'))
            ));
    }

    public function reject(ClassroomMembership $membership, ClassroomService $service)
    {
        $this->authorize('manage', $membership->classroom);
        abort_if($membership->classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $membership = $service->decide($membership, auth()->user(), false);
        $membership->student->notify(new MembershipDecisionNotification($membership->classroom, false));
        return back()->with('success', 'Join request rejected.');
    }
    public function remove(ClassroomMembership $membership, ClassroomService $service)
    {
        $this->authorize('manage', $membership->classroom);
        abort_if($membership->classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $service->endMembership($membership, auth()->user(), 'removed');
        return back()->with('success', 'Student removed; result history was preserved.');
    }
}
