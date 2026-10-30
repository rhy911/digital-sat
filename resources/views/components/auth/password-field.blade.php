@props(['label', 'inputId', 'name', 'autocomplete' => null, 'toggleId', 'targetId', 'errorId' => null])

@php
    $errorId = $errorId ?? $inputId.'-error';
    $errorMessage = $errors->first($name);
    $describedBy = $attributes->get('aria-describedby', $errorId);
@endphp

<label for="{{ $inputId }}" class="form-label">{{ $label }}</label>
<div class="password-field">
    <input type="password" class="form-control" id="{{ $inputId }}" name="{{ $name }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($errorMessage) aria-invalid="true" @endif aria-describedby="{{ $describedBy }}"
        {{ $attributes->except('aria-describedby') }}>
    <button type="button" class="password-toggle" id="{{ $toggleId }}" data-password-target="{{ $targetId }}"
        aria-label="Show {{ strtolower($label) }}" aria-pressed="false"></button>
</div>
<div class="invalid-feedback" id="{{ $errorId }}">{{ $errorMessage }}</div>
