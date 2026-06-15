@props(['userTest', 'compact' => false])

@php
    $moduleUlid = $userTest->currentModule ? $userTest->currentModule->ulid : $userTest->test->sections->first()?->modules->first()?->ulid;
    $modalId = 'confirm-delete-' . $userTest->ulid;
@endphp

<div class="option flex flex-col items-start text-start {{ $compact ? '' : 'w-100 mb-3' }}"
    style="{{ $compact ? '' : 'text-align: left;' }}">
    
    <div class="flex justify-between items-start w-full">
        <h3 class="text-xl md:text-2xl font-bold">{{ $userTest->test->title }}</h3>
        
        <button type="button" x-data x-on:click.prevent="$dispatch('open-modal', '{{ $modalId }}')" class="text-gray-400 hover:text-red-600 transition-colors mt-1 ml-2" title="Delete Attempt">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </button>
    </div>

    <div class="status-badge bg-yellow-100 text-yellow-800 border-yellow-200 mt-2">⏳ In Progress</div>
    
    @if($moduleUlid)
        <a href="{{ route('engine.session', ['ulid' => $moduleUlid]) }}?attempt={{ $userTest->ulid }}" class="text-[#324dc7] hover:underline text-lg font-bold ml-auto mt-2">Resume Test</a>
    @else
        <span class="text-gray-500 text-sm ml-auto mt-2">Cannot resume</span>
    @endif

    <x-ui.modal id="{{ $modalId }}" title="Delete Attempt" maxWidth="md">
        <p class="text-slate-300">Are you sure you want to delete this in-progress attempt? This action cannot be undone.</p>
        <x-slot name="footer">
            <div class="flex justify-end gap-3 w-full">
                <button type="button" x-on:click="$dispatch('close-modal', '{{ $modalId }}')" class="px-4 py-2 border border-slate-600 text-slate-300 rounded hover:bg-slate-700 transition-colors">Cancel</button>
                <form action="{{ route('my-practice.destroy', $userTest->ulid) }}" method="POST" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors">Delete</button>
                </form>
            </div>
        </x-slot>
    </x-ui.modal>
</div>
