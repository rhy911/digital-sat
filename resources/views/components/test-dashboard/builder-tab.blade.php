@props(['tests'])

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
