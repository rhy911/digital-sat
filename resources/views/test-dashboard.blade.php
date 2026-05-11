@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<style>
    .ts-control { border-radius: 0.375rem !important; }
    .status-select { min-width: 100px; }
    .math-tex { font-family: 'Cambria Math', 'serif'; background: #f8f9fa; padding: 2px 5px; border-radius: 3px; }
</style>
@endpush

<x-layouts.admin :pageTitle="'Test Data Dashboard'">
    <div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-3">Digital SAT Test Data Input</h2>
            <ul class="nav nav-pills mb-3" id="dashboardTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tests-tab" data-bs-toggle="tab" data-bs-target="#tests" type="button" role="tab">1. Tests</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button" role="tab">2. Sections</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="modules-tab" data-bs-toggle="tab" data-bs-target="#modules" type="button" role="tab">3. Modules</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="questions-tab" data-bs-toggle="tab" data-bs-target="#questions" type="button" role="tab">4. Questions & Answers</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="builder-tab" data-bs-toggle="tab" data-bs-target="#builder" type="button" role="tab">5. Easy Builder (For Teachers)</button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="dashboardTabContent">
        <!-- Tests Tab -->
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

        <!-- Sections Tab -->
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

        <!-- Modules Tab -->
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

        <!-- Questions Tab -->
        <div class="tab-pane fade" id="questions" role="tabpanel">
            <div class="card mb-4 shadow-sm border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Attach Existing Question from Bank</h5>
                </div>
                <div class="card-body">
                    <form id="attachQuestionForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="attachToModule" class="form-label">Module <span class="text-danger">*</span></label>
                                <select class="form-select tom-select" id="attachToModule" name="module_id" required>
                                    <option value="">Search module...</option>
                                    @foreach($tests as $test)
                                        @foreach($test->sections as $section)
                                            @foreach($section->modules as $module)
                                            <option value="{{ $module->id }}">
                                                {{ $test->title }} | {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod {{ $module->module_number }} ({{ $module->difficulty_level }})
                                            </option>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label for="attachQuestionId" class="form-label">Question from Bank <span class="text-danger">*</span></label>
                                <select class="form-select tom-select-remote-question" id="attachQuestionId" name="question_id" required>
                                    <option value="">Search by ID or text...</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="attachPosition" class="form-label">Position</label>
                                <input type="number" class="form-control" id="attachPosition" name="position" min="1" placeholder="Auto">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Attach to Module</button>
                    </form>
                </div>
            </div>

            <div class="card mb-4 shadow-sm border-warning">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-gear-fill"></i> STEP 1: Global Import Configuration</h5>
                    <span class="badge bg-dark text-white">Required for all methods</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-2">
                            <label for="bulkQuestionModule" class="form-label fw-bold">Target Module <span class="text-danger">*</span></label>
                            <select class="form-select tom-select" id="bulkQuestionModule" required>
                                <option value="">Search module to import into...</option>
                                @foreach($tests as $test)
                                    @foreach($test->sections as $section)
                                        @foreach($section->modules as $module)
                                        <option value="{{ $module->id }}" data-section-type="{{ $section->type }}">
                                            {{ $test->title }} | {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod {{ $module->module_number }} ({{ $module->difficulty_level }})
                                        </option>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="bulkStartPosition" class="form-label fw-bold">Starting position <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="bulkStartPosition" min="1" value="1" required>
                            <div class="form-text small">Existing questions will be shifted down.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 shadow-sm border-success">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">STEP 2: Choose Import Method</h5>
                    <span class="badge bg-light text-dark">Select one below</span>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-4" id="importMethodTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="json-tab" data-bs-toggle="tab" data-bs-target="#import-json" type="button" role="tab"><i class="bi bi-filetype-json"></i> JSON / Editor</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="csv-tab" data-bs-toggle="tab" data-bs-target="#import-csv" type="button" role="tab"><i class="bi bi-file-earmark-spreadsheet"></i> CSV Spreadsheet</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="zip-tab" data-bs-toggle="tab" data-bs-target="#import-zip" type="button" role="tab"><i class="bi bi-file-earmark-zip"></i> ZIP (with Images)</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="importMethodContent">
                        <!-- JSON / Editor Tab -->
                        <div class="tab-pane fade show active" id="import-json" role="tabpanel">
                            <p class="text-muted small mb-3">
                                <strong>Recommended:</strong> upload a <code>.json</code> file or paste into the editor. 
                                Each row must match the SAT schema (passages, stems, choices).
                            </p>
                            <div class="row">
                                <div class="col-lg-5 mb-3">
                                    <label for="bulkJsonFile" class="form-label fw-bold">Import JSON file</label>
                                    <input type="file" class="form-control" id="bulkJsonFile" accept=".json,application/json">
                                    <p class="form-text small text-muted mt-1">Selecting a file loads it into the editor for review.</p>
                                    
                                    <div class="mt-4">
                                        <h6 class="fw-bold small text-uppercase text-muted mb-2">Examples & Samples</h6>
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary text-start" id="bulkLoadExampleRwBtn"><i class="bi bi-plus-circle"></i> Insert R&W example</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary text-start" id="bulkLoadExampleMathBtn"><i class="bi bi-plus-circle"></i> Insert Math example</button>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="bulkDownloadRwSampleBtn">R&W sample.json</button>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="bulkDownloadMathSampleBtn">Math sample.json</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-7 mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label for="bulkQuestionsJson" class="form-label fw-bold mb-0">Payload (JSON editor)</label>
                                        <button type="button" class="btn btn-link btn-sm text-danger p-0" id="bulkClearEditorBtn">Clear Editor</button>
                                    </div>
                                    <textarea class="form-control font-monospace small" id="bulkQuestionsJson" rows="12" spellcheck="false" placeholder='{ "items": [ ... ] }'></textarea>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-2">
                                <button type="button" class="btn btn-info text-white" id="bulkPreviewBtn"><i class="bi bi-eye"></i> Preview</button>
                                <button type="button" class="btn btn-success" id="bulkImportSubmitBtn"><i class="bi bi-cloud-arrow-up"></i> Import all from Editor</button>
                            </div>
                        </div>

                        <!-- CSV Tab -->
                        <div class="tab-pane fade" id="import-csv" role="tabpanel">
                            <p class="text-muted small mb-3">
                                Best for importing from Excel or Google Sheets. Header row is required. Column names are lowercase with underscores.
                            </p>
                            <div class="row align-items-end">
                                <div class="col-md-6 mb-3">
                                    <label for="bulkCsvFile" class="form-label fw-bold">CSV file (.csv or .txt) <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="bulkCsvFile" accept=".csv,.txt,text/csv,text/plain">
                                </div>
                                <div class="col-md-6 mb-3 d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary" id="bulkDownloadRwSampleCsvBtn">Download R&W sample.csv</button>
                                    <button type="button" class="btn btn-outline-primary" id="bulkDownloadMathSampleCsvBtn">Download Math sample.csv</button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-2">
                                <button type="button" class="btn btn-info text-white" id="bulkCsvPreviewBtn"><i class="bi bi-eye"></i> Preview</button>
                                <button type="button" class="btn btn-success" id="bulkCsvImportSubmitBtn"><i class="bi bi-cloud-arrow-up"></i> Import CSV</button>
                            </div>
                        </div>

                        <!-- ZIP Tab -->
                        <div class="tab-pane fade" id="import-zip" role="tabpanel">
                            <p class="text-muted small mb-3">
                                <strong>Power User:</strong> upload a <code>.zip</code> containing <code>.json</code> or <code>.csv</code> files and your images.
                                Images can be in an <code>images/</code> folder or alongside data. Use <code>[MEDIA:filename.png]</code> placeholders in your text.
                            </p>
                            <div class="row align-items-end">
                                <div class="col-md-8 mb-3">
                                    <label for="bulkZipFile" class="form-label fw-bold">ZIP file <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="bulkZipFile" accept=".zip">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <button type="button" class="btn btn-info text-white w-100" id="bulkZipImportBtn"><i class="bi bi-file-earmark-zip"></i> Import ZIP</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Questions -->
            <div class="card shadow-sm mt-2">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0">Questions Pool</h5>
                    <span class="badge bg-secondary" id="questionsPoolCountBadge">{{ $questionsTotal }} Total</span>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                        <label for="questionsTableFilter" class="small mb-0 text-muted">Filter</label>
                        <input type="text" class="form-control form-control-sm" id="questionsTableFilter" placeholder="ID or text in stem…" style="max-width: 200px;">
                        
                        <select class="form-select form-select-sm" id="questionsTableSectionFilter" style="max-width: 120px;">
                            <option value="">All Sections</option>
                            <option value="reading_writing">R&W</option>
                            <option value="math">Math</option>
                        </select>

                        <select class="form-select form-select-sm" id="questionsTableStatusFilter" style="max-width: 120px;">
                            <option value="">All Status</option>
                            <option value="1">Complete</option>
                            <option value="0">Incomplete</option>
                        </select>

                        <select class="form-select form-select-sm" id="questionsTableModuleFilter" style="max-width: 250px;">
                            <option value="">All Modules</option>
                            @foreach($tests as $test)
                                @foreach($test->sections as $section)
                                    @foreach($section->modules as $module)
                                    <option value="{{ $module->id }}">
                                        {{ $test->title }} | {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod {{ $module->module_number }}
                                    </option>
                                    @endforeach
                                @endforeach
                            @endforeach
                        </select>

                        <button type="button" class="btn btn-sm btn-outline-secondary" id="questionsTableFilterBtn">Apply</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="questionsTableFilterClearBtn">Clear</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>No.</th>
                                    <th>Sec</th>
                                    <th>Stem Snippet</th>
                                    <th>Pretest?</th>
                                    <th>Domain</th>
                                    <th>Diff</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="questionsTableBody">
                                @forelse($questions as $question)
                                <tr>
                                    <td>{{ $question->id }}</td>
                                    <td>
                                        <strong>{{ $question->question_number ?? '-' }}</strong>
                                        @if(!$question->is_complete)
                                           <span class="badge bg-warning text-dark" title="Missing Domain or Difficulty">Incomplete</span>
                                        @endif
                                    </td>
                                    <td><small>{{ $question->section_type === 'reading_writing' ? 'R&W' : 'Math' }}</small></td>
                                    <td>{{ Str::limit($question->stem, 40) }}</td>
                                    <td>{!! $question->is_pretest ? '<span class="text-danger">● Yes</span>' : 'No' !!}</td>
                                    <td><small>{{ $question->skill_domain }}</small></td>
                                    <td><small class="badge bg-light text-dark border">{{ ucfirst($question->difficulty) }}</small></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary edit-question-btn" data-id="{{ $question->id }}">Edit</button>
                                            <button class="btn btn-sm btn-outline-danger delete-question-btn" data-id="{{ $question->id }}">×</button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No questions found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div id="questionsPoolPagination" class="mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Easy Builder Tab -->
        <div class="tab-pane fade" id="builder" role="tabpanel">
            <div class="card shadow-sm border-warning mb-4">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Easy Question Builder</h5>
                    <span class="badge bg-dark">Step-by-Step Mode</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle"></i> <strong>How to use:</strong> Select a module first, then add as many questions as you want. Each question is a "Block". We will automatically format your text.
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="builderModuleId" class="form-label fw-bold">1. Select Target Module <span class="text-danger">*</span></label>
                            <select class="form-select tom-select" id="builderModuleId" required>
                                <option value="">Search module...</option>
                                @foreach($tests as $test)
                                    @foreach($test->sections as $section)
                                        @foreach($section->modules as $module)
                                        <option value="{{ $module->id }}" data-section-type="{{ $section->type }}">
                                            {{ $test->title }} | {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod {{ $module->module_number }} ({{ $module->difficulty_level }})
                                        </option>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="builderStartPosition" class="form-label fw-bold">2. Start Position</label>
                            <input type="number" class="form-control" id="builderStartPosition" value="1" min="1">
                        </div>
                    </div>

                    <div id="builderBlocksContainer">
                        <!-- Question blocks will be added here -->
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                        <button type="button" class="btn btn-outline-warning" id="addBuilderBlockBtn">
                            <i class="bi bi-plus-circle"></i> Add Another Question
                        </button>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" id="clearBuilderBtn">Clear All</button>
                    <button type="button" class="btn btn-warning" id="submitBuilderBtn">Save All Questions to Test</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Preview Modal -->
<div class="modal fade" id="importPreviewModal" tabindex="-1" aria-labelledby="importPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="importPreviewModalLabel">Import Data Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <div id="previewContent">
                    <!-- Preview items will be injected here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editQuestionForm">
                @csrf
                @method('PUT')
                <input type="hidden" id="editQuestionId" name="id">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editQuestionModalLabel">Edit Question #<span id="editQuestionIdDisplay"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="editPassageContainer" class="mb-3 d-none">
                        <label for="editPassageContent" class="form-label fw-bold">Passage Content (Reading & Writing)</label>
                        <textarea class="form-control" id="editPassageContent" name="passage_content" rows="6"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editQuestionStem" class="form-label fw-bold">Question Stem / Prompt</label>
                        <textarea class="form-control" id="editQuestionStem" name="stem" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editQuestionType" class="form-label">Question Type</label>
                            <select class="form-select" id="editQuestionType" name="question_type" required>
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="student_produced_response">Student Produced (SPR)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editDifficulty" class="form-label">Difficulty</label>
                            <select class="form-select" id="editDifficulty" name="difficulty">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editSkillDomain" class="form-label">Skill Domain</label>
                            <select class="form-select" id="editSkillDomain" name="skill_domain"></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editSkillSubdomain" class="form-label">Skill Subdomain</label>
                            <input type="text" class="form-control" id="editSkillSubdomain" name="skill_subdomain">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3" id="editSprHintContainer">
                            <label for="editSprHint" class="form-label">SPR Hint</label>
                            <input type="text" class="form-control" id="editSprHint" name="spr_hint">
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="editIsPretest" name="is_pretest" value="1">
                                <label class="form-check-label" for="editIsPretest">Pretest?</label>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="editCalculatorAllowed" name="calculator_allowed" value="1">
                                <label class="form-check-label" for="editCalculatorAllowed">Calculator?</label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div id="editMcqChoicesContainer">
                        <h6 class="fw-bold mb-3">Answer Choices (MCQ)</h6>
                        @foreach(['A', 'B', 'C', 'D'] as $index => $label)
                        <div class="row mb-2 align-items-center">
                            <div class="col-1 text-center"><strong>{{ $label }}</strong></div>
                            <input type="hidden" name="choices[{{ $index }}][label]" value="{{ $label }}">
                            <div class="col-8">
                                <input type="text" class="form-control form-control-sm" name="choices[{{ $index }}][content]" id="editChoice{{ $label }}Content" placeholder="Option content">
                            </div>
                            <div class="col-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="correct_choice" value="{{ $label }}" id="editChoice{{ $label }}Correct">
                                    <label class="form-check-label small">Correct</label>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div id="editSprAnswersContainer" class="d-none">
                        <h6 class="fw-bold mb-3">Correct Answers (SPR)</h6>
                        <div class="mb-3">
                            <label for="editSprAnswers" class="form-label">Comma-separated accepted values</label>
                            <input type="text" class="form-control" id="editSprAnswers" name="spr_answers" placeholder="e.g. 12, 12.0, 24/2">
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold mb-3">Explanation</h6>
                    <div class="mb-3">
                        <label for="editExplanation" class="form-label">Correct Rationale</label>
                        <textarea class="form-control" id="editExplanation" name="explanation" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="small">Rationale A</label>
                            <textarea class="form-control form-control-sm" id="editRationaleA" name="rationale_a" rows="1"></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="small">Rationale B</label>
                            <textarea class="form-control form-control-sm" id="editRationaleB" name="rationale_b" rows="1"></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="small">Rationale C</label>
                            <textarea class="form-control form-control-sm" id="editRationaleC" name="rationale_c" rows="1"></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="small">Rationale D</label>
                            <textarea class="form-control form-control-sm" id="editRationaleD" name="rationale_d" rows="1"></textarea>
                        </div>
                    </div>

                    <hr>
                    <div id="editMediaManagementContainer">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold m-0">Media Management</h6>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshEditMediaList()" title="Refresh media list from text fields">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                                <input type="file" id="editQuestionMediaUpload" class="d-none" accept="image/*">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('editQuestionMediaUpload').click()">
                                    <i class="bi bi-cloud-upload"></i> Upload & Insert
                                </button>
                            </div>
                        </div>
                        <div id="editMediaList" class="row g-2">
                            <!-- Existing media items will be listed here -->
                        </div>
                        <p class="text-muted small mt-2"><i class="bi bi-info-circle"></i> Media is managed via Markdown <code>![](...)</code> in text fields. Deleting here removes it from text.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Alert Container -->
<div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

<template id="builderBlockTemplate">
    <div class="card mb-4 builder-block border-secondary shadow-sm" data-index="{INDEX}">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold text-secondary">Question #{DISPLAY_INDEX}</span>
            <div class="d-flex gap-2">
                <input type="file" class="d-none builder-image-input" accept="image/*">
                <button type="button" class="btn btn-sm btn-outline-primary upload-image-btn">
                    <i class="bi bi-image"></i> Upload Image
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger remove-block-btn">Remove</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- R&W Passage (Hidden by default, shown if module is R&W) -->
                <div class="col-12 mb-3 builder-passage-container d-none">
                    <label class="form-label fw-bold small">Passage (Reading & Writing only)</label>
                    <textarea class="form-control builder-passage" rows="3" placeholder="Enter passage text..."></textarea>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold small">Question Stem <span class="text-danger">*</span></label>
                    <textarea class="form-control builder-stem" rows="2" placeholder="e.g. What is the value of x?" required></textarea>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold small">Domain (Optional)</label>
                    <select class="form-select builder-domain">
                        <option value="">Select domain...</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold small">Difficulty (Optional)</label>
                    <select class="form-select builder-difficulty">
                        <option value="">Select difficulty...</option>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>

                <hr>
                <div class="col-12">
                    <h6 class="fw-bold small mb-3">Choices (Mark the correct one)</h6>
                    <div class="builder-choices-container">
                        <!-- 4 choices -->
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0 builder-correct-radio" type="radio" name="correct_{INDEX}" value="A" checked>
                                <span class="ms-2 fw-bold">A</span>
                            </div>
                            <input type="text" class="form-control builder-choice-content" data-label="A" placeholder="Option A content" required>
                        </div>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0 builder-correct-radio" type="radio" name="correct_{INDEX}" value="B">
                                <span class="ms-2 fw-bold">B</span>
                            </div>
                            <input type="text" class="form-control builder-choice-content" data-label="B" placeholder="Option B content" required>
                        </div>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0 builder-correct-radio" type="radio" name="correct_{INDEX}" value="C">
                                <span class="ms-2 fw-bold">C</span>
                            </div>
                            <input type="text" class="form-control builder-choice-content" data-label="C" placeholder="Option C content" required>
                        </div>
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input class="form-check-input mt-0 builder-correct-radio" type="radio" name="correct_{INDEX}" value="D">
                                <span class="ms-2 fw-bold">D</span>
                            </div>
                            <input type="text" class="form-control builder-choice-content" data-label="D" placeholder="Option D content" required>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <label class="form-label fw-bold small text-muted">Explanation (Optional)</label>
                    <textarea class="form-control builder-explanation" rows="2" placeholder="Why is this answer correct?"></textarea>
                </div>
            </div>
        </div>
    </div>
</template>

@push('scripts')

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
const TEST_DASHBOARD_TAB_KEY = 'testDashboardActiveTab';

function rememberTestDashboardTab() {
    const activeBtn = document.querySelector('#dashboardTabs .nav-link.active');
    if (!activeBtn) {
        return;
    }
    const target = activeBtn.getAttribute('data-bs-target');
    if (target) {
        sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, target);
    }
}

const SKILL_DOMAINS = {
    reading_writing: [
        { value: 'craft_and_structure', label: 'Craft and Structure' },
        { value: 'information_and_ideas', label: 'Information and Ideas' },
        { value: 'standard_english_conventions', label: 'Standard English Conventions' },
        { value: 'expression_of_ideas', label: 'Expression of Ideas' }
    ],
    math: [
        { value: 'algebra', label: 'Algebra' },
        { value: 'advanced_math', label: 'Advanced Math' },
        { value: 'problem_solving_data_analysis', label: 'Problem-Solving and Data Analysis' },
        { value: 'geometry_trigonometry', label: 'Geometry and Trigonometry' }
    ]
};

const TEST_DASHBOARD_SNAPSHOT_URL = @json(route('test-dashboard.snapshot'));
const QUESTIONS_LIST_URL = @json(route('test-dashboard.questions.list'));
const QUESTIONS_SEARCH_URL = @json(route('test-dashboard.questions.search'));
const CSV_BULK_URL = @json(route('test-dashboard.questions.bulk-csv'));
const BULK_PREVIEW_URL = @json(route('test-dashboard.questions.bulk-preview'));
const CSV_BULK_PREVIEW_URL = @json(route('test-dashboard.questions.bulk-csv-preview'));

window.__tdQuestionsPage = 1;
window.__tdQuestionsPerPage = @json((int) ($questionsPerPage ?? 25));
window.__tdQuestionsQuery = '';

function escapeHtml(str) {
    if (str == null) {
        return '';
    }
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function stripTags(html) {
    return String(html).replace(/<[^>]*>/g, '');
}

function humanizeUnderscores(value) {
    if (!value) {
        return '';
    }
    return value.split('_').map(function (w) {
        return w.charAt(0).toUpperCase() + w.slice(1);
    }).join(' ');
}

function getTomSelectValue(selectId) {
    const el = document.getElementById(selectId);
    if (!el || !el.tomselect) {
        return '';
    }
    const v = el.tomselect.getValue();
    return Array.isArray(v) ? (v[0] || '') : (v || '');
}

function optionExistsInSelect(selectEl, value) {
    if (value === '' || value == null) {
        return true;
    }
    const s = String(value);
    return Array.from(selectEl.options).some(function (o) {
        return o.value === s;
    });
}

function destroyTomSelectIfAny(selectEl) {
    if (selectEl && selectEl.tomselect) {
        selectEl.tomselect.destroy();
    }
}

function initTomSelectOn(selectEl) {
    if (!selectEl || selectEl.tomselect) {
        return;
    }
    new TomSelect(selectEl, {
        create: false,
        sortField: { field: 'text', order: 'asc' }
    });
}

function captureTomSelectPreservation(submittedForm) {
    const ids = ['sectionTest', 'moduleSection', 'questionModule', 'bulkQuestionModule', 'questionPassage', 'answerQuestionId', 'explanationQuestionId'];
    const preserve = {};
    ids.forEach(function (id) {
        const el = document.getElementById(id);
        if (!el || (submittedForm && submittedForm.querySelector('#' + id))) {
            return;
        }
        preserve[id] = getTomSelectValue(id);
    });
    return preserve;
}

function rebuildSectionTestTomSelect(tests, preserved) {
    const el = document.getElementById('sectionTest');
    if (!el) {
        return;
    }
    destroyTomSelectIfAny(el);
    el.innerHTML = '<option value="">Search test...</option>';
    tests.forEach(function (t) {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.title + ' (ID:' + t.id + ')';
        el.appendChild(opt);
    });
    initTomSelectOn(el);
    if (preserved && optionExistsInSelect(el, preserved)) {
        el.tomselect.setValue(String(preserved), true);
    }
}

function rebuildModuleSectionTomSelect(tests, preserved) {
    const el = document.getElementById('moduleSection');
    if (!el) {
        return;
    }
    destroyTomSelectIfAny(el);
    el.innerHTML = '<option value="">Search section...</option>';
    tests.forEach(function (test) {
        (test.sections || []).forEach(function (section) {
            const opt = document.createElement('option');
            opt.value = section.id;
            opt.setAttribute('data-type', section.type);
            opt.textContent = test.title + ' - ' + section.name + ' (ID:' + section.id + ')';
            el.appendChild(opt);
        });
    });
    initTomSelectOn(el);
    if (preserved && optionExistsInSelect(el, preserved)) {
        el.tomselect.setValue(String(preserved), true);
    }
}

function rebuildQuestionModuleTomSelect(tests, preserved, selectId) {
    selectId = selectId || 'questionModule';
    const el = document.getElementById(selectId);
    if (!el) {
        return;
    }
    destroyTomSelectIfAny(el);
    el.innerHTML = '<option value="">Search module...</option>';
    tests.forEach(function (test) {
        (test.sections || []).forEach(function (section) {
            (section.modules || []).forEach(function (mod) {
                const opt = document.createElement('option');
                opt.value = mod.id;
                opt.setAttribute('data-section-type', section.type);
                opt.textContent = test.title + ' - ' + section.name + ' - Mod ' + mod.module_number + ' (' + humanizeUnderscores(mod.difficulty_level) + ')';
                el.appendChild(opt);
            });
        });
    });
    initTomSelectOn(el);
    if (preserved && optionExistsInSelect(el, preserved)) {
        el.tomselect.setValue(String(preserved), true);
        if (selectId === 'questionModule') {
            autoFetchSectionType(el);
        }
    }
}

function rebuildQuestionPassageTomSelect(passages, preserved) {
    const el = document.getElementById('questionPassage');
    if (!el) {
        return;
    }
    destroyTomSelectIfAny(el);
    el.innerHTML = '<option value="">No passage (Standalone) / Search passage...</option>';
    (passages || []).forEach(function (p) {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = stripTags(p.content || '').slice(0, 80) + (stripTags(p.content || '').length > 80 ? '…' : '');
        el.appendChild(opt);
    });
    initTomSelectOn(el);
    if (preserved && optionExistsInSelect(el, preserved)) {
        el.tomselect.setValue(String(preserved), true);
    }
}

function initRemoteQuestionPicker(selectId, preservedValue) {
    const el = document.getElementById(selectId);
    if (!el) {
        return;
    }
    destroyTomSelectIfAny(el);
    el.innerHTML = '<option value="">Search question (type to load)...</option>';
    const pinnedId = preservedValue != null && preservedValue !== '' ? String(preservedValue) : '';
    const ts = new TomSelect(el, {
        valueField: 'value',
        labelField: 'text',
        searchField: 'text',
        preload: 'focus',
        loadThrottle: 250,
        maxOptions: 50,
        create: false,
        load: function (query, callback) {
            const params = new URLSearchParams();
            params.set('q', query || '');
            if (pinnedId !== '') {
                params.set('id', pinnedId);
            }
            fetch(QUESTIONS_SEARCH_URL + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (j) { callback((j && j.data) ? j.data : []); })
                .catch(function () { callback(); });
        }
    });
    if (pinnedId !== '') {
        fetch(QUESTIONS_SEARCH_URL + '?q=&id=' + encodeURIComponent(pinnedId), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                (j.data || []).forEach(function (opt) {
                    ts.addOption(opt);
                });
                ts.setValue(pinnedId, true);
            })
            .catch(function () { /* ignore */ });
    }
}

function initRemoteQuestionPickers(preserve) {
    const p = preserve || {};
    initRemoteQuestionPicker('answerQuestionId', p.answerQuestionId);
    initRemoteQuestionPicker('explanationQuestionId', p.explanationQuestionId);
}

function questionsListFetchUrl() {
    let u;
    try {
        u = new URL(QUESTIONS_LIST_URL);
    } catch (e) {
        u = new URL(QUESTIONS_LIST_URL, window.location.origin);
    }
    u.searchParams.set('page', String(window.__tdQuestionsPage || 1));
    u.searchParams.set('per_page', String(window.__tdQuestionsPerPage || 25));
    if (window.__tdQuestionsQuery) {
        u.searchParams.set('q', window.__tdQuestionsQuery);
    }
    if (window.__tdQuestionsSection) {
        u.searchParams.set('section_type', window.__tdQuestionsSection);
    }
    if (window.__tdQuestionsModule) {
        u.searchParams.set('module_id', window.__tdQuestionsModule);
    }
    if (window.__tdQuestionsStatus !== undefined && window.__tdQuestionsStatus !== '') {
        u.searchParams.set('is_complete', window.__tdQuestionsStatus);
    }
    return u.toString();
}

function renderQuestionsPagination(meta) {
    const wrap = document.getElementById('questionsPoolPagination');
    if (!wrap) {
        return;
    }
    if (!meta || meta.total === 0) {
        wrap.innerHTML = '';
        return;
    }
    const cur = meta.current_page || 1;
    const last = meta.last_page || 1;
    const total = meta.total || 0;
    let html = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">';
    html += '<span class="small text-muted">Page ' + cur + ' of ' + last + ' · ' + total + ' questions</span>';
    html += '<div class="btn-group btn-group-sm">';
    html += '<button type="button" class="btn btn-outline-secondary" data-q-page="prev"' + (cur <= 1 ? ' disabled' : '') + '>Previous</button>';
    html += '<button type="button" class="btn btn-outline-secondary" data-q-page="next"' + (cur >= last ? ' disabled' : '') + '>Next</button>';
    html += '</div></div>';
    wrap.innerHTML = html;
}

function bindQuestionsPaginationOnce() {
    const wrap = document.getElementById('questionsPoolPagination');
    if (!wrap || wrap.dataset.bound === '1') {
        return;
    }
    wrap.dataset.bound = '1';
    wrap.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-q-page]');
        if (!btn || btn.disabled) {
            return;
        }
        const dir = btn.getAttribute('data-q-page');
        const cur = window.__tdQuestionsPage || 1;
        if (dir === 'prev') {
            window.__tdQuestionsPage = Math.max(1, cur - 1);
        } else if (dir === 'next') {
            window.__tdQuestionsPage = cur + 1;
        }
        try {
            await refreshQuestionsTableOnly();
        } catch (err) {
            showAlert('danger', err.message || 'Failed to load page');
        }
    });
}

async function refreshQuestionsTableOnly() {
    const response = await fetch(questionsListFetchUrl(), {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    });
    if (!response.ok) {
        throw new Error('Questions list failed (' + response.status + ')');
    }
    const listJson = await response.json();
    const last = listJson.last_page || 1;
    if ((listJson.current_page || 1) > last) {
        window.__tdQuestionsPage = last;
        return refreshQuestionsTableOnly();
    }
    renderQuestionsTable(listJson.data || []);
    renderQuestionsPagination(listJson);
    const qBadge = document.getElementById('questionsPoolCountBadge');
    if (qBadge && listJson.total != null) {
        qBadge.textContent = listJson.total + ' Total';
    }
}

function renderTestsTable(tests) {
    const tbody = document.getElementById('testsTableBody');
    if (!tbody) {
        return;
    }
    if (!tests.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No tests found</td></tr>';
        return;
    }
    tbody.innerHTML = tests.map(function (t) {
        const badge = t.status === 'active' ? 'success' : 'secondary';
        const draftSel = t.status === 'draft' ? ' selected' : '';
        const activeSel = t.status === 'active' ? ' selected' : '';
        const archSel = t.status === 'archived' ? ' selected' : '';
        return '<tr>'
            + '<td>' + escapeHtml(t.id) + '</td>'
            + '<td><strong>' + escapeHtml(t.title) + '</strong></td>'
            + '<td>' + escapeHtml(humanizeUnderscores(t.test_type)) + '</td>'
            + '<td><span class="badge bg-' + badge + '">' + escapeHtml(humanizeUnderscores(t.status)) + '</span></td>'
            + '<td>' + escapeHtml(t.total_duration_minutes) + 'm</td>'
            + '<td><div class="d-flex gap-2">'
            + '<select class="form-select form-select-sm status-select" data-test-id="' + escapeHtml(t.id) + '">'
            + '<option value="draft"' + draftSel + '>Draft</option>'
            + '<option value="active"' + activeSel + '>Active</option>'
            + '<option value="archived"' + archSel + '>Archived</option>'
            + '</select>'
            + '<button type="button" class="btn btn-sm btn-outline-danger delete-test-btn" data-id="' + escapeHtml(t.id) + '">Delete</button>'
            + '</div></td></tr>';
    }).join('');
}

function renderSectionsTable(tests) {
    const tbody = document.getElementById('sectionsTableBody');
    if (!tbody) {
        return;
    }
    const rows = [];
    tests.forEach(function (test) {
        (test.sections || []).forEach(function (section) {
            rows.push('<tr>'
                + '<td>' + escapeHtml(section.id) + '</td>'
                + '<td>' + escapeHtml(test.title) + '</td>'
                + '<td><strong>' + escapeHtml(section.name) + '</strong></td>'
                + '<td>' + escapeHtml(humanizeUnderscores(section.type)) + '</td>'
                + '<td>' + escapeHtml(section.order) + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-danger delete-section-btn" data-id="' + escapeHtml(section.id) + '">Delete</button></td>'
                + '</tr>');
        });
    });
    tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No sections yet</td></tr>';
}

function moduleDifficultyBadgeClass(level) {
    if (level === 'hard') {
        return 'danger';
    }
    if (level === 'easy') {
        return 'success';
    }
    return 'primary';
}

function renderModulesTable(tests) {
    const tbody = document.getElementById('modulesTableBody');
    if (!tbody) {
        return;
    }
    const rows = [];
    tests.forEach(function (test) {
        (test.sections || []).forEach(function (section) {
            (section.modules || []).forEach(function (mod) {
                const diffClass = moduleDifficultyBadgeClass(mod.difficulty_level);
                rows.push('<tr>'
                    + '<td>' + escapeHtml(mod.id) + '</td>'
                    + '<td><small>' + escapeHtml(test.title) + '</small><br><strong>' + escapeHtml(section.name) + '</strong></td>'
                    + '<td>' + escapeHtml(mod.module_number) + '</td>'
                    + '<td><span class="badge bg-secondary">' + escapeHtml(mod.order) + '</span></td>'
                    + '<td><span class="badge bg-' + diffClass + '">' + escapeHtml(humanizeUnderscores(mod.difficulty_level)) + '</span></td>'
                    + '<td>' + escapeHtml(mod.duration_minutes) + 'm</td>'
                    + '<td>' + escapeHtml(mod.total_questions) + '</td>'
                    + '<td><button type="button" class="btn btn-sm btn-outline-danger delete-module-btn" data-id="' + escapeHtml(mod.id) + '">Delete</button></td>'
                    + '</tr>');
            });
        });
    });
    tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No modules yet</td></tr>';
}

function renderQuestionsTable(questions) {
    const tbody = document.getElementById('questionsTableBody');
    if (!tbody) {
        return;
    }
    if (!questions.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No questions found</td></tr>';
        return;
    }
    tbody.innerHTML = questions.map(function (q) {
        const sec = q.section_type === 'reading_writing' ? 'R&W' : 'Math';
        const pre = q.is_pretest ? '<span class="text-danger">● Yes</span>' : 'No';
        const status = q.is_complete ? '' : ' <span class="badge bg-warning text-dark" title="Missing Domain or Difficulty">Incomplete</span>';
        const stem = stripTags(q.stem || '');
        const snippet = stem.length <= 40 ? stem : stem.slice(0, 40) + '…';
        const qNum = q.question_number != null ? '<strong>' + q.question_number + '</strong>' : '-';
        return '<tr>'
            + '<td>' + escapeHtml(q.id) + '</td>'
            + '<td>' + qNum + status + '</td>'
            + '<td><small>' + escapeHtml(sec) + '</small></td>'
            + '<td>' + escapeHtml(snippet) + '</td>'
            + '<td>' + pre + '</td>'
            + '<td><small>' + escapeHtml(q.skill_domain || '') + '</small></td>'
            + '<td><small class="badge bg-light text-dark border">' + escapeHtml(capitalizeFirstLetter(q.difficulty || '')) + '</small></td>'
            + '<td>'
            + '<div class="d-flex gap-1">'
            + '<button type="button" class="btn btn-sm btn-outline-primary edit-question-btn" data-id="' + escapeHtml(q.id) + '">Edit</button>'
            + '<button type="button" class="btn btn-sm btn-outline-danger delete-question-btn" data-id="' + escapeHtml(q.id) + '">×</button>'
            + '</div>'
            + '</td>'
            + '</tr>';
    }).join('');
}

function rebuildAllTomSelects(payload, preserve) {
    const p = preserve || {};
    const tests = payload.tests || [];
    const passages = payload.passages || [];
    rebuildSectionTestTomSelect(tests, p.sectionTest);
    rebuildModuleSectionTomSelect(tests, p.moduleSection);
    rebuildQuestionModuleTomSelect(tests, p.questionModule, 'questionModule');
    rebuildQuestionModuleTomSelect(tests, p.bulkQuestionModule, 'bulkQuestionModule');
    rebuildQuestionPassageTomSelect(passages, p.questionPassage);
    initRemoteQuestionPickers(p);
}

async function refreshTestDashboardData(preserveTomSelects) {
    const [snapRes, listRes] = await Promise.all([
        fetch(TEST_DASHBOARD_SNAPSHOT_URL, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }),
        fetch(questionsListFetchUrl(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
    ]);
    if (!snapRes.ok) {
        throw new Error('Snapshot request failed (' + snapRes.status + ')');
    }
    let payload = { tests: [], passages: [] };
    try {
        payload = await snapRes.json();
    } catch (e) {
        console.error('Snapshot JSON parse failed');
    }

    renderTestsTable(payload.tests || []);
    renderSectionsTable(payload.tests || []);
    renderModulesTable(payload.tests || []);
    let listJson = { data: [], total: 0, current_page: 1, last_page: 1 };
    if (listRes.ok) {
        try {
            listJson = await listRes.json();
        } catch (e) { /* ignore */ }
    }
    const last = listJson.last_page || 1;
    if ((listJson.current_page || 1) > last) {
        window.__tdQuestionsPage = last;
        await refreshQuestionsTableOnly();
    } else {
        renderQuestionsTable(listJson.data || []);
        renderQuestionsPagination(listJson);
        const qBadge = document.getElementById('questionsPoolCountBadge');
        if (qBadge && listJson.total != null) {
            qBadge.textContent = listJson.total + ' Total';
        }
    }
    rebuildAllTomSelects(payload, preserveTomSelects);
}

function initTestDashboardDelegatedActions() {
    const root = document.getElementById('dashboardTabContent');
    if (!root || root.dataset.delegatedActionsBound === '1') {
        return;
    }
    root.dataset.delegatedActionsBound = '1';

    root.addEventListener('change', function (e) {
        const sel = e.target.closest('select.status-select[data-test-id]');
        if (!sel) {
            return;
        }
        updateTestStatus(sel.getAttribute('data-test-id'), sel.value);
    });

    root.addEventListener('click', async function (e) {
        const btn = e.target.closest('.delete-test-btn, .delete-section-btn, .delete-module-btn, .delete-question-btn, .edit-question-btn');
        if (!btn) {
            return;
        }

        const id = btn.getAttribute('data-id');

        if (btn.classList.contains('edit-question-btn')) {
            openEditQuestionModal(id);
            return;
        }

        if (!confirm('Permanently delete this item?')) {
            return;
        }
        let url;
        if (btn.classList.contains('delete-test-btn')) {
            url = @json(url('test-dashboard/tests')) + '/' + encodeURIComponent(id);
        } else if (btn.classList.contains('delete-section-btn')) {
            url = @json(url('test-dashboard/sections')) + '/' + encodeURIComponent(id);
        } else if (btn.classList.contains('delete-module-btn')) {
            url = @json(url('test-dashboard/modules')) + '/' + encodeURIComponent(id);
        } else if (btn.classList.contains('delete-question-btn')) {
            url = @json(url('test-dashboard/questions')) + '/' + encodeURIComponent(id);
        } else {
            return;
        }
        const preserve = captureTomSelectPreservation(null);
        try {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            if (response.ok) {
                showAlert('success', 'Deleted successfully');
                await refreshTestDashboardData(preserve);
            } else {
                let msg = 'Delete failed';
                try {
                    const j = await response.json();
                    msg = j.message || msg;
                } catch (err) { /* ignore */ }
                showAlert('danger', msg);
            }
        } catch (error) {
            showAlert('danger', 'Error: ' + error.message);
        }
    });
}

async function openEditQuestionModal(id) {
    try {
        const response = await fetch(`{{ url('test-dashboard/questions') }}/${id}`);
        if (!response.ok) throw new Error('Failed to fetch question data');
        const result = await response.json();
        const question = result.data;

        document.getElementById('editQuestionId').value = question.id;
        document.getElementById('editQuestionIdDisplay').textContent = question.id;
        document.getElementById('editQuestionStem').value = question.stem;
        document.getElementById('editQuestionType').value = question.question_type;
        document.getElementById('editDifficulty').value = question.difficulty || '';
        document.getElementById('editSkillSubdomain').value = question.skill_subdomain || '';
        document.getElementById('editSprHint').value = question.spr_hint || '';
        document.getElementById('editIsPretest').checked = !!question.is_pretest;
        document.getElementById('editCalculatorAllowed').checked = !!question.calculator_allowed;

        const sprHintContainer = document.getElementById('editSprHintContainer');
        if (question.section_type === 'reading_writing') {
            sprHintContainer.classList.add('d-none');
        } else {
            sprHintContainer.classList.remove('d-none');
        }

        const domainSelect = document.getElementById('editSkillDomain');
        domainSelect.innerHTML = '<option value="">Select domain...</option>';
        if (SKILL_DOMAINS[question.section_type]) {
            SKILL_DOMAINS[question.section_type].forEach(domain => {
                const opt = document.createElement('option');
                opt.value = domain.value;
                opt.textContent = domain.label;
                if (domain.value === question.skill_domain) opt.selected = true;
                domainSelect.appendChild(opt);
            });
        }

        const passageContainer = document.getElementById('editPassageContainer');
        if (question.section_type === 'reading_writing' && question.passage) {
            passageContainer.classList.remove('d-none');
            document.getElementById('editPassageContent').value = question.passage.content;
        } else {
            passageContainer.classList.add('d-none');
            document.getElementById('editPassageContent').value = '';
        }

        const modal = new bootstrap.Modal(document.getElementById('editQuestionModal'));
        
        // Populate Choices
        if (question.question_type === 'multiple_choice') {
            document.getElementById('editMcqChoicesContainer').classList.remove('d-none');
            document.getElementById('editSprAnswersContainer').classList.add('d-none');
            // Clear choices first
            ['A','B','C','D'].forEach(lbl => {
                const ci = document.getElementById(`editChoice${lbl}Content`);
                const cr = document.getElementById(`editChoice${lbl}Correct`);
                if(ci) ci.value = '';
                if(cr) cr.checked = false;
            });
            const rawChoices = question.answer_choices || question.answerChoices || [];
            rawChoices.forEach(choice => {
                const contentInput = document.getElementById(`editChoice${choice.label}Content`);
                const correctRadio = document.getElementById(`editChoice${choice.label}Correct`);
                if (contentInput) contentInput.value = choice.content;
                if (correctRadio && (choice.is_correct || choice.is_correct === 1)) correctRadio.checked = true;
            });
        } else {
            document.getElementById('editMcqChoicesContainer').classList.add('d-none');
            document.getElementById('editSprAnswersContainer').classList.remove('d-none');
            const answers = (question.spr_correct_answers || []).map(a => a.answer).join(', ');
            document.getElementById('editSprAnswers').value = answers;
        }

        // Populate Explanation
        if (question.explanation) {
            document.getElementById('editExplanation').value = question.explanation.explanation;
            document.getElementById('editRationaleA').value = question.explanation.rationale_a || '';
            document.getElementById('editRationaleB').value = question.explanation.rationale_b || '';
            document.getElementById('editRationaleC').value = question.explanation.rationale_c || '';
            document.getElementById('editRationaleD').value = question.explanation.rationale_d || '';
        } else {
            document.getElementById('editExplanation').value = '';
            document.getElementById('editRationaleA').value = '';
            document.getElementById('editRationaleB').value = '';
            document.getElementById('editRationaleC').value = '';
            document.getElementById('editRationaleD').value = '';
        }

        // Populate Media List
        refreshEditMediaList(question);

        modal.show();
    } catch (error) {
        showAlert('danger', error.message);
    }
}

    function refreshEditMediaList() {
        const mediaList = document.getElementById('editMediaList');
        if (!mediaList) return;
        mediaList.innerHTML = '';

        const fields = [
            document.getElementById('editQuestionStem')?.value || '',
            document.getElementById('editPassageContent')?.value || '',
            document.getElementById('editExplanation')?.value || '',
            document.getElementById('editRationaleA')?.value || '',
            document.getElementById('editRationaleB')?.value || '',
            document.getElementById('editRationaleC')?.value || '',
            document.getElementById('editRationaleD')?.value || ''
        ];
        
        ['A', 'B', 'C', 'D'].forEach(lbl => {
            const el = document.getElementById(`editChoice${lbl}Content`);
            if (el) fields.push(el.value);
        });

        const allText = fields.join(' ');
        // Improved Regex for Markdown images and Placeholders
        const mdRegex = /!\[.*?\]\((.*?)\)/g;
        const placeholderRegex = /\[Media:([^\]]+)\]/gi;
        
        const foundUrls = new Set();
        const foundPlaceholders = new Set();
        
        let match;
        while ((match = mdRegex.exec(allText)) !== null) {
            foundUrls.add(match[1].trim());
        }
        while ((match = placeholderRegex.exec(allText)) !== null) {
            foundPlaceholders.add(match[1].trim());
        }

        if (foundUrls.size === 0 && foundPlaceholders.size === 0) {
            mediaList.innerHTML = '<div class="col-12 text-muted small">No media found in this question.</div>';
        } else {
            foundUrls.forEach(url => {
                const col = document.createElement('div');
                col.className = 'col-md-3 col-6 mb-2';
                col.innerHTML = `
                    <div class="position-relative border rounded p-1 bg-white shadow-sm d-flex align-items-center justify-content-center" style="height: 100px;">
                        <img src="${url}" class="img-fluid rounded" style="max-height: 90px; object-fit: contain;" onerror="this.src='https://placehold.co/100x100?text=Error'">
                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 rounded-circle shadow-sm d-flex align-items-center justify-content-center" 
                                style="width: 24px; height: 24px; margin: -10px -10px 0 0; z-index: 10;"
                                onclick="removeMediaFromEditModal('${url}', true)" title="Remove from all fields">
                            &times;
                        </button>
                    </div>
                `;
                mediaList.appendChild(col);
            });
            
            foundPlaceholders.forEach(filename => {
                const col = document.createElement('div');
                col.className = 'col-md-3 col-6 mb-2';
                // Try to resolve placeholder to a predicted storage URL for preview
                const predictedUrl = `/storage/media/${filename}`;
                col.innerHTML = `
                    <div class="position-relative border rounded p-1 bg-light shadow-sm d-flex flex-column align-items-center justify-content-center" style="height: 100px;">
                        <img src="${predictedUrl}" class="img-fluid rounded mb-1" style="max-height: 60px; object-fit: contain;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                        <div class="text-center" style="display:none;">
                            <i class="bi bi-file-earmark-image text-muted" style="font-size: 1.5rem;"></i>
                            <div class="x-small text-truncate px-1" style="max-width: 80px;">${filename}</div>
                        </div>
                        <div class="x-small text-muted text-center fw-bold">Placeholder</div>
                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 rounded-circle shadow-sm d-flex align-items-center justify-content-center" 
                                style="width: 24px; height: 24px; margin: -10px -10px 0 0; z-index: 10;"
                                onclick="removeMediaFromEditModal('${filename}', false)" title="Remove from all fields">
                            &times;
                        </button>
                    </div>
                `;
                mediaList.appendChild(col);
            });
        }
    }

window.removeMediaFromEditModal = function(identifier, isUrl) {
    if (!confirm('Are you sure you want to remove this media from all fields?')) return;
    
    const textareas = [
        'editQuestionStem', 'editPassageContent', 'editExplanation',
        'editRationaleA', 'editRationaleB', 'editRationaleC', 'editRationaleD'
    ];
    
    const escaped = identifier.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = isUrl ? new RegExp(`!\\[.*?\\]\\(\\s*${escaped}\\s*\\)`, 'g') : new RegExp(`\\[Media:\\s*${escaped}\\s*\\]`, 'gi');
    
    textareas.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = el.value.replace(regex, '').trim();
    });

    ['A', 'B', 'C', 'D'].forEach(lbl => {
        const el = document.getElementById(`editChoice${lbl}Content`);
        if (el) el.value = el.value.replace(regex, '').trim();
    });

    refreshEditMediaList();
};

document.getElementById('editQuestionMediaUpload')?.addEventListener('change', async function(e) {
    if (!this.files || !this.files.length) return;
    
    const formData = new FormData();
    formData.append('image', this.files[0]);

    try {
        const response = await fetch('{{ route('test-dashboard.media.upload') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: formData
        });
        const result = await response.json();
        if (response.ok) {
            const uploadedFilename = this.files[0].name.trim();
            const markdown = result.markdown;
            
            const textareas = [
                'editQuestionStem', 'editPassageContent', 'editExplanation',
                'editRationaleA', 'editRationaleB', 'editRationaleC', 'editRationaleD'
            ];
            
            let replacedAny = false;
            const placeholderRegex = new RegExp(`\\[Media:\\s*${uploadedFilename.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\]`, 'gi');

            textareas.forEach(id => {
                const el = document.getElementById(id);
                if (el && placeholderRegex.test(el.value)) {
                    el.value = el.value.replace(placeholderRegex, markdown);
                    replacedAny = true;
                }
            });

            ['A', 'B', 'C', 'D'].forEach(lbl => {
                const el = document.getElementById(`editChoice${lbl}Content`);
                if (el && placeholderRegex.test(el.value)) {
                    el.value = el.value.replace(placeholderRegex, markdown);
                    replacedAny = true;
                }
            });

            if (!replacedAny) {
                // Fallback: append to stem if no placeholder matched the filename
                const stem = document.getElementById('editQuestionStem');
                stem.value += '\n' + markdown;
            }

            refreshEditMediaList();
            showAlert('success', replacedAny ? 'Placeholder replaced with image' : 'Media uploaded and inserted into stem');
        } else {
            showAlert('danger', result.message || 'Upload failed');
        }
    } catch (error) {
        showAlert('danger', error.message);
    } finally {
        this.value = '';
    }
});

document.getElementById('editQuestionForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('editQuestionId').value;
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    // Manually handle nested choices array
    if (data.question_type === 'multiple_choice') {
        data.choices = [];
        ['A', 'B', 'C', 'D'].forEach((label, index) => {
            const content = document.getElementById(`editChoice${label}Content`).value;
            data.choices.push({
                label: label,
                content: content,
                order: index + 1
            });
        });
    }

    // Handle checkboxes
    data.is_pretest = document.getElementById('editIsPretest').checked ? 1 : 0;
    data.calculator_allowed = document.getElementById('editCalculatorAllowed').checked ? 1 : 0;

    try {
        const response = await fetch(`{{ url('test-dashboard/questions') }}/${id}`, {
            method: 'POST', // Using POST with _method=PUT in formData/Object
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        if (response.ok) {
            showAlert('success', 'Question updated successfully');
            bootstrap.Modal.getInstance(document.getElementById('editQuestionModal')).hide();
            await refreshQuestionsTableOnly();
        } else {
            showAlert('danger', result.message || 'Update failed');
        }
    } catch (error) {
        showAlert('danger', error.message);
    }
});

function updateSectionName(select) {
    const nameInput = document.getElementById('sectionName');
    if (select.value === 'reading_writing') {
        nameInput.value = 'Reading and Writing';
    } else if (select.value === 'math') {
        nameInput.value = 'Math';
    }
}

function autoFetchSectionType(select) {
    const selectedOption = select.options[select.selectedIndex];
    const sectionType = selectedOption.getAttribute('data-section-type');
    const sectionTypeSelect = document.getElementById('qSectionType');
    
    if (sectionType) {
        sectionTypeSelect.value = sectionType;
        updateSkillDomains(sectionTypeSelect);
    }
}

async function updateTestStatus(testId, status) {
    const preserve = captureTomSelectPreservation(null);
    try {
        const response = await fetch(`{{ url('test-dashboard/tests') }}/${testId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ status: status })
        });

        if (response.ok) {
            showAlert('success', 'Status updated!');
            try {
                await refreshTestDashboardData(preserve);
            } catch (err) {
                rememberTestDashboardTab();
                window.location.reload();
            }
        } else {
            const res = await response.json();
            showAlert('danger', res.message || 'Failed to update status');
        }
    } catch (error) {
        showAlert('danger', 'Error: ' + error.message);
    }
}

function applyModuleDefaults(select) {
    const selectedOption = select.options[select.selectedIndex];
    const type = selectedOption.getAttribute('data-type');
    const durationInput = document.getElementById('moduleDuration');
    const questionsInput = document.getElementById('totalQuestions');

    if (type === 'reading_writing') {
        durationInput.value = 32;
        questionsInput.value = 27;
    } else if (type === 'math') {
        durationInput.value = 35;
        questionsInput.value = 22;
    }
}

function updateSkillDomains(select) {
    const domainSelect = document.getElementById('skillDomain');
    const type = select.value;

    const questionTypeSelect = document.getElementById('questionType');
    const sprOption = questionTypeSelect.querySelector('option[value="student_produced_response"]');
    const sprHintContainer = document.getElementById('sprHintContainer');

    if (type === 'reading_writing') {
        if (questionTypeSelect.value === 'student_produced_response') {
            questionTypeSelect.value = 'multiple_choice';
        }
        sprOption.style.display = 'none';
        if (sprHintContainer) sprHintContainer.classList.add('d-none');
    } else {
        sprOption.style.display = 'block';
        if (sprHintContainer) sprHintContainer.classList.remove('d-none');
    }

    domainSelect.innerHTML = '<option value="">Select domain...</option>';

    if (type && SKILL_DOMAINS[type]) {
        SKILL_DOMAINS[type].forEach(domain => {
            const opt = document.createElement('option');
            opt.value = domain.value;
            opt.textContent = domain.label;
            domainSelect.appendChild(opt);
        });
    }
}
document.addEventListener('DOMContentLoaded', function() {
    initTestDashboardDelegatedActions();

    document.querySelectorAll('#dashboardTabs [data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function (e) {
            const target = e.target.getAttribute('data-bs-target');
            if (target) {
                sessionStorage.setItem(TEST_DASHBOARD_TAB_KEY, target);
            }
        });
    });

    // Initialize Tom Select (remote question pickers are initialized separately)
    document.querySelectorAll('.tom-select').forEach(function (el) {
        if (el.classList.contains('tom-select-remote-question')) {
            return;
        }
        new TomSelect(el, {
            create: false,
            sortField: { field: 'text', order: 'asc' }
        });
    });
    initRemoteQuestionPickers({});
    bindQuestionsPaginationOnce();

    document.getElementById('questionsTableFilterBtn')?.addEventListener('click', async function () {
        window.__tdQuestionsQuery = (document.getElementById('questionsTableFilter')?.value || '').trim();
        window.__tdQuestionsSection = document.getElementById('questionsTableSectionFilter')?.value || '';
        window.__tdQuestionsModule = document.getElementById('questionsTableModuleFilter')?.value || '';
        window.__tdQuestionsStatus = document.getElementById('questionsTableStatusFilter')?.value || '';
        window.__tdQuestionsPage = 1;
        try {
            await refreshQuestionsTableOnly();
        } catch (err) {
            showAlert('danger', err.message || 'Failed to filter');
        }
    });
    document.getElementById('questionsTableFilterClearBtn')?.addEventListener('click', async function () {
        const inp = document.getElementById('questionsTableFilter');
        if (inp) inp.value = '';
        const sec = document.getElementById('questionsTableSectionFilter');
        if (sec) sec.value = '';
        const mod = document.getElementById('questionsTableModuleFilter');
        if (mod) mod.value = '';
        const stat = document.getElementById('questionsTableStatusFilter');
        if (stat) stat.value = '';
        window.__tdQuestionsQuery = '';
        window.__tdQuestionsSection = '';
        window.__tdQuestionsModule = '';
        window.__tdQuestionsStatus = '';
        window.__tdQuestionsPage = 1;
        try {
            await refreshQuestionsTableOnly();
        } catch (err) {
            showAlert('danger', err.message || 'Failed to clear filter');
        }
    });

    // Initialize EasyMDE for bulk import payload if needed (optional)
    // For now we just remove the specific ones for the deleted forms
    window.__testDashboardEditors = {};

    const savedTab = sessionStorage.getItem(TEST_DASHBOARD_TAB_KEY);
    if (savedTab) {
        const trigger = document.querySelector('#dashboardTabs [data-bs-target="' + savedTab + '"]');
        if (trigger && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(trigger).show();
        }
    }

    setupForm('testForm', '{{ route('test-dashboard.tests.store') }}');
    setupForm('sectionForm', '{{ route('test-dashboard.sections.store') }}');
    setupForm('moduleForm', '{{ route('test-dashboard.modules.store') }}');
    setupForm('attachQuestionForm', '{{ route('test-dashboard.questions.attach') }}');

    const BULK_QUESTIONS_URL = @json(route('test-dashboard.questions.bulk-store'));
    const bulkJsonExampleRw = {
        items: [
            {
                passage: {
                    content: 'The researcher noted that early observations were incomplete, yet they shaped every later hypothesis. Later teams revisited the same data and drew different conclusions—without discarding the value of the first pass.\n\nWhich choice best describes the main idea of the text?',
                    passage_type: 'single',
                    genre: 'natural_science',
                    source_title: 'Field notes (fictional sample)',
                    word_count: 52
                },
                stem: 'Which choice best describes the **main idea** of the text?',
                question_type: 'multiple_choice',
                difficulty: 'medium',
                skill_domain: 'information_and_ideas',
                choices: [
                    { label: 'A', content: 'Early observations were useless.', is_correct: false },
                    { label: 'B', content: 'Initial incomplete work still influenced later science.', is_correct: true },
                    { label: 'C', content: 'Later teams refused to use older data.', is_correct: false },
                    { label: 'D', content: 'Hypotheses are never revised.', is_correct: false }
                ],
                explanation: 'The passage stresses that early incomplete observations still shaped later hypotheses.'
            }
        ]
    };
    const bulkJsonExampleMath = {
        items: [
            {
                stem: 'What is **2 + 2**?',
                question_type: 'multiple_choice',
                difficulty: 'easy',
                skill_domain: 'algebra',
                choices: [
                    { label: 'A', content: '3', is_correct: false },
                    { label: 'B', content: '4', is_correct: true },
                    { label: 'C', content: '5', is_correct: false },
                    { label: 'D', content: '6', is_correct: false }
                ],
                explanation: 'The sum of 2 and 2 is 4.'
            },
            {
                stem: 'If $x^2 = 9$, what is the **positive** value of $x$?',
                question_type: 'student_produced_response',
                difficulty: 'medium',
                skill_domain: 'advanced_math',
                spr_correct_answers: ['3'],
                spr_hint: 'Enter a positive number only.',
                explanation: 'The positive square root of 9 is 3.'
            }
        ]
    };

    function setBulkQuestionsJson(obj) {
        const ta = document.getElementById('bulkQuestionsJson');
        if (ta) {
            ta.value = JSON.stringify(obj, null, 2);
        }
    }

    function downloadJsonFile(filename, obj) {
        const blob = new Blob([JSON.stringify(obj, null, 2)], { type: 'application/json;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    document.getElementById('bulkJsonFile')?.addEventListener('change', function (e) {
        const f = e.target.files && e.target.files[0];
        const ta = document.getElementById('bulkQuestionsJson');
        if (!f || !ta) {
            return;
        }
        const reader = new FileReader();
        reader.onload = function () {
            try {
                const parsed = JSON.parse(reader.result);
                ta.value = JSON.stringify(parsed, null, 2);
            } catch (err) {
                showAlert('warning', 'Could not parse file for preview: ' + err.message);
            }
        };
        reader.onerror = function () {
            showAlert('danger', 'Could not read the selected file.');
        };
        reader.readAsText(f, 'UTF-8');
    });

    document.getElementById('bulkLoadExampleRwBtn')?.addEventListener('click', function () {
        setBulkQuestionsJson(bulkJsonExampleRw);
    });
    document.getElementById('bulkLoadExampleMathBtn')?.addEventListener('click', function () {
        setBulkQuestionsJson(bulkJsonExampleMath);
    });
    document.getElementById('bulkDownloadRwSampleBtn')?.addEventListener('click', function () {
        downloadJsonFile('bulk-sample-reading-writing.json', bulkJsonExampleRw);
    });
    document.getElementById('bulkDownloadMathSampleBtn')?.addEventListener('click', function () {
        downloadJsonFile('bulk-sample-math.json', bulkJsonExampleMath);
    });

    function csvEscapeCell(val) {
        const s = String(val);
        if (/[",\n\r]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function downloadCsvFile(filename, rows) {
        const lines = rows.map(function (row) {
            return row.map(csvEscapeCell).join(',');
        });
        const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    const bulkCsvSampleRwHeader = [
        'question_type', 'difficulty', 'skill_domain', 'stem', 'passage_content', 'passage_genre',
        'correct_choice', 'choice_a_content', 'choice_b_content', 'choice_c_content', 'choice_d_content', 'explanation'
    ];
    const bulkCsvSampleRwRow = [
        'multiple_choice',
        'medium',
        'information_and_ideas',
        'Which choice best describes the **main idea** of the text?',
        '<p>The researcher noted that early observations were incomplete, yet they shaped every later hypothesis.</p>',
        'natural_science',
        'B',
        'Early observations were useless.',
        'Initial incomplete work still influenced later science.',
        'Later teams refused to use older data.',
        'Hypotheses are never revised.',
        'The passage stresses that early incomplete observations still shaped later hypotheses.'
    ];
    const bulkCsvSampleMathHeader = [
        'question_type', 'difficulty', 'skill_domain', 'stem',
        'correct_choice', 'choice_a_content', 'choice_b_content', 'choice_c_content', 'choice_d_content', 'explanation',
        'spr_correct_answers', 'spr_hint'
    ];
    // Example row for Math (MCQ then SPR columns)
    const bulkCsvSampleMathRowMcq = [
        'multiple_choice', 'easy', 'algebra', 'What is **2 + 2**?',
        'B', '3', '4', '5', '6', 'Sum is 4.', '', ''
    ];
    const bulkCsvSampleMathRowSpr = [
        'student_produced_response', 'medium', 'advanced_math', 'If $x^2 = 9$, what is the **positive** value of $x$?',
        '', '', '', '', '', '', '3|3.0', 'The positive square root of 9 is 3.'
    ];

    document.getElementById('bulkDownloadRwSampleCsvBtn')?.addEventListener('click', function () {
        downloadCsvFile('bulk-sample-reading-writing.csv', [bulkCsvSampleRwHeader, bulkCsvSampleRwRow]);
    });
    document.getElementById('bulkDownloadMathSampleCsvBtn')?.addEventListener('click', function () {
        const hdr = [
            'question_type', 'difficulty', 'skill_domain', 'stem',
            'correct_choice', 'choice_a_content', 'choice_b_content', 'choice_c_content', 'choice_d_content', 'explanation',
            'spr_correct_answers', 'spr_hint'
        ];
        downloadCsvFile('bulk-sample-math.csv', [
            hdr,
            ['multiple_choice', 'easy', 'algebra', 'What is 2 + 2?', 'B', '3', '4', '5', '6', 'Sum is 4.', '', ''],
            ['student_produced_response', 'medium', 'advanced_math', 'If x^2 = 9, positive x?', '', '', '', '', '', '', '3|3.0', 'One positive number']
        ]);
    });

    const previewModal = new bootstrap.Modal(document.getElementById('importPreviewModal'));

    function renderPreview(items) {
        const container = document.getElementById('previewContent');
        if (!items || !items.length) {
            container.innerHTML = '<div class="alert alert-warning">No items found to preview.</div>';
            return;
        }

        const processMedia = (text) => {
            if (!text || typeof text !== 'string') return text;
            // Only replace if it's NOT already part of a Markdown image (![](...))
            return text.replace(/(?<!\!)\[Media:([^\]]+)\]/gi, (match, filename) => {
                return `<img src="/storage/media/${filename}" alt="${filename}" class="question-media img-fluid mb-2 d-block mx-auto" style="max-height: 300px;">`;
            });
        };

        let html = '';
        items.forEach((item, index) => {
            // Process media in all text fields
            item.stem = processMedia(item.stem);
            if (item.passage) {
                if (typeof item.passage === 'string') {
                    item.passage = processMedia(item.passage);
                } else if (item.passage.content) {
                    item.passage.content = processMedia(item.passage.content);
                }
            }
            if (item.choices) {
                item.choices.forEach(c => {
                    if (c.content) c.content = processMedia(c.content);
                });
            }
            if (item.explanation) item.explanation = processMedia(item.explanation);

            const sectionBadge = item.section_type 
                ? `<span class="badge bg-secondary">${item.section_type === 'reading_writing' ? 'Reading & Writing' : 'Math'}</span>` 
                : '';
            
            html += `
                <div class="card mb-4 shadow-sm border-info">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-info text-dark">Item ${index + 1}</span>
                            ${sectionBadge}
                            <span class="badge bg-primary">${humanizeUnderscores(item.skill_domain)}</span>
                            ${item.skill_subdomain ? `<span class="badge bg-outline-primary border border-primary text-primary">${item.skill_subdomain}</span>` : ''}
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-dark">${humanizeUnderscores(item.difficulty)}</span>
                            ${item.is_pretest ? '<span class="badge bg-danger">Pretest</span>' : ''}
                            ${item.external_id ? `<small class="text-muted">ID: ${item.external_id}</small>` : ''}
                        </div>
                    </div>
                    <div class="card-body">
                        ${item.passage ? `
                            <div class="p-3 mb-3 bg-white border-start border-4 border-info rounded-end shadow-sm">
                                <h6 class="text-info mb-2"><i class="bi bi-justify-left"></i> Passage</h6>
                                <div class="passage-content small" style="max-height: 200px; overflow-y: auto;">
                                    ${item.passage.content || item.passage}
                                </div>
                                ${item.passage.source_title ? `<div class="mt-2 text-muted x-small">Source: ${item.passage.source_title}</div>` : ''}
                            </div>
                        ` : ''}

                        <div class="mb-4">
                            <h6 class="fw-bold mb-2">Question Stem:</h6>
                            <div class="p-3 bg-white border rounded shadow-sm">
                                ${item.stem}
                            </div>
                        </div>
                        
                        ${item.choices ? `
                            <h6 class="fw-bold mb-2">Choices:</h6>
                            <div class="row g-3 mb-4">
                                ${item.choices.map(c => `
                                    <div class="col-md-6">
                                        <div class="p-3 border rounded h-100 ${c.is_correct ? 'bg-success bg-opacity-10 border-success shadow-sm' : 'bg-white'}">
                                            <div class="d-flex align-items-center gap-2">
                                                <strong class="rounded-circle bg-light border px-2 py-1">${c.label}</strong>
                                                <div class="flex-grow-1">${c.content}</div>
                                                ${c.is_correct ? '<i class="bi bi-check-circle-fill text-success"></i>' : ''}
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}

                        ${item.spr_correct_answers ? `
                            <div class="mb-4">
                                <h6 class="fw-bold mb-2">Accepted Answers (SPR):</h6>
                                <div class="p-3 bg-success bg-opacity-10 border border-success rounded d-flex flex-wrap gap-2">
                                    ${item.spr_correct_answers.map(ans => `<span class="badge bg-success">${ans}</span>`).join('')}
                                </div>
                                ${item.spr_hint ? `<div class="mt-1 small text-muted italic">Hint: ${item.spr_hint}</div>` : ''}
                            </div>
                        ` : ''}

                        ${item.explanation ? `
                            <div class="mt-3 p-3 bg-light border rounded small">
                                <h6 class="fw-bold text-muted mb-2"><i class="bi bi-info-circle"></i> Explanation:</h6>
                                <div class="text-muted">${item.explanation}</div>
                                ${(item.rationale_a || item.rationale_b || item.rationale_c || item.rationale_d) ? `
                                    <div class="row mt-2 g-2">
                                        ${item.rationale_a ? `<div class="col-md-6"><small><strong>A:</strong> ${item.rationale_a}</small></div>` : ''}
                                        ${item.rationale_b ? `<div class="col-md-6"><small><strong>B:</strong> ${item.rationale_b}</small></div>` : ''}
                                        ${item.rationale_c ? `<div class="col-md-6"><small><strong>C:</strong> ${item.rationale_c}</small></div>` : ''}
                                        ${item.rationale_d ? `<div class="col-md-6"><small><strong>D:</strong> ${item.rationale_d}</small></div>` : ''}
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
        previewModal.show();
        
        // Trigger KaTeX rendering for preview content
        if (window.renderMathInElement) {
            window.renderMathInElement(container, {
                delimiters: [
                    {left: "$$", right: "$$", display: true},
                    {left: "$", right: "$", display: false},
                    {left: "\\(", right: "\\)", display: false},
                    {left: "\\[", right: "\\]", display: true}
                ],
                throwOnError : false
            });
        }
    }

    async function handlePreview(isCsv) {
        const fileInput = document.getElementById(isCsv ? 'bulkCsvFile' : 'bulkJsonFile');
        const file = fileInput && fileInput.files && fileInput.files.length ? fileInput.files[0] : null;
        const ta = document.getElementById('bulkQuestionsJson');
        const url = isCsv ? CSV_BULK_PREVIEW_URL : BULK_PREVIEW_URL;
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
        };

        const formModule = getTomSelectValue('bulkQuestionModule');
        const formStart = document.getElementById('bulkStartPosition')?.value;

        if (!formModule) {
            showAlert('danger', 'Please select a Target Module in STEP 1 before previewing.');
            return;
        }

        try {
            let response;
            if (file) {
                const fd = new FormData();
                fd.append(isCsv ? 'csv_file' : 'json_file', file);
                if (formModule) fd.append('module_id', formModule);
                if (formStart) fd.append('start_position', formStart);

                response = await fetch(url, {
                    method: 'POST',
                    headers: headers,
                    body: fd
                });
            } else if (!isCsv && ta && ta.value.trim()) {
                let parsed;
                try {
                    parsed = JSON.parse(ta.value.trim());
                } catch (e) {
                    showAlert('danger', 'Invalid JSON: ' + e.message);
                    return;
                }
                const payload = Array.isArray(parsed) ? { items: parsed } : parsed;
                if (formModule && !payload.module_id) payload.module_id = formModule;
                if (formStart && !payload.start_position) payload.start_position = formStart;

                response = await fetch(url, {
                    method: 'POST',
                    headers: Object.assign({ 'Content-Type': 'application/json' }, headers),
                    body: JSON.stringify(payload)
                });
            } else {
                showAlert('danger', isCsv ? 'Select a CSV file first.' : 'Select a JSON file or enter JSON content.');
                return;
            }

            const resultText = await response.text();
            let result;
            try {
                result = JSON.parse(resultText);
            } catch (e) {
                showAlert('danger', 'Server returned invalid JSON. Status: ' + response.status);
                console.error('Invalid JSON response:', resultText);
                return;
            }

            if (response.ok) {
                renderPreview(result.data.items);
            } else {
                let msg = result.message || 'Preview failed';
                if (result.errors && typeof result.errors === 'object') {
                    const parts = Object.values(result.errors).flat();
                    if (parts.length) {
                        msg = parts.join('<br>');
                    }
                }
                showAlert('danger', msg);
            }
        } catch (error) {
            showAlert('danger', 'Error: ' + error.message);
        }
    }

    document.getElementById('bulkPreviewBtn')?.addEventListener('click', () => handlePreview(false));
    document.getElementById('bulkCsvPreviewBtn')?.addEventListener('click', () => handlePreview(true));

    document.getElementById('bulkClearEditorBtn')?.addEventListener('click', () => {
        const ta = document.getElementById('bulkQuestionsJson');
        if (ta) ta.value = '';
        const fileInput = document.getElementById('bulkJsonFile');
        if (fileInput) fileInput.value = '';
    });

    document.getElementById('bulkCsvImportSubmitBtn')?.addEventListener('click', async function () {
        const fileInput = document.getElementById('bulkCsvFile');
        const file = fileInput && fileInput.files && fileInput.files.length ? fileInput.files[0] : null;
        if (!file) {
            showAlert('danger', 'Choose a CSV file first.');
            return;
        }
        const formModule = getTomSelectValue('bulkQuestionModule');
        const formStartRaw = document.getElementById('bulkStartPosition')?.value;
        const sp = parseInt(String(formStartRaw), 10);
        if (!formModule) {
            showAlert('danger', 'Please select a Target Module in STEP 1.');
            return;
        }
        if (!Number.isFinite(sp) || sp < 1) {
            showAlert('danger', 'Starting position must be an integer ≥ 1.');
            return;
        }
        const fd = new FormData();
        fd.append('csv_file', file);
        fd.append('module_id', formModule);
        fd.append('start_position', String(sp));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        try {
            const response = await fetch(CSV_BULK_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: fd
            });
            let result = {};
            try {
                result = await response.json();
            } catch (parseErr) {
                showAlert('danger', 'Unexpected response from server (status ' + response.status + ').');
                return;
            }
            if (response.ok) {
                const extra = result.data && result.data.passages_created != null
                    ? ' Passages created: ' + result.data.passages_created + '.'
                    : '';
                showAlert('success', (result.message || 'CSV import completed.') + extra);
                window.__tdQuestionsPage = 1;
                if (fileInput) {
                    fileInput.value = '';
                }
                const preserve = captureTomSelectPreservation(null);
                try {
                    await refreshTestDashboardData(preserve);
                } catch (err) {
                    showAlert('danger', 'Imported, but refresh failed — reloading page. ' + err.message);
                    rememberTestDashboardTab();
                    window.location.reload();
                }
            } else {
                let msg = result.message || 'CSV import failed';
                if (result.errors && typeof result.errors === 'object') {
                    const parts = Object.values(result.errors).flat();
                    if (parts.length) {
                        msg = parts.join(' ');
                    }
                }
                showAlert('danger', msg);
            }
        } catch (error) {
            showAlert('danger', 'Network error: ' + error.message);
        }
    });

    document.getElementById('bulkImportSubmitBtn')?.addEventListener('click', async function () {
        const ta = document.getElementById('bulkQuestionsJson');
        const fileInput = document.getElementById('bulkJsonFile');
        if (!ta) {
            return;
        }
        const file = fileInput && fileInput.files && fileInput.files.length ? fileInput.files[0] : null;

        const formModule = getTomSelectValue('bulkQuestionModule');
        const formStartRaw = document.getElementById('bulkStartPosition')?.value;

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
        };

        try {
            let response;
            if (file) {
                let fileText;
                try {
                    fileText = await file.text();
                } catch (e) {
                    showAlert('danger', 'Could not read file: ' + e.message);
                    return;
                }
                let fileJson;
                try {
                    fileJson = JSON.parse(fileText);
                } catch (err) {
                    showAlert('danger', 'Invalid JSON in file: ' + err.message);
                    return;
                }
                const fileModule = fileJson.module_id;
                const fileStart = fileJson.start_position;
                const effectiveModule = formModule || (fileModule != null && fileModule !== '' ? String(fileModule) : '');
                const startValue = (formStartRaw !== '' && formStartRaw != null) ? formStartRaw : fileStart;
                const startPosition = parseInt(String(startValue), 10);
                if (!effectiveModule) {
                    showAlert('danger', 'Please select a Target Module in STEP 1.');
                    return;
                }
                if (!Number.isFinite(startPosition) || startPosition < 1) {
                    showAlert('danger', 'Starting position must be an integer ≥ 1 (form or start_position in the JSON file).');
                    return;
                }
                const fd = new FormData();
                fd.append('json_file', new File([fileText], file.name, { type: file.type || 'application/json' }));
                if (formModule) {
                    fd.append('module_id', formModule);
                }
                if (formStartRaw !== '' && formStartRaw != null) {
                    fd.append('start_position', String(formStartRaw));
                }
                response = await fetch(BULK_QUESTIONS_URL, {
                    method: 'POST',
                    headers: headers,
                    credentials: 'same-origin',
                    body: fd
                });
            } else {
                let parsed;
                try {
                    parsed = JSON.parse(ta.value.trim() || '{}');
                } catch (err) {
                    showAlert('danger', 'Invalid JSON: ' + err.message);
                    return;
                }
                if (Array.isArray(parsed)) {
                    parsed = { items: parsed };
                }
                const modFromJson = parsed.module_id || formModule;
                const startFromJson = parsed.start_position != null && parsed.start_position !== ''
                    ? parsed.start_position
                    : formStartRaw;
                const sp = parseInt(String(startFromJson), 10);
                if (!modFromJson) {
                    showAlert('danger', 'Please select a Target Module in STEP 1.');
                    return;
                }
                if (!parsed.items || !Array.isArray(parsed.items) || !parsed.items.length) {
                    showAlert('danger', 'JSON must include a non-empty items array (or choose a JSON file).');
                    return;
                }
                if (!Number.isFinite(sp) || sp < 1) {
                    showAlert('danger', 'Starting position must be an integer ≥ 1.');
                    return;
                }
                const payload = {
                    module_id: parseInt(String(modFromJson), 10),
                    start_position: sp,
                    items: parsed.items
                };
                response = await fetch(BULK_QUESTIONS_URL, {
                    method: 'POST',
                    headers: Object.assign({ 'Content-Type': 'application/json' }, headers),
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
            }

            let result = {};
            try {
                result = await response.json();
            } catch (parseErr) {
                showAlert('danger', 'Unexpected response from server (status ' + response.status + ').');
                return;
            }
            if (response.ok) {
                const extra = result.data && result.data.passages_created != null
                    ? ' Passages created: ' + result.data.passages_created + '.'
                    : '';
                showAlert('success', (result.message || 'Bulk import completed.') + extra);
                if (fileInput) {
                    fileInput.value = '';
                }
                const preserve = captureTomSelectPreservation(null);
                try {
                    await refreshTestDashboardData(preserve);
                } catch (err) {
                    showAlert('danger', 'Imported, but refresh failed — reloading page. ' + err.message);
                    rememberTestDashboardTab();
                    window.location.reload();
                }
            } else {
                let msg = result.message || 'Bulk import failed';
                if (result.errors && typeof result.errors === 'object') {
                    const parts = Object.values(result.errors).flat();
                    if (parts.length) {
                        msg = parts.join(' ');
                    }
                }
                showAlert('danger', msg);
            }
        } catch (error) {
            showAlert('danger', 'Network error: ' + error.message);
        }
    });
});

function syncMarkdownEditorsToFields(formId) {
    const editors = window.__testDashboardEditors;
    if (!editors) {
        return;
    }
    if (formId === 'passageForm' && editors.passage) {
        editors.passage.element.value = editors.passage.value();
    }
    if (formId === 'questionForm' && editors.stem) {
        editors.stem.element.value = editors.stem.value();
    }
}

function setupForm(formId, url) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        syncMarkdownEditorsToFields(formId);

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Custom handle for is_pretest checkbox
        if (formId === 'questionForm') {
            data.is_pretest = document.getElementById('isPretest').checked ? 1 : 0;
        }

        // Custom handle for Radio-based MCQs
        if (formId === 'answerChoicesForm') {
            const correctIndex = parseInt(data.is_correct_radio);
            data.choices = [];
            for (let i = 0; i < 4; i++) {
                data.choices.push({
                    label: form.querySelector(`[name="choices[${i}][label]"]`).value,
                    content: form.querySelector(`[name="choices[${i}][content]"]`).value,
                    order: i + 1,
                    is_correct: i === correctIndex
                });
            }
            delete data.is_correct_radio;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            let result = {};
            try {
                result = await response.json();
            } catch (parseErr) {
                showAlert('danger', 'Unexpected response from server (status ' + response.status + ').');
                return;
            }

            if (response.ok) {
                showAlert('success', result.message || 'Created successfully!');
                const preserve = captureTomSelectPreservation(form);
                form.reset();
                if (formId === 'passageForm' && window.__testDashboardEditors?.passage) {
                    window.__testDashboardEditors.passage.value('');
                }
                if (formId === 'questionForm' && window.__testDashboardEditors?.stem) {
                    window.__testDashboardEditors.stem.value('');
                }
                try {
                    await refreshTestDashboardData(preserve);
                } catch (err) {
                    showAlert('danger', 'Saved, but refresh failed — reloading page. ' + err.message);
                    rememberTestDashboardTab();
                    window.location.reload();
                }
            } else {
                let msg = result.message || 'Validation failed';
                if (result.errors && typeof result.errors === 'object') {
                    const parts = Object.values(result.errors).flat();
                    if (parts.length) {
                        msg = parts.join(' ');
                    }
                }
                showAlert('danger', msg);
            }
        } catch (error) {
            showAlert('danger', 'Network error: ' + error.message);
        }
    });
}

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show shadow`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    container.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
}

// --- Easy Builder Logic ---
let builderBlockCount = 0;
const builderEditors = {}; // To store EasyMDE instances

function addBuilderBlock() {
    builderBlockCount++;
    const template = document.getElementById('builderBlockTemplate').innerHTML;
    const html = template
        .replace(/{INDEX}/g, builderBlockCount)
        .replace(/{DISPLAY_INDEX}/g, builderBlockCount);
    
    const container = document.getElementById('builderBlocksContainer');
    const div = document.createElement('div');
    div.innerHTML = html;
    const block = div.firstElementChild;
    container.appendChild(block);

    // Give unique IDs for EasyMDE
    const stemTextarea = block.querySelector('.builder-stem');
    stemTextarea.id = `stem_${builderBlockCount}`;
    
    const passageTextarea = block.querySelector('.builder-passage');
    passageTextarea.id = `passage_${builderBlockCount}`;

    // Sync domain options
    syncBuilderBlockDomain(block);

    // Initialize EasyMDE for Stem
    builderEditors[`stem_${builderBlockCount}`] = new EasyMDE({
        element: stemTextarea,
        placeholder: "Enter question stem...",
        minHeight: "100px",
        toolbar: [
            "bold", "italic", 
            {
                name: "underline",
                action: (editor) => {
                    const cm = editor.codemirror;
                    const selection = cm.getSelection();
                    cm.replaceSelection(`<u>${selection}</u>`);
                },
                className: "fa fa-underline",
                title: "Underline (<u>)",
            },
            "heading", "|", "quote", "unordered-list", "ordered-list", "|", "preview"
        ],
        status: false
    });
    
    // Auto-scroll to new block
    block.scrollIntoView({ behavior: 'smooth' });

    // Handle Image Upload
    const imgInput = block.querySelector('.builder-image-input');
    const imgBtn = block.querySelector('.upload-image-btn');
    
    imgBtn.onclick = () => imgInput.click();
    
    imgInput.onchange = async function() {
        if (!this.files || !this.files[0]) return;
        
        const fd = new FormData();
        fd.append('image', this.files[0]);
        
        imgBtn.disabled = true;
        imgBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        try {
            const res = await fetch('{{ route('test-dashboard.media.upload') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: fd
            });
            const result = await res.json();
            if (result.success) {
                // Insert into Stem editor by default
                const mde = builderEditors[`stem_${block.dataset.index}`];
                if (mde) {
                    const pos = mde.codemirror.getCursor();
                    mde.codemirror.replaceRange(`\n${result.markdown}\n`, pos);
                }
                showAlert('success', 'Image uploaded and inserted!');
            } else {
                showAlert('danger', result.message || 'Upload failed');
            }
        } catch (err) {
            showAlert('danger', 'Error: ' + err.message);
        } finally {
            imgBtn.disabled = false;
            imgBtn.innerHTML = '<i class="bi bi-image"></i> Upload Image';
            this.value = ''; // Reset input
        }
    };

    // Handle remove
    block.querySelector('.remove-block-btn').onclick = function() {
        // Cleanup EasyMDE
        if (builderEditors[`stem_${block.dataset.index}`]) {
            builderEditors[`stem_${block.dataset.index}`].toTextArea();
            delete builderEditors[`stem_${block.dataset.index}`];
        }
        if (builderEditors[`passage_${block.dataset.index}`]) {
            builderEditors[`passage_${block.dataset.index}`].toTextArea();
            delete builderEditors[`passage_${block.dataset.index}`];
        }
        block.remove();
        reorderBuilderBlocks();
    };
}

function reorderBuilderBlocks() {
    // Re-indexing blocks is complex with EasyMDE, so we mostly update labels
    const blocks = document.querySelectorAll('.builder-block');
    blocks.forEach((block, i) => {
        block.querySelector('.text-secondary').textContent = `Question #${i + 1}`;
    });
}

function syncBuilderBlockDomain(block) {
    const moduleId = getTomSelectValue('builderModuleId');
    if (!moduleId) return;
    
    const select = document.getElementById('builderModuleId');
    const option = select.options[select.selectedIndex];
    const type = option.getAttribute('data-section-type');
    
    // Show/Hide passage
    const passageContainer = block.querySelector('.builder-passage-container');
    const passageTextarea = block.querySelector('.builder-passage');
    const index = block.dataset.index;

    if (type === 'reading_writing') {
        passageContainer.classList.remove('d-none');
        // Initialize if not already done
        if (!builderEditors[`passage_${index}`]) {
            builderEditors[`passage_${index}`] = new EasyMDE({
                element: passageTextarea,
                placeholder: "Enter passage content...",
                minHeight: "150px",
                toolbar: [
                    "bold", "italic",
                    {
                        name: "underline",
                        action: (editor) => {
                            const cm = editor.codemirror;
                            const selection = cm.getSelection();
                            cm.replaceSelection(`<u>${selection}</u>`);
                        },
                        className: "fa fa-underline",
                        title: "Underline (<u>)",
                    },
                    "|", "unordered-list", "|", "preview"
                ],
                status: false
            });
        }
    } else {
        passageContainer.classList.add('d-none');
        // We keep the instance but it's hidden. 
        // Or we can destroy it, but teacher might toggle back.
    }

    // Update domains
    const domainSelect = block.querySelector('.builder-domain');
    const currentVal = domainSelect.value;
    domainSelect.innerHTML = '<option value="">Select domain...</option>';
    if (type && SKILL_DOMAINS[type]) {
        SKILL_DOMAINS[type].forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.value;
            opt.textContent = d.label;
            if (d.value === currentVal) opt.selected = true;
            domainSelect.appendChild(opt);
        });
    }
}

document.getElementById('addBuilderBlockBtn')?.addEventListener('click', addBuilderBlock);

document.getElementById('builderModuleId')?.addEventListener('change', function() {
    document.querySelectorAll('.builder-block').forEach(syncBuilderBlockDomain);
});

document.getElementById('clearBuilderBtn')?.addEventListener('click', function() {
    if (confirm('Clear all questions in builder?')) {
        Object.values(builderEditors).forEach(mde => mde.toTextArea());
        for (const key in builderEditors) delete builderEditors[key];
        document.getElementById('builderBlocksContainer').innerHTML = '';
        builderBlockCount = 0;
    }
});

document.getElementById('submitBuilderBtn')?.addEventListener('click', async function() {
    const moduleId = getTomSelectValue('builderModuleId');
    if (!moduleId) {
        showAlert('danger', 'Please select a target module first.');
        return;
    }

    const blocks = document.querySelectorAll('.builder-block');
    if (blocks.length === 0) {
        showAlert('danger', 'Add at least one question block.');
        return;
    }

    const items = [];
    let isValid = true;

    blocks.forEach(block => {
        const index = block.dataset.index;
        const stem = builderEditors[`stem_${index}`] ? builderEditors[`stem_${index}`].value().trim() : '';
        
        if (!stem) {
            block.classList.add('border-danger');
            isValid = false;
        } else {
            block.classList.remove('border-danger');
        }

        const choices = [];
        const correctRadio = block.querySelector('.builder-correct-radio:checked');
        const correctLabel = correctRadio ? correctRadio.value : 'A';
        
        block.querySelectorAll('.builder-choice-content').forEach(input => {
            const label = input.getAttribute('data-label');
            const content = input.value.trim();
            if (!content) isValid = false;
            
            choices.push({
                label: label,
                content: content,
                is_correct: (label === correctLabel),
                order: label.charCodeAt(0) - 64
            });
        });

        const item = {
            stem: stem,
            question_type: 'multiple_choice',
            difficulty: block.querySelector('.builder-difficulty').value || null,
            skill_domain: block.querySelector('.builder-domain').value || null,
            choices: choices,
            explanation: block.querySelector('.builder-explanation').value.trim()
        };

        const type = document.getElementById('builderModuleId').selectedOptions[0]?.getAttribute('data-section-type');
        if (type === 'reading_writing' && builderEditors[`passage_${index}`]) {
            const passageContent = builderEditors[`passage_${index}`].value().trim();
            if (passageContent) {
                item.passage = { content: passageContent };
            }
        }

        items.push(item);
    });

    if (!isValid) {
        showAlert('danger', 'Please fill in all required fields (Stem and Choices).');
        return;
    }

    const startPos = document.getElementById('builderStartPosition').value || 1;
    const payload = {
        module_id: moduleId,
        start_position: startPos,
        items: items
    };

    try {
        const response = await fetch('{{ route('test-dashboard.questions.bulk-store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const resultText = await response.text();
        let result;
        try {
            result = JSON.parse(resultText);
        } catch (e) {
            showAlert('danger', 'Server returned invalid response. Status: ' + response.status);
            return;
        }

        if (response.ok) {
            showAlert('success', `Successfully imported ${items.length} questions.`);
            // Clean up
            Object.values(builderEditors).forEach(mde => mde.toTextArea());
            for (const key in builderEditors) delete builderEditors[key];
            document.getElementById('builderBlocksContainer').innerHTML = '';
            builderBlockCount = 0;
            
            const preserve = captureTomSelectPreservation(null);
            await refreshTestDashboardData(preserve);
        } else {
            showAlert('danger', result.message || 'Import failed.');
        }
    } catch (error) {
        showAlert('danger', 'Error: ' + error.message);
    }
});

document.getElementById('bulkZipImportBtn')?.addEventListener('click', async function() {
    const fileInput = document.getElementById('bulkZipFile');
    const moduleId = getTomSelectValue('bulkQuestionModule');
    const startPos = document.getElementById('bulkStartPosition')?.value || 1;
    const btn = this;

    if (!moduleId) {
        showAlert('danger', 'Please select a Target Module in STEP 1.');
        return;
    }

    if (!fileInput.files[0]) {
        showAlert('danger', 'Please select a ZIP file.');
        return;
    }

    const fd = new FormData();
    fd.append('zip_file', fileInput.files[0]);
    fd.append('module_id', moduleId);
    fd.append('start_position', startPos);

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';

    try {
        const response = await fetch('{{ route('test-dashboard.questions.bulk-zip') }}', {
            method: 'POST',
            headers: { 
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: fd
        });
        
        const resultText = await response.text();
        let result;
        try {
            result = JSON.parse(resultText);
        } catch (e) {
            showAlert('danger', 'Server returned invalid response. Status: ' + response.status);
            return;
        }

        if (response.ok) {
            showAlert('success', result.message);
            fileInput.value = '';
            const preserve = captureTomSelectPreservation(null);
            await refreshTestDashboardData(preserve);
        } else {
            showAlert('danger', result.message || 'ZIP import failed.');
        }
    } catch (err) {
        showAlert('danger', 'Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Import ZIP';
    }
});


</script>
@endpush
</x-layouts.admin>
