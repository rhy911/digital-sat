@props([
    'type' => 'danger',
    'messages' => null,
    'dismissible' => true,
])

@php
    $variantClasses = match ($type) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-rose-200 bg-rose-50 text-rose-800',
    };
    $iconColorClasses = match ($type) {
        'success' => 'text-emerald-500',
        'warning' => 'text-amber-500',
        default => 'text-rose-500',
    };
    $messageList = is_array($messages) ? array_values(array_filter($messages)) : [];
@endphp

@if ($messageList || $slot->isNotEmpty())
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        role="alert"
        {{ $attributes->class([
            'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm',
            $variantClasses,
        ]) }}
    >
        <svg class="h-5 w-5 shrink-0 {{ $iconColorClasses }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            @if ($type === 'success')
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            @elseif ($type === 'warning')
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 11-18 0 9 9 0 0118 0Z" />
            @else
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0Z" />
            @endif
        </svg>

        <div class="flex-1">
            @if (count($messageList) > 1)
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($messageList as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @elseif (count($messageList) === 1)
                <p>{{ $messageList[0] }}</p>
            @else
                {{ $slot }}
            @endif
        </div>

        @if ($dismissible)
            <button type="button" @click="show = false" class="shrink-0 rounded-md p-1 hover:bg-black/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current" aria-label="Dismiss">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
    </div>
@endif
