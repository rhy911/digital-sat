@props([
    'count' => 1,
])

@if ($count > 1)
    <div class="space-y-2" role="status" aria-label="Loading">
        @for ($i = 0; $i < $count; $i++)
            <div {{ $attributes->class(['animate-pulse rounded bg-slate-200']) }}></div>
        @endfor
    </div>
@else
    <div role="status" aria-label="Loading" {{ $attributes->class(['animate-pulse rounded bg-slate-200']) }}></div>
@endif
