@props(['tests', 'questions', 'questionsTotal'])

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
