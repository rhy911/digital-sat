@props(['tests'])

<div class="tab-pane fade" id="sections" role="tabpanel">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Create New Section</h5>
        </div>
        <div class="card-body">
            <form id="sectionForm">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="sectionTest" class="form-label">Parent Test <span class="text-danger">*</span></label>
                        <select class="form-select tom-select" id="sectionTest" name="test_id" required>
                            <option value="">Search test...</option>
                            @foreach($tests as $test)
                            <option value="{{ $test->id }}">{{ $test->title }} (ID:{{ $test->id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="sectionType" class="form-label">Section Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="sectionType" name="type" required onchange="updateSectionName(this)">
                            <option value="">Select type...</option>
                            <option value="reading_writing">Reading & Writing</option>
                            <option value="math">Math</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3 d-none">
                        <label for="sectionName" class="form-label">Display Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sectionName" name="name" placeholder="Reading and Writing">
                    </div>
                    <div class="col-md-12 mb-3">
                        <p class="form-text text-muted mb-0">
                            Section order is fixed for Digital SAT: <strong>Reading &amp; Writing</strong> is always first (order 1), <strong>Math</strong> is always second (order 2). You can only add one section of each type per test.
                        </p>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">Create Section</button>
            </form>
        </div>
    </div>

    <div class="card mt-4 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Existing Sections</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Test Title</th>
                            <th>Section Name</th>
                            <th>Type</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sectionsTableBody">
                        @foreach($tests as $test)
                            @foreach($test->sections as $section)
                            <tr>
                                <td>{{ $section->id }}</td>
                                <td>{{ $test->title }}</td>
                                <td><strong>{{ $section->name }}</strong></td>
                                <td>{{ ucfirst(str_replace('_', ' ', $section->type)) }}</td>
                                <td>{{ $section->order }}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger delete-section-btn" data-id="{{ $section->id }}">Delete</button>
                                </td>
                            </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
