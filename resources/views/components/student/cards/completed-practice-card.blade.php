@props(['userTest', 'compact' => false])

<div class="option flex flex-col items-start text-start {{ $compact ? '' : 'w-100 mb-3' }}"
    style="{{ $compact ? '' : 'text-align: left;' }}">
    <h3 class="text-xl md:text-2xl font-bold">{{ $userTest->test->title }}</h3>
    <div class="status-badge">✓ Completed</div>
    <a href="{{ route('my-practice', $userTest) }}" class="text-[#324dc7] hover:underline text-lg font-bold ml-auto">View my
        responses</a>
</div>
