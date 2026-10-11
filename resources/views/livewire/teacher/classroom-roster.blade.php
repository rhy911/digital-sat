<div class="relative">
    @if ($pending->isNotEmpty())
        <div class="pending-block" x-data="{ selected: [] }">
            <div class="pending-head">
                <h3>{{ __('classroom.pending_requests', ['count' => $pending->count()]) }}</h3>
                @if ($classroom->status === 'active')
                    <form method="POST" action="{{ route('teacher.memberships.bulk-approve', $classroom) }}"
                        class="d-inline">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="membership_ids[]" :value="id">
                        </template>
                        <button type="submit" class="btn-sm-primary btn-compact flex-none w-auto"
                            :disabled="selected.length === 0">
                            {{ __('classroom.approve') }} (<span x-text="selected.length"></span>)
                        </button>
                    </form>
                @endif
            </div>
            @if ($classroom->status === 'active')
                <label class="pending-select-all">
                    <input type="checkbox"
                        x-bind:checked="selected.length === {{ $pending->count() }} && selected.length > 0"
                        x-on:change="selected = $event.target.checked ? [{{ $pending->pluck('id')->implode(',') }}] : []">
                    {{ __('classroom.select_all') }}
                </label>
            @endif
            <div class="flex flex-col gap-8">
                @foreach ($pending as $membership)
                    <div class="pending-row">
                        <div class="flex items-center gap-2">
                            @if ($classroom->status === 'active')
                                <input type="checkbox" x-model.number="selected" value="{{ $membership->id }}"
                                    aria-label="Select {{ $membership->student->name }}">
                            @endif
                            <div>
                                <strong>{{ $membership->student->name }}</strong>
                                <span class="pending-row-email">({{ $membership->student->email }})</span>
                            </div>
                        </div>
                        @if ($classroom->status === 'active')
                            <div class="flex gap-6">
                                <form method="POST" action="{{ route('teacher.memberships.approve', $membership) }}"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit"
                                        class="btn-sm-primary btn-tight flex-none w-auto">{{ __('classroom.approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('teacher.memberships.reject', $membership) }}"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit"
                                        class="btn-sm-ghost btn-tight flex-none w-auto">{{ __('classroom.reject') }}</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="sp-head flex items-center justify-between gap-4 flex-wrap mb-4">
        <div>
            <p>{{ __('classroom.roster_desc') }}</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <div class="relative w-full sm:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <x-ui.icon name="search" class="w-3.5 h-3.5" />
                </div>
                <input wire:model.live.debounce.250ms="search" type="text" inputmode="search"
                    placeholder="{{ __('classroom.search_students_placeholder') }}"
                    class="text-xs pl-8 pr-7 py-2 border border-slate-200/90 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand w-full bg-white transition-colors placeholder:text-slate-400"
                    aria-label="{{ __('classroom.search_students_placeholder') }}">
                @if ($search !== '')
                    <button type="button" wire:click="clearSearch"
                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer"
                        aria-label="Clear search">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="min-h-[500px] flex flex-col justify-between transition-opacity duration-150 rounded-xl border border-slate-200/90 bg-white overflow-hidden shadow-2xs"
        wire:loading.class="opacity-60 pointer-events-none">
        <div class="table-responsive-wrapper flex-1 overflow-x-auto overscroll-x-contain">
            <table class="w-full min-w-[540px] border-0 rounded-none shadow-none">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200/80">
                        <th scope="col" class="py-3 px-4 text-xs font-semibold uppercase tracking-wider text-left"
                            @if ($sortField === 'name') aria-sort="{{ $sortDirection === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            <button type="button" wire:click="sortBy('name')"
                                class="group inline-flex items-center gap-1.5 cursor-pointer select-none transition-colors {{ $sortField === 'name' ? 'text-brand font-bold' : 'text-slate-500 hover:text-slate-800' }}">
                                <span>{{ __('classroom.col_student') }}</span>
                                <span class="inline-flex items-center justify-center w-3.5 h-3.5">
                                    @if ($sortField === 'name')
                                        @if ($sortDirection === 'asc')
                                            <svg class="w-3.5 h-3.5 text-brand" viewBox="0 0 20 20" fill="currentColor"
                                                aria-hidden="true">
                                                <path fill-rule="evenodd"
                                                    d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        @else
                                            <svg class="w-3.5 h-3.5 text-brand" viewBox="0 0 20 20" fill="currentColor"
                                                aria-hidden="true">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                    @else
                                        <svg class="w-3.5 h-3.5 text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity"
                                            viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M10 3a.75.75 0 01.53.22l3.25 3.25a.75.75 0 11-1.06 1.06L10 4.81 7.28 7.53a.75.75 0 01-1.06-1.06l3.25-3.25A.75.75 0 0110 3zm-3.72 9.47a.75.75 0 011.06 0L10 15.19l2.72-2.72a.75.75 0 111.06 1.06l-3.25 3.25a.75.75 0 01-1.06 0l-3.25-3.25a.75.75 0 010-1.06z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="py-3 px-4 text-xs font-semibold uppercase tracking-wider text-left"
                            @if ($sortField === 'joined') aria-sort="{{ $sortDirection === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            <button type="button" wire:click="sortBy('joined')"
                                class="group inline-flex items-center gap-1.5 cursor-pointer select-none transition-colors {{ $sortField === 'joined' ? 'text-brand font-bold' : 'text-slate-500 hover:text-slate-800' }}">
                                <span>{{ __('classroom.col_joined') }}</span>
                                <span class="inline-flex items-center justify-center w-3.5 h-3.5">
                                    @if ($sortField === 'joined')
                                        @if ($sortDirection === 'asc')
                                            <svg class="w-3.5 h-3.5 text-brand" viewBox="0 0 20 20" fill="currentColor"
                                                aria-hidden="true">
                                                <path fill-rule="evenodd"
                                                    d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        @else
                                            <svg class="w-3.5 h-3.5 text-brand" viewBox="0 0 20 20"
                                                fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                    @else
                                        <svg class="w-3.5 h-3.5 text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity"
                                            viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M10 3a.75.75 0 01.53.22l3.25 3.25a.75.75 0 11-1.06 1.06L10 4.81 7.28 7.53a.75.75 0 01-1.06-1.06l3.25-3.25A.75.75 0 0110 3zm-3.72 9.47a.75.75 0 011.06 0L10 15.19l2.72-2.72a.75.75 0 111.06 1.06l-3.25 3.25a.75.75 0 01-1.06 0l-3.25-3.25a.75.75 0 010-1.06z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th class="py-3 px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">
                            {{ __('classroom.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rosterPage as $membership)
                        <tr wire:key="membership-{{ $membership->id }}"
                            class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3.5 px-4 name-cell">
                                <x-ui.person-cell :name="$membership->student->name" :email="$membership->student->email" :initials="$membership->student->initials" />
                            </td>
                            <td class="py-3.5 px-4 tabular-nums font-mono text-xs text-slate-600">
                                {{ $membership->decided_at?->format('d/m/Y') ?? $membership->created_at->format('d/m/Y') }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center justify-end">
                                    <x-ui.dropdown align="right" width="48">
                                        <x-slot:trigger>
                                            <button type="button"
                                                class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-500 hover:text-slate-900 hover:bg-slate-100 hover:border-slate-300 hover:shadow-xs transition-all cursor-pointer"
                                                :class="{ 'bg-slate-100 border-slate-300 text-slate-900 shadow-xs ring-2 ring-brand/10': open }"
                                                aria-label="{{ __('classroom.col_actions') }}">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"
                                                    aria-hidden="true">
                                                    <path
                                                        d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM18 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </button>
                                        </x-slot:trigger>
                                        <x-slot:content>
                                            <a href="{{ route('teacher.classes.students.progress', [$classroom, $membership->student]) }}"
                                                class="flex items-center gap-2 px-3.5 py-3 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors text-left">
                                                <x-ui.icon name="bar-chart-2" class="w-3.5 h-3.5 text-slate-400" />
                                                <span>{{ __('classroom.view_progress') }}</span>
                                            </a>
                                            @if ($classroom->status === 'active')
                                                <div class="border-t border-slate-100">
                                                    <button type="button"
                                                        @click="open = false; $dispatch('open-confirm-delete', {
                                                                title: @js(__('classroom.action_remove') . ' ' . $membership->student->name),
                                                                message: @js(__('classroom.remove_student_confirm')),
                                                                actionUrl: @js(route('teacher.memberships.remove', $membership)),
                                                                confirmButtonText: @js(__('classroom.action_remove'))
                                                            })"
                                                        class="w-full flex items-center gap-2 px-3.5 py-3 text-xs font-medium text-rose-600 hover:bg-rose-50/70 transition-colors text-left cursor-pointer">
                                                        <x-ui.icon name="trash" class="w-3.5 h-3.5 text-rose-500" />
                                                        <span>{{ __('classroom.action_remove') }}</span>
                                                    </button>
                                                </div>
                                            @endif
                                        </x-slot:content>
                                    </x-ui.dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-24 text-center">
                                <div class="flex flex-col items-center justify-center gap-2 max-w-sm mx-auto">
                                    <div
                                        class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-1">
                                        <x-ui.icon name="search" class="w-5 h-5" />
                                    </div>
                                    @if ($search !== '')
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ __('No students found matching') }} "<span
                                                class="text-brand">{{ $search }}</span>"</p>
                                        <p class="text-xs text-slate-500">Check the spelling or try searching by
                                            student name or email.</p>
                                        <button type="button" wire:click="clearSearch"
                                            class="mt-2 inline-flex items-center px-3 py-1.5 text-xs font-semibold text-brand hover:text-brand-hover bg-brand/5 hover:bg-brand/10 rounded-lg transition-colors cursor-pointer">
                                            Clear search filter
                                        </button>
                                    @else
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ __('No students in this class yet') }}</p>
                                        <p class="text-xs text-slate-500">{!! __('classroom.no_students_roster', [
                                            'code' => '<strong class="font-mono text-slate-800">' . e($classroom->join_code) . '</strong>',
                                        ]) !!}</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rosterPage->hasPages())
            <div class="p-3 border-t border-slate-100 bg-slate-50/50">
                {{ $rosterPage->links() }}
            </div>
        @endif
    </div>
</div>
