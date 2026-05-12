@props(['tests'])

<div class="tab-pane fade" id="modules" role="tabpanel">
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Create New Module</h5>
        </div>
        <div class="card-body">
            <form id="moduleForm">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="moduleSection" class="form-label">Parent Section <span class="text-danger">*</span></label>
                        <select class="form-select tom-select" id="moduleSection" name="section_id" required onchange="applyModuleDefaults(this)">
                            <option value="">Search section...</option>
                            @foreach($tests as $test)
                                @foreach($test->sections as $section)
                                <option value="{{ $section->id }}" data-type="{{ $section->type }}">
                                    {{ $test->title }} - {{ $section->name }} (ID:{{ $section->id }})
                                </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="moduleNumber" class="form-label">Module # <span class="text-danger">*</span></label>
                        <select class="form-select" id="moduleNumber" name="module_number" required>
                            <option value="1">1 (Standard)</option>
                            <option value="2">2 (Adaptive)</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="difficultyLevel" class="form-label">Difficulty <span class="text-danger">*</span></label>
                        <select class="form-select" id="difficultyLevel" name="difficulty_level" required>
                            <option value="standard">Standard (M1)</option>
                            <option value="easy">Easy (M2)</option>
                            <option value="hard">Hard (M2)</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="moduleDuration" class="form-label">Duration (min) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="moduleDuration" name="duration_minutes" value="32" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="totalQuestions" class="form-label">Total Questions <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="totalQuestions" name="total_questions" value="27" required>
                        <small class="text-muted">Includes 2 pretest questions</small>
                    </div>
                    <div class="col-md-4 mb-3 d-flex align-items-end">
                        <p class="form-text text-muted mb-2">
                            <strong>Exam sequence</strong> within each section: Module 1 then Module 2; across the test, all Reading &amp; Writing modules (1→2) then all Math modules (1→2).
                        </p>
                    </div>
                </div>
                <button type="submit" class="btn btn-info text-white">Create Module</button>
            </form>
        </div>
    </div>

    <div class="card mt-4 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Existing Modules</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Test - Section</th>
                            <th>Module #</th>
                            <th>Exam seq.</th>
                            <th>Difficulty</th>
                            <th>Duration</th>
                            <th>Questions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="modulesTableBody">
                        @foreach($tests as $test)
                            @foreach($test->sections as $section)
                                @foreach($section->modules as $module)
                                <tr>
                                    <td>{{ $module->id }}</td>
                                    <td><small>{{ $test->title }}</small><br><strong>{{ $section->name }}</strong></td>
                                    <td>{{ $module->module_number }}</td>
                                    <td><span class="badge bg-secondary">{{ $module->order }}</span></td>
                                    <td>
                                        <span class="badge bg-{{ $module->difficulty_level === 'hard' ? 'danger' : ($module->difficulty_level === 'easy' ? 'success' : 'primary') }}">
                                            {{ ucfirst($module->difficulty_level) }}
                                        </span>
                                    </td>
                                    <td>{{ $module->duration_minutes }}m</td>
                                    <td>{{ $module->total_questions }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger delete-module-btn" data-id="{{ $module->id }}">Delete</button>
                                    </td>
                                </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
