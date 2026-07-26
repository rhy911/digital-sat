<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnouncementCommentRequest;
use App\Models\Classroom;
use App\Models\ClassroomAnnouncement;
use App\Models\ClassroomAnnouncementComment;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;

class AnnouncementCommentController extends Controller
{
    public function store(
        StoreAnnouncementCommentRequest $request,
        Classroom $classroom,
        ClassroomAnnouncement $announcement,
        AnnouncementService $service,
    ) {
        // Belonging + view authorization are enforced in StoreAnnouncementCommentRequest::authorize().
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only.');

        $service->comment($announcement, $request->user(), $request->validated()['body']);

        return back()->with('success', 'Comment added.');
    }

    public function destroy(
        Request $request,
        Classroom $classroom,
        ClassroomAnnouncement $announcement,
        ClassroomAnnouncementComment $comment,
        AnnouncementService $service,
    ) {
        abort_unless((int) $announcement->classroom_id === (int) $classroom->id, 404);
        abort_unless((int) $comment->announcement_id === (int) $announcement->id, 404);

        $user = $request->user();
        $isAuthor = (int) $comment->author_id === (int) $user->id;
        $isTeacher = $classroom->owner_id === $user->id || $classroom->coTeachers()->where('user_id', $user->id)->exists() || $user->role === 'admin';

        abort_unless($isAuthor || $isTeacher, 403, 'Unauthorized comment action.');

        $service->deleteComment($comment);

        return back()->with('success', 'Comment deleted.');
    }
}
