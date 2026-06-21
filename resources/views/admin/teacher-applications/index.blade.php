<x-layouts.student :user="auth()->user()" title="Teacher applications" header-type="progress">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="ds-teacher-workspace teacher-detail">
        <div class="page-heading">
            <div><h1>Teacher applications</h1><p>Approve verified educators before they can create classes or content.</p></div>
        </div>
        @if(session('success'))<div class="class-alert class-alert--success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="class-alert class-alert--error" role="alert">{{ $errors->first() }}</div>@endif
        <section class="class-panel">
            <div class="report-table-wrap">
                <table class="report-table">
                    <thead><tr><th>Applicant</th><th>Status</th><th>Joined</th><th>Decision</th></tr></thead>
                    <tbody>
                    @forelse($applications as $teacher)
                        <tr>
                            <td><strong>{{ $teacher->name }}</strong><small>{{ $teacher->email }}</small></td>
                            <td><span class="status-chip status-chip--{{ $teacher->teacher_approval_status }}">{{ ucfirst($teacher->teacher_approval_status) }}</span></td>
                            <td>{{ $teacher->created_at->format('M j, Y') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.teacher-applications.decide', $teacher) }}" class="review-actions">
                                    @csrf
                                    <label>Decision note <span>(required for rejection)</span><input name="reason" placeholder="Add a concise reason"></label>
                                    <button type="submit" name="decision" value="approved" class="class-button class-button--primary">Approve</button>
                                    <button type="submit" name="decision" value="rejected" class="class-button">Reject</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No pending or rejected applications.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        {{ $applications->links() }}
    </div>
</x-layouts.student>
