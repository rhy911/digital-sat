@props([
    'showSuccess' => false,
    'errorId' => 'errorMessage',
    'successId' => 'successMessage',
])

@php
    $flashError = session('error');
    $flashWarning = session('warning');
    $flashSuccess = session('success') ?: session('status');
    $validationMessage = $errors->first();
    $message = $flashError ?: $flashWarning ?: $validationMessage;
@endphp

<div id="{{ $errorId }}" role="alert" aria-live="assertive">{{ $message }}</div>
@if ($showSuccess)
    <div id="{{ $successId }}" role="status" aria-live="polite">{{ $flashSuccess }}</div>
@endif
