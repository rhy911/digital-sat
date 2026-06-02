@props(['userTest', 'compact' => false])

@php
    $moduleUlid = $userTest->currentModule ? $userTest->currentModule->ulid : $userTest->test->sections->first()?->modules->first()?->ulid;
@endphp

<div class="option flex flex-col items-start text-start {{ $compact ? '' : 'w-100 mb-3' }}"
    style="{{ $compact ? '' : 'text-align: left;' }}">
    <h3 class="text-xl md:text-2xl font-bold">{{ $userTest->test->title }}</h3>
    <div class="status-badge bg-yellow-100 text-yellow-800 border-yellow-200">⏳ In Progress</div>
    
    @if($moduleUlid)
        <a href="{{ route('take-test', ['ulid' => $moduleUlid]) }}" class="text-[#324dc7] hover:underline text-lg font-bold ml-auto mt-2">Resume Test</a>
    @else
        <span class="text-gray-500 text-sm ml-auto mt-2">Cannot resume</span>
    @endif
</div>
