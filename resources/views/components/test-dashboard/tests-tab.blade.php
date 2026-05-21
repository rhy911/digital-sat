@props(['tests'])

<div class="tab-pane fade show active" id="tests" role="tabpanel">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Create New Test</h5>
        </div>
        <div class="card-body">
            <form id="testForm">
                @csrf
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="testTitle" class="form-label">Test Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="testTitle" name="title" placeholder="e.g. Digital SAT Practice Test 1" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="testType" class="form-label">Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="testType" name="test_type" required>
                            <option value="full_length" selected>Full Length (Standard)</option>
                            <option value="section_only">Section Only</option>
                            <option value="mini_quiz">Mini Quiz</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3 d-none">
                        <label for="totalDuration" class="form-label">Total Duration (min) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="totalDuration" name="total_duration_minutes" value="0">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="breakDuration" class="form-label">Break (min) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="breakDuration" name="break_duration_minutes" value="10" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="testStatus" class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="testStatus" name="status" required>
                            <option value="active" selected>Active</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Test</button>
            </form>
        </div>
    </div>

    <div class="card mt-4 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Existing Tests</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="testsTableBody">
                        @forelse($tests as $test)
                        <tr>
                            <td>{{ $test->id }}</td>
                            <td><strong>{{ $test->title }}</strong></td>
                            <td>{{ ucfirst(str_replace('_', ' ', $test->test_type)) }}</td>
                            <td><span class="badge bg-{{ $test->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($test->status) }}</span></td>
                            <td>{{ $test->total_duration_minutes }}m</td>
                            <td>
                                <div class="d-flex gap-2">
                                    <select class="form-select form-select-sm status-select" data-test-id="{{ $test->id }}">
                                        <option value="draft" {{ $test->status === 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="active" {{ $test->status === 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="archived" {{ $test->status === 'archived' ? 'selected' : '' }}>Archived</option>
                                    </select>
                                    <button class="btn btn-sm btn-outline-info clone-test-btn" data-id="{{ $test->id }}" title="Clone Template (Hierarchy Only)"><i class="bi bi-copy"></i></button>
                                    <button class="btn btn-sm btn-outline-danger delete-test-btn" data-id="{{ $test->id }}">Delete</button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No tests found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
