@props(['tests', 'questions', 'questionsTotal'])

<div class="tab-pane fade" id="questions" role="tabpanel">
    <div class="card mb-4 shadow-sm border-warning">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-gear-fill"></i> STEP 1: Global Import Configuration</h5>
            <span class="badge bg-dark text-white">Required for all methods</span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8 mb-2">
                    <label for="bulkQuestionModule" class="form-label fw-bold">Target Module <span
                            class="text-danger">*</span></label>
                    <select class="form-select tom-select" id="bulkQuestionModule" required>
                        <option value="">Search module to import into...</option>
                        @foreach ($tests as $test)
                            @foreach ($test->sections as $section)
                                @foreach ($section->modules as $module)
                                    <option value="{{ $module->id }}" data-section-type="{{ $section->type }}">
                                        {{ $test->title }} |
                                        {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod
                                        {{ $module->module_number }} ({{ $module->difficulty_level }})
                                    </option>
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label for="bulkStartPosition" class="form-label fw-bold">Starting position <span
                            class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="bulkStartPosition" min="1" value="1"
                        required>
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
                    <button class="nav-link active" id="json-tab" data-bs-toggle="tab" data-bs-target="#import-json"
                        type="button" role="tab"><i class="bi bi-filetype-json"></i> JSON / Editor</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="csv-tab" data-bs-toggle="tab" data-bs-target="#import-csv"
                        type="button" role="tab"><i class="bi bi-file-earmark-spreadsheet"></i> CSV
                        Spreadsheet</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="zip-tab" data-bs-toggle="tab" data-bs-target="#import-zip"
                        type="button" role="tab"><i class="bi bi-file-earmark-zip"></i> ZIP (with Images)</button>
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
                            <label class="form-label fw-bold">Import JSON file</label>
                            <div class="file-dropzone border border-dashed rounded-3 p-4 text-center bg-light-subtle position-relative cursor-pointer transition-all hover:bg-light"
                                style="border-width: 2px !important; border-color: #dee2e6 !important;">
                                <input type="file"
                                    class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer"
                                    id="bulkJsonFile" accept=".json,application/json" style="z-index: 10;">
                                <i class="bi bi-filetype-json display-5 text-muted mb-2 d-block"></i>
                                <span class="fw-semibold d-block text-dark mb-1 drag-instruction">Drag & drop your JSON
                                    here</span>
                                <span class="text-muted small">or click to browse file</span>
                                <div class="file-name-display mt-2 small text-success fw-bold d-none"></div>
                            </div>
                            <p class="form-text small text-muted mt-2">Selecting a file loads its items directly into
                                the JSON editor.</p>

                            <div class="mt-4">
                                <h6 class="fw-bold small text-uppercase text-muted mb-2">Examples & Samples</h6>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start"
                                        id="bulkLoadExampleRwBtn"><i class="bi bi-plus-circle"></i> Insert R&W
                                        example</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-start"
                                        id="bulkLoadExampleMathBtn"><i class="bi bi-plus-circle"></i> Insert Math
                                        example</button>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            id="bulkDownloadRwSampleBtn">R&W sample.json</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            id="bulkDownloadMathSampleBtn">Math sample.json</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="bulkQuestionsJson" class="form-label fw-bold mb-0">Payload (JSON
                                    editor)</label>
                                <button type="button" class="btn btn-link btn-sm text-danger p-0"
                                    id="bulkClearEditorBtn">Clear Editor</button>
                            </div>
                            <textarea class="form-control font-monospace small" id="bulkQuestionsJson" rows="12" spellcheck="false"
                                placeholder='{ "items": [ ... ] }'></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-info text-white" id="bulkPreviewBtn"><i
                                class="bi bi-eye"></i> Preview</button>
                        <button type="button" class="btn btn-success" id="bulkImportSubmitBtn"><i
                                class="bi bi-cloud-arrow-up"></i> Import all from Editor</button>
                    </div>
                </div>

                <!-- CSV Tab -->
                <div class="tab-pane fade" id="import-csv" role="tabpanel">
                    <p class="text-muted small mb-3">
                        Best for importing from Excel or Google Sheets. Header row is required. Column names are
                        lowercase with underscores.
                    </p>
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">CSV file (.csv or .txt) <span
                                    class="text-danger">*</span></label>
                            <div class="file-dropzone border border-dashed rounded-3 p-4 text-center bg-light-subtle position-relative cursor-pointer transition-all hover:bg-light"
                                style="border-width: 2px !important; border-color: #dee2e6 !important;">
                                <input type="file"
                                    class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer"
                                    id="bulkCsvFile" accept=".csv,.txt,text/csv,text/plain" style="z-index: 10;">
                                <i class="bi bi-file-earmark-spreadsheet display-5 text-muted mb-2 d-block"></i>
                                <span class="fw-semibold d-block text-dark mb-1 drag-instruction">Drag & drop your CSV
                                    here</span>
                                <span class="text-muted small">or click to browse file</span>
                                <div class="file-name-display mt-2 small text-success fw-bold d-none"></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3 d-flex flex-column gap-2 align-items-start">
                            <span class="text-muted small fw-bold">Need a template?</span>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                id="bulkDownloadRwSampleCsvBtn"><i class="bi bi-download"></i> Download R&W
                                sample.csv</button>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                id="bulkDownloadMathSampleCsvBtn"><i class="bi bi-download"></i> Download Math
                                sample.csv</button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-info text-white" id="bulkCsvPreviewBtn"><i
                                class="bi bi-eye"></i> Preview</button>
                        <button type="button" class="btn btn-success" id="bulkCsvImportSubmitBtn"><i
                                class="bi bi-cloud-arrow-up"></i> Import CSV</button>
                    </div>
                </div>

                <!-- ZIP Tab -->
                <div class="tab-pane fade" id="import-zip" role="tabpanel">
                    <p class="text-muted small mb-3">
                        <strong>Power User:</strong> upload a <code>.zip</code> containing <code>.json</code> or
                        <code>.csv</code> files and your images.
                        Images can be in an <code>images/</code> folder or alongside data. Use
                        <code>[MEDIA:filename.png]</code> placeholders in your text.
                    </p>
                    <div class="row align-items-center">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">ZIP file <span class="text-danger">*</span></label>
                            <div class="file-dropzone border border-dashed rounded-3 p-4 text-center bg-light-subtle position-relative cursor-pointer transition-all hover:bg-light"
                                style="border-width: 2px !important; border-color: #dee2e6 !important;">
                                <input type="file"
                                    class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer"
                                    id="bulkZipFile" accept=".zip" style="z-index: 10;">
                                <i class="bi bi-file-earmark-zip display-5 text-muted mb-2 d-block"></i>
                                <span class="fw-semibold d-block text-dark mb-1 drag-instruction">Drag & drop your ZIP
                                    here</span>
                                <span class="text-muted small">or click to browse file</span>
                                <div class="file-name-display mt-2 small text-success fw-bold d-none"></div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <button type="button"
                                class="btn btn-info text-white w-100 py-3 d-inline-flex align-items-center justify-content-center gap-2 rounded-3 shadow-sm"
                                id="bulkZipImportBtn">
                                <i class="bi bi-file-earmark-zip-fill fs-5"></i> Import ZIP Package
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attach Existing Question from Bank -->
    <div class="card mb-4 shadow-sm border-0 rounded-3" style="overflow: visible !important;">
        <div class="card-header bg-primary bg-gradient text-white py-3">
            <h5 class="mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-link-45deg fs-4"></i> Attach Existing Question from Bank
            </h5>
        </div>
        <div class="card-body bg-light-subtle p-4">
            <form id="attachQuestionForm">
                @csrf
                <div class="row align-items-end">
                    <div class="col-md-5 mb-3">
                        <label for="attachToModule" class="form-label fw-bold">Target Module <span
                                class="text-danger">*</span></label>
                        <select class="form-select tom-select" id="attachToModule" name="module_id" required>
                            <option value="">Search module...</option>
                            @foreach ($tests as $test)
                                @foreach ($test->sections as $section)
                                    @foreach ($section->modules as $module)
                                        <option value="{{ $module->id }}">
                                            {{ $test->title }} |
                                            {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod
                                            {{ $module->module_number }} ({{ $module->difficulty_level }})
                                        </option>
                                    @endforeach
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label for="attachQuestionId" class="form-label fw-bold">Question from Bank <span
                                class="text-danger">*</span></label>
                        <select class="form-select tom-select-remote-question" id="attachQuestionId"
                            name="question_id" required>
                            <option value="">Search by ID or question text snippet...</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="attachPosition" class="form-label fw-bold">Position</label>
                        <input type="number" class="form-control py-2" id="attachPosition" name="position"
                            min="1" placeholder="Auto">
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-2">
                    <button type="submit"
                        class="btn btn-primary px-4 py-2 d-inline-flex align-items-center gap-2 rounded-3 shadow-sm">
                        <i class="bi bi-check-circle-fill"></i> Attach to Module
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Questions -->
    <div class="card shadow-sm border-0 mt-4 rounded-3" style="overflow: visible !important;">
        <div
            class="card-header bg-dark bg-gradient text-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-0">
            <h5 class="mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-database-fill-gear text-warning"></i> Questions Pool & Bank
            </h5>
            <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill" id="questionsPoolCountBadge"
                style="font-size: 0.85rem;">{{ $questionsTotal }} Total Questions</span>
        </div>
        <div class="card-body bg-light-subtle p-4">
            <div
                class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4 bg-white p-3 rounded-3 shadow-sm border border-light-subtle">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="input-group input-group-sm" style="max-width: 240px;">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i
                                class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0" id="questionsTableFilter"
                            placeholder="Search stem text...">
                    </div>

                    <select class="form-select form-select-sm border-light-subtle" id="questionsTableSectionFilter"
                        style="max-width: 140px; border-radius: 0.375rem;">
                        <option value="">All Sections</option>
                        <option value="reading_writing">R&W</option>
                        <option value="math">Math</option>
                    </select>

                    <select class="form-select form-select-sm border-light-subtle" id="questionsTableStatusFilter"
                        style="max-width: 140px; border-radius: 0.375rem;">
                        <option value="">All Status</option>
                        <option value="1">Complete</option>
                        <option value="0">Incomplete</option>
                    </select>

                    <select class="form-select form-select-sm border-light-subtle tom-select-filter"
                        id="questionsTableModuleFilter" style="max-width: 280px; border-radius: 0.375rem;">
                        <option value="">All Modules</option>
                        @foreach ($tests as $test)
                            @foreach ($test->sections as $section)
                                @foreach ($section->modules as $module)
                                    <option value="{{ $module->id }}">
                                        {{ $test->title }} |
                                        {{ $section->type === 'reading_writing' ? 'R&W' : 'Math' }} - Mod
                                        {{ $module->module_number }}
                                    </option>
                                @endforeach
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="button"
                        class="btn btn-sm btn-primary px-3 shadow-sm d-inline-flex align-items-center gap-1"
                        id="questionsTableFilterBtn">
                        <i class="bi bi-filter"></i> Apply Filters
                    </button>
                    <button type="button"
                        class="btn btn-sm btn-outline-secondary px-3 d-inline-flex align-items-center gap-1"
                        id="questionsTableFilterClearBtn">
                        <i class="bi bi-x-circle"></i> Clear
                    </button>
                </div>
            </div>

            <div class="table-responsive rounded-3 border border-light-subtle bg-white">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="table-dark bg-gradient text-white">
                        <tr>
                            <th class="ps-3 py-3" style="width: 80px;">ID</th>
                            <th class="py-3" style="width: 130px;">Q. Number</th>
                            <th class="py-3" style="width: 100px;">Section</th>
                            <th class="py-3">Stem Snippet</th>
                            <th class="py-3" style="width: 110px;">Usage</th>
                            <th class="py-3" style="width: 180px;">Domain</th>
                            <th class="py-3" style="width: 100px;">Difficulty</th>
                            <th class="pe-3 py-3 text-end" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="questionsTableBody">
                        @forelse($questions as $question)
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-secondary">{{ $question->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span
                                            class="fw-semibold text-dark">{{ $question->question_number ?? '-' }}</span>
                                        @if (!$question->is_complete)
                                            <span
                                                class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded"
                                                title="Missing Domain or Difficulty" style="font-size: 0.7rem;"><i
                                                    class="bi bi-exclamation-triangle-fill"></i> Incomplete</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($question->section_type === 'reading_writing')
                                        <span
                                            class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-semibold">R&W</span>
                                    @else
                                        <span
                                            class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-semibold">Math</span>
                                    @endif
                                </td>
                                <td class="text-secondary text-truncate" style="max-width: 280px;"
                                    title="{{ strip_tags($question->stem) }}">
                                    {{ Str::limit(strip_tags($question->stem), 50) }}
                                </td>
                                <td>
                                    @if ($question->is_pretest)
                                        <span
                                            class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill d-inline-flex align-items-center fw-semibold">
                                            <span class="spinner-grow spinner-grow-sm text-danger me-1.5"
                                                style="width: 6px; height: 6px; animation-duration: 1.5s;"
                                                role="status"></span>
                                            Pretest
                                        </span>
                                    @else
                                        <span
                                            class="badge bg-light text-muted border px-2.5 py-1 rounded-pill fw-semibold">Active</span>
                                    @endif
                                </td>
                                <td><span
                                        class="text-secondary small font-monospace">{{ $question->skill_domain }}</span>
                                </td>
                                <td>
                                    @if (strtolower($question->difficulty) === 'easy')
                                        <span
                                            class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded">{{ ucfirst($question->difficulty) }}</span>
                                    @elseif(strtolower($question->difficulty) === 'medium')
                                        <span
                                            class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0.5 rounded">{{ ucfirst($question->difficulty) }}</span>
                                    @else
                                        <span
                                            class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded">{{ ucfirst($question->difficulty) }}</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-flex justify-content-end gap-1.5">
                                        <button
                                            class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 edit-question-btn rounded-pill px-2.5 py-1"
                                            data-id="{{ $question->id }}">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <button
                                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center delete-question-btn rounded-circle"
                                            style="width: 30px; height: 30px;" data-id="{{ $question->id }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i
                                        class="bi bi-database-fill-x display-6 mb-2 d-block text-secondary opacity-50"></i>
                                    No questions found in bank.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div id="questionsPoolPagination" class="mt-4 d-flex justify-content-center"></div>
        </div>
    </div>
</div>
