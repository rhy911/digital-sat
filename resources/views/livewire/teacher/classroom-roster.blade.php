<div class="relative">
    @if ($pending->isNotEmpty())
        <div class="pending-block" x-data="{ selected: [] }">
            <div class="pending-head">
                <h3>Pending requests ({{ $pending->count() }})</h3>
                @if ($classroom->status === 'active')
                    <form method="POST"
                        action="{{ route('teacher.memberships.bulk-approve', $classroom) }}"
                        class="d-inline">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="membership_ids[]" :value="id">
                        </template>
                        <button type="submit" class="btn-sm-primary btn-compact flex-none w-auto"
                            :disabled="selected.length === 0">
                            Approve selected (<span x-text="selected.length"></span>)
                        </button>
                    </form>
                @endif
            </div>
            @if ($classroom->status === 'active')
                <label class="pending-select-all">
                    <input type="checkbox"
                        x-bind:checked="selected.length === {{ $pending->count() }} && selected.length > 0"
                        x-on:change="selected = $event.target.checked ? [{{ $pending->pluck('id')->implode(',') }}] : []">
                    Select all
                </label>
            @endif
            <div class="flex flex-col gap-8">
                @foreach ($pending as $membership)
                    <div class="pending-row">
                        <div class="flex items-center gap-2">
                            @if ($classroom->status === 'active')
                                <input type="checkbox" x-model.number="selected"
                                    value="{{ $membership->id }}">
                            @endif
                            <div>
                                <strong>{{ $membership->student->name }}</strong>
                                <span class="pending-row-email">({{ $membership->student->email }})</span>
                            </div>
                        </div>
                        @if ($classroom->status === 'active')
                            <div class="flex gap-6">
                                <form method="POST"
                                    action="{{ route('teacher.memberships.approve', $membership) }}"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit"
                                        class="btn-sm-primary btn-tight flex-none w-auto">Approve</button>
                                </form>
                                <form method="POST"
                                    action="{{ route('teacher.memberships.reject', $membership) }}"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit"
                                        class="btn-sm-ghost btn-tight flex-none w-auto">Reject</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="sp-head flex items-center justify-between">
        <div>
            <p>{{ __('classroom.roster_desc') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <div wire:loading.flex class="items-center gap-2 px-3 py-1 bg-blue-50 text-blue-700 text-xs font-semibold rounded-full border border-blue-200">
                <svg class="animate-spin h-3.5 w-3.5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Loading roster...</span>
            </div>
            <button class="btn-outline" onclick="copyJoinCode('{{ $classroom->join_code }}', this)">Invite code: {{ $classroom->join_code }}</button>
        </div>
    </div>

    <div wire:loading.class="opacity-50 pointer-events-none" class="transition-opacity duration-150">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Joined</th>
                    <th></th>
                    @if ($classroom->status === 'active')
                        <th class="text-right">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($rosterPage as $membership)
                    <tr>
                        <td class="name-cell">
                            <x-ui.person-cell :name="$membership->student->name" :email="$membership->student->email" :initials="$membership->student->initials" />
                        </td>
                        <td>{{ $membership->decided_at?->format('d/m/Y') ?? $membership->created_at->format('d/m/Y') }}
                        </td>
                        <td>
                            <a href="{{ route('teacher.classes.students.progress', [$classroom, $membership->student]) }}"
                                class="link-action">View progress</a>
                        </td>
                        @if ($classroom->status === 'active')
                            <td class="text-right">
                                <form method="POST"
                                    action="{{ route('teacher.memberships.remove', $membership) }}"
                                    onsubmit="return confirm('Remove this student? Result history will remain.');"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit"
                                        class="link-action danger btn-link-plain font-bold text-xs-plus">Remove</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $classroom->status === 'active' ? 4 : 3 }}" class="empty-row">
                            No active students. Share code <strong>{{ $classroom->join_code }}</strong>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($rosterPage->hasPages())
        <div class="cw-pagination">
            <div class="cw-pagination__info">
                Showing <strong>{{ $rosterPage->firstItem() }}</strong>
                to <strong>{{ $rosterPage->lastItem() }}</strong>
                of <strong>{{ $rosterPage->total() }}</strong> results
            </div>
            <div class="cw-pagination__controls">
                @if ($rosterPage->onFirstPage())
                    <button type="button" disabled class="cw-pagination__btn">
                        Previous
                    </button>
                @else
                    <button type="button" wire:click="previousPage('rosterPage')" wire:loading.attr="disabled" class="cw-pagination__btn">
                        Previous
                    </button>
                @endif

                @for ($p = 1; $p <= $rosterPage->lastPage(); $p++)
                    @if ($p == $rosterPage->currentPage())
                        <button type="button" class="cw-pagination__btn is-active">
                            {{ $p }}
                        </button>
                    @else
                        <button type="button" wire:click="gotoPage({{ $p }}, 'rosterPage')" wire:loading.attr="disabled" class="cw-pagination__btn">
                            {{ $p }}
                        </button>
                    @endif
                @endfor

                @if ($rosterPage->hasMorePages())
                    <button type="button" wire:click="nextPage('rosterPage')" wire:loading.attr="disabled" class="cw-pagination__btn">
                        Next
                    </button>
                @else
                    <button type="button" disabled class="cw-pagination__btn">
                        Next
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
