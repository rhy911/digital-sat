<x-layouts.student :user="auth()->user()" title="Teacher applications" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/analytics.css'])
    @endpush
    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="route('admin.teacher-applications.index')" :avatar-label="auth()->user()->initials"
            :items="\App\Support\NavRail::admin('applications')" />

        <div class="shell-content">
        <x-ui.flash />
        <div class="binder-panel">
            <div class="ledger-header">
                <div class="dh-left">
                    <h2>Teacher applications</h2>
                    <div class="dh-desc">Approve verified educators before they can create classes or content.</div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table>
                    <thead><tr><th>Applicant</th><th>Status</th><th>Joined</th><th>Decision</th></tr></thead>
                    <tbody>
                    @forelse($applications as $teacher)
                        <tr>
                            <td class="name-cell">
                                <div class="n">{{ $teacher->name }}</div>
                                <div class="m">{{ $teacher->email }}</div>
                            </td>
                            <td>
                                @php
                                    $pillVariant = match ($teacher->teacher_approval_status) {
                                        'approved' => 'ok',
                                        'rejected' => 'danger',
                                        default => 'pending',
                                    };
                                @endphp
                                <span class="status-pill {{ $pillVariant }}"><span class="d"></span>{{ ucfirst($teacher->teacher_approval_status) }}</span>
                            </td>
                            <td>{{ $teacher->created_at->format('M j, Y') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.teacher-applications.decide', $teacher) }}"
                                    class="flex flex-col gap-2 items-end">
                                    @csrf
                                    <label class="form-field-label w-full">Decision note <span class="text-faint text-2xs">(required for rejection)</span>
                                        <input name="reason" placeholder="Add a concise reason" class="settings-input w-full mt-4">
                                    </label>
                                    <div class="flex gap-2">
                                        <button type="submit" name="decision" value="rejected" class="btn-sm-ghost">Reject</button>
                                        <button type="submit" name="decision" value="approved" class="btn-sm-primary">Approve</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-row">No pending or rejected applications.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($applications->hasPages())
                <div class="mt-12">{{ $applications->links() }}</div>
            @endif
        </div>
        </div>
    </div>
</x-layouts.student>
