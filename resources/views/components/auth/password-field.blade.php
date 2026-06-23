@props(['label', 'inputId', 'name', 'autocomplete' => null, 'toggleId', 'targetId'])

<label for="{{ $inputId }}" class="form-label">{{ $label }}</label>
<div class="password-field">
    <input type="password" class="form-control" id="{{ $inputId }}" name="{{ $name }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif>
    <button type="button" class="password-toggle" id="{{ $toggleId }}" data-password-target="{{ $targetId }}"
        aria-label="Show password"></button>
</div>
