<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreAnnouncementRequest;
use App\Models\Classroom;
use App\Models\ClassroomAnnouncement;
use App\Services\AnnouncementService;

class AnnouncementController extends Controller
{
    public function store(StoreAnnouncementRequest $request, Classroom $classroom, AnnouncementService $service)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');

        $service->post($classroom, $request->user(), $request->validated()['body']);

        return back()->with('success', 'Announcement posted. Students were notified.');
    }

    public function togglePin(Classroom $classroom, ClassroomAnnouncement $announcement, AnnouncementService $service)
    {
        $this->authorize('manage', $classroom);
        abort_unless((int) $announcement->classroom_id === (int) $classroom->id, 404);

        $service->togglePin($announcement);

        return back()->with('success', $announcement->pinned ? 'Announcement pinned.' : 'Announcement unpinned.');
    }

    public function destroy(Classroom $classroom, ClassroomAnnouncement $announcement)
    {
        $this->authorize('manage', $classroom);
        abort_unless((int) $announcement->classroom_id === (int) $classroom->id, 404);

        $announcement->delete();

        return back()->with('success', 'Announcement removed.');
    }
}
