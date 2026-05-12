@props(['userTest', 'compact' => false])

<div class="option past-card d-flex flex-column align-items-start {{ $compact ? '' : 'w-100 mb-3' }}"
    style="{{ $compact ? '' : 'text-align: left;' }}">
    <h4>{{ $userTest->test->title }}</h4>
    <div class="status-badge">✓ Completed</div>
    <a href="{{ route('my-practice', $userTest->id) }}" class="view-response">View my responses</a>
</div>
