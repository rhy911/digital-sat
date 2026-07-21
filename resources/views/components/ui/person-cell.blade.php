@props([
    'name',
    'email',
    'initials',
])

<div {{ $attributes->class(['flex-name']) }}>
    <div class="avatar-sm">{{ $initials }}</div>
    <div>
        <div class="n">{{ $name }}</div>
        <div class="m">{{ $email }}</div>
    </div>
</div>
