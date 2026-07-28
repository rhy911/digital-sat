<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreEventRequest;
use App\Models\Classroom;
use App\Models\ClassroomEvent;

class ClassroomEventController extends Controller
{
    public function store(StoreEventRequest $request, Classroom $classroom)
    {
        $this->authorize('manage', $classroom);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');

        $classroom->events()->create($request->validated() + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Event added to the class calendar.');
    }

    public function update(StoreEventRequest $request, Classroom $classroom, ClassroomEvent $event)
    {
        $this->authorize('manage', $classroom);
        abort_unless((int) $event->classroom_id === (int) $classroom->id, 404);
        abort_if($classroom->status === 'archived', 409, 'Archived classes are read-only. Restore this class first.');

        $event->update($request->validated());

        return back()->with('success', 'Event updated.');
    }

    public function destroy(Classroom $classroom, ClassroomEvent $event)
    {
        $this->authorize('manage', $classroom);
        abort_unless((int) $event->classroom_id === (int) $classroom->id, 404);

        $event->delete();

        return back()->with('success', 'Event removed.');
    }
}
