@props([
    'showSuccess' => false,
    'errorId' => 'errorMessage',
    'successId' => 'successMessage',
])

<div id="{{ $errorId }}" role="alert" aria-live="assertive"></div>
@if ($showSuccess)
    <div id="{{ $successId }}" role="status" aria-live="polite"></div>
@endif
