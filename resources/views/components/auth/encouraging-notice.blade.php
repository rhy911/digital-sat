@props([
    'message' => 'Your answers auto-save during practice tests.',
])

<div {{ $attributes->merge(['class' => 'flex items-center justify-center gap-2 text-sm text-slate-600']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
        class="text-emerald-600 feather feather-check-circle shrink-0" aria-hidden="true">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>
    <span>{{ $message }}</span>
</div>
