@props(['tests', 'allModules'])

<div class="tab-pane fade" id="modules" role="tabpanel">
    <div class="row g-4">
        <!-- Create Module Form -->
        <div class="col-md-7">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-folder-plus"></i> Create New Reusable Module</h5>
                </div>
                <div class="card-body">
                    <form id="moduleForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="moduleTest" class="form-label">Target Test <span class="text-muted">(Optional)</span></label>
                                <select class="form-select tom-select" id="moduleTest" name="test_id">
                                    <option value="">No test (Standalone reusable module)</option>
                                    @foreach($tests as $test)
                                    <option value="{{ $test->id }}">
                                        {{ $test->title }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Section will be auto-generated inside the test</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="moduleSectionType" class="form-label">Section Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="moduleSectionType" name="section_type" required onchange="applyModuleDefaults(this)">
                                    <option value="reading_writing" data-type="reading_writing">Reading and Writing</option>
                                    <option value="math" data-type="math">Math</option>
                                </select>
                                <small class="text-muted">Auto-selects exam duration &amp; counts</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="moduleKey" class="form-label">Module Key / Unique Code <span class="text-muted">(Optional)</span></label>
                            <input type="text" class="form-control font-monospace" id="moduleKey" name="key" placeholder="e.g. RW_M1_STANDARD_01">
                            <small class="text-muted">Generated automatically if left blank</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="moduleNumber" class="form-label">Module Type # <span class="text-danger">*</span></label>
                                <select class="form-select" id="moduleNumber" name="module_number" required>
                                    <option value="1">1 (Standard / M1)</option>
                                    <option value="2">2 (Adaptive / M2)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="difficultyLevel" class="form-label">Difficulty <span class="text-danger">*</span></label>
                                <select class="form-select" id="difficultyLevel" name="difficulty_level" required>
                                    <option value="standard">Standard (M1)</option>
                                    <option value="easy">Easy (M2)</option>
                                    <option value="hard">Hard (M2)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="moduleDuration" class="form-label">Duration (min) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="moduleDuration" name="duration_minutes" value="32" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="totalQuestions" class="form-label">Total Questions <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="totalQuestions" name="total_questions" value="27" required>
                                <small class="text-muted">Includes 2 pretest questions</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <p class="form-text text-muted mb-0">
                                <strong>Tip:</strong> Standalone modules can be associated with multiple test sections later using the link panel.
                            </p>
                        </div>
                        <button type="submit" class="btn btn-info text-white">Create Module</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Link Existing Module Form -->
        <div class="col-md-5">
            <div class="card h-100 shadow-sm border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-link-45deg"></i> Link Reusable Module to Section</h5>
                </div>
                <div class="card-body">
                    <form id="linkModuleForm">
                        @csrf
                        <div class="mb-4">
                            <label for="linkSection" class="form-label">Target Section <span class="text-danger">*</span></label>
                            <select class="form-select tom-select" id="linkSection" name="section_id" required>
                                <option value="">Select section...</option>
                                @foreach($tests as $test)
                                    @foreach($test->sections as $section)
                                    <option value="{{ $section->id }}">
                                        {{ $test->title }} - {{ $section->name }}
                                    </option>
                                    @endforeach
                                @endforeach
                            </select>
                            <small class="text-muted">Select the Section to receive this Module</small>
                        </div>

                        <div class="mb-4">
                            <label for="linkModule" class="form-label">Reusable Module <span class="text-danger">*</span></label>
                            <select class="form-select tom-select" id="linkModule" name="module_id" required>
                                <option value="">Select module by key/ID...</option>
                                @foreach($allModules as $mod)
                                <option value="{{ $mod->id }}">
                                    [{{ $mod->key ?? 'ID: ' . $mod->id }}] - Mod {{ $mod->module_number }} ({{ ucfirst($mod->difficulty_level) }})
                                </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Choose the module you wish to link</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Associate Module</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Existing Modules Listing -->
    <div class="card mt-4 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-journals"></i> All Reusable Modules &amp; Composition</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Module Key / Code</th>
                            <th>Linked Tests &amp; Sections</th>
                            <th>Type #</th>
                            <th>Difficulty</th>
                            <th>Duration</th>
                            <th>Questions</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="modulesTableBody">
                        @forelse($allModules as $module)
                        <tr>
                            <td>{{ $module->id }}</td>
                            <td>
                                <code class="font-monospace bg-light px-2 py-1 border rounded text-dark">{{ $module->key ?? 'N/A' }}</code>
                            </td>
                            <td>
                                @if($module->sections->isEmpty())
                                    <span class="badge bg-warning text-dark"><i class="bi bi-unlock"></i> Standalone (Reusable)</span>
                                @else
                                    <div class="d-flex flex-column gap-1">
                                        @foreach($module->sections as $sec)
                                        <div>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1">
                                                <i class="bi bi-tag"></i> {{ $sec->test->title ?? 'Test' }} &raquo; <strong>{{ $sec->name }}</strong>
                                            </span>
                                        </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary">Mod {{ $module->module_number }}</span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $module->difficulty_level === 'hard' ? 'danger' : ($module->difficulty_level === 'easy' ? 'success' : 'primary') }}">
                                    {{ ucfirst($module->difficulty_level) }}
                                </span>
                            </td>
                            <td>{{ $module->duration_minutes }}m</td>
                            <td>{{ $module->total_questions }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger delete-module-btn" data-id="{{ $module->id }}">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No modules found. Create one above!</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
