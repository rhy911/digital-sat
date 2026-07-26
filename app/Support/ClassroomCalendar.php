<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\ClassroomEvent;
use Illuminate\Support\Collection;

class ClassroomCalendar
{
    /**
     * Build a flat, JSON-friendly list of calendar items merging class events
     * with assignment open/due dates. Each item: {date, type, title, time, location, url}.
     *
     * @param  iterable<ClassroomEvent>  $events
     * @param  iterable<Assignment>  $assignments
     */
    public static function build(iterable $events, iterable $assignments, ?callable $assignmentUrl = null): array
    {
        $items = collect();

        foreach ($events as $event) {
            $items->push([
                'date' => $event->starts_at->format('Y-m-d'),
                'type' => 'event',
                'title' => $event->title,
                'time' => $event->all_day ? null : $event->starts_at->format('g:i A'),
                'location' => $event->location,
                'url' => null,
            ]);
        }

        foreach ($assignments as $assignment) {
            if ($assignment->available_at) {
                $items->push([
                    'date' => $assignment->available_at->format('Y-m-d'),
                    'type' => 'opens',
                    'title' => $assignment->title,
                    'time' => $assignment->available_at->format('g:i A'),
                    'location' => null,
                    'url' => $assignmentUrl ? $assignmentUrl($assignment) : null,
                ]);
            }
            if ($assignment->due_at) {
                $items->push([
                    'date' => $assignment->due_at->format('Y-m-d'),
                    'type' => 'due',
                    'title' => $assignment->title,
                    'time' => $assignment->due_at->format('g:i A'),
                    'location' => null,
                    'url' => $assignmentUrl ? $assignmentUrl($assignment) : null,
                ]);
            }
        }

        return $items
            ->sortBy(fn ($item) => $item['date'].($item['time'] ?? ''))
            ->values()
            ->all();
    }
}
