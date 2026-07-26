@props([
    'items' => [],
    'canManage' => false,
    'classroom' => null,
    'events' => null,
])

<div class="class-calendar" data-class-calendar data-items='@json($items)'>
    <div class="cal-main">
        <div class="cal-head">
            <button type="button" class="btn-sm-ghost btn-compact" data-cal-prev aria-label="Previous month">‹</button>
            <span class="cal-title" data-cal-title>—</span>
            <button type="button" class="btn-sm-ghost btn-compact" data-cal-next aria-label="Next month">›</button>
            <button type="button" class="btn-sm-ghost btn-compact cal-today-btn" data-cal-today>Today</button>
        </div>

        <div class="cal-weekdays">
            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
        </div>

        <div class="cal-grid" data-cal-grid></div>

        <div class="cal-day-detail" data-cal-day>
            <p class="cal-day-empty">Select a day to see its schedule.</p>
        </div>
    </div>

    @if ($canManage && $classroom)
        <div class="cal-side">
            <form method="POST" action="{{ route('teacher.events.store', $classroom) }}" class="cal-add-form" autocomplete="off">
                @csrf
                <h4 class="cal-add-title">Add event</h4>
                <input type="text" name="title" required maxlength="180" placeholder="Session title" class="cal-input">
                <label class="cal-label">Starts</label>
                <input type="text" name="starts_at" required class="cal-input datetime-picker" placeholder="Pick date & time">
                <label class="cal-label">Ends (optional)</label>
                <input type="text" name="ends_at" class="cal-input datetime-picker" placeholder="Pick date & time">
                <input type="text" name="location" maxlength="150" placeholder="Room / location (optional)" class="cal-input">
                <label class="cal-check">
                    <input type="checkbox" name="all_day" value="1"> All day
                </label>
                <button type="submit" class="btn-sm-primary cal-add-btn">Add to calendar</button>
            </form>

            @if ($events && $events->isNotEmpty())
                <div class="cal-event-list">
                    <h4 class="cal-add-title">Upcoming events</h4>
                    @foreach ($events->sortBy('starts_at')->take(12) as $event)
                        <div class="cal-event-row">
                            <div class="cal-event-info">
                                <span class="cal-event-name">{{ $event->title }}</span>
                                <span class="cal-event-when">
                                    {{ $event->starts_at->format('D, M j') }}{{ $event->all_day ? '' : ' · '.$event->starts_at->format('g:i A') }}
                                    @if ($event->location) · {{ $event->location }} @endif
                                </span>
                            </div>
                            <form method="POST" action="{{ route('teacher.events.destroy', [$classroom, $event]) }}"
                                data-confirm="Remove this event?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-sm-ghost btn-compact cal-event-del">✕</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
