<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassroomMembership;
use App\Notifications\MembershipDecisionNotification;
use App\Services\ClassroomService;

class MembershipController extends Controller
{
    public function approve(ClassroomMembership $membership, ClassroomService $service)
    {
        $this->authorize('manage', $membership->classroom);
        abort_if($membership->classroom->status === 'archived', 409, 'Archived classes are read-only.');
        $membership = $service->decide($membership, auth()->user(), true);
        $membership->student->notify(new MembershipDecisionNotification($membership->classroom, true));
        $membership->classroom->assignments()->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>', now()))
            ->each(fn ($assignment) => $membership->student->notify(new \App\Notifications\AssignmentPublishedNotification($assignment->load('classroom'))));
        return back()->with('success', 'Student approved.');
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
