@props([
    'showSuccess' => false,
    'errorId' => 'errorMessage',
    'successId' => 'successMessage',
    'errorStyle' => 'display: none; margin-bottom: 1rem;',
    'successStyle' => 'display: none;',
])

<div id="{{ $errorId }}" style="{{ $errorStyle }}"></div>
@if ($showSuccess)
    <div id="{{ $successId }}" style="{{ $successStyle }}"></div>
@endif

