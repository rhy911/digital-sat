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

            <div class="row">
                <!-- Left Sidebar Navigator (P2) -->
                <div class="col-lg-3 mb-4">
                    <div class="sticky-top" style="top: 2rem; z-index: 100;">
                        <div class="card border-0 shadow-sm rounded-4 bg-white bg-opacity-70" style="backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.25);">
                            <div class="card-header bg-gradient bg-warning text-dark py-3 rounded-top-4">
                                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                    <i class="bi bi-compass"></i> Workspace Index
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="list-group list-group-flush gap-2" id="builderSidebarNavigator" style="max-height: 450px; overflow-y: auto;">
                                    <div class="text-muted text-center py-4 small">
                                        <i class="bi bi-layers fs-3 d-block mb-2 text-warning"></i>
                                        Add a question to start indexing
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Middle Question Builder Workspace (P1) -->
                <div class="col-lg-5 mb-4 position-relative" id="builderWorkspaceScroller" style="max-height: calc(100vh - 12rem); overflow-y: auto;">
                    
                    <!-- Sticky Breadcrumb (P2) -->
                    <div class="sticky-top bg-white border-bottom pb-2 mb-3 pt-1 z-3 d-none" id="builderInteractiveBreadcrumb">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0 align-items-center small">
                            <li class="breadcrumb-item"><i class="bi bi-journal-text me-1"></i><span id="bc-test-title" class="text-truncate d-inline-block align-bottom" style="max-width: 120px;" title="">Test</span></li>
                            <li class="breadcrumb-item">
                                <div class="dropdown d-inline-block">
                                    <span class="cursor-pointer fw-bold dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="bc-section-title">Section</span>
                                    <ul class="dropdown-menu shadow-sm fs-7" id="bc-section-dropdown"></ul>
                                </div>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                <div class="dropdown d-inline-block">
                                    <span class="cursor-pointer fw-bold dropdown-toggle text-primary" data-bs-toggle="dropdown" aria-expanded="false" id="bc-module-title">Module</span>
                                    <ul class="dropdown-menu shadow-sm fs-7" id="bc-module-dropdown"></ul>
                                </div>
                            </li>
                          </ol>
                        </nav>
                    </div>

                    <div id="builderBlocksContainer">
                        <!-- Question blocks will be added here -->
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                        <button type="button" class="btn btn-outline-warning w-100 py-3 rounded-3 shadow-xs" id="addBuilderBlockBtn">
                            <i class="bi bi-plus-circle"></i> Add Another Question
                        </button>
                    </div>
                </div>

                <!-- Right Live Preview Drawer (P1) -->
                <div class="col-lg-4 mb-4">
                    <div class="sticky-top" style="top: 2rem; z-index: 100;">
                        <div class="card border-0 shadow-sm rounded-4 bg-white bg-opacity-70" style="backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.25); max-height: calc(100vh - 12rem); display: flex; flex-direction: column;">
                            <div class="card-header bg-gradient bg-dark text-white py-3 rounded-top-4">
                                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                    <i class="bi bi-eye-fill text-warning"></i> Bluebook Live Preview
                                </h6>
                            </div>
                            <div class="card-body p-3" id="builderLivePreviewDrawer" style="overflow-y: auto; flex-grow: 1;">
                                <div class="text-muted text-center py-5 small">
                                    <i class="bi bi-file-earmark-richtext fs-2 d-block mb-2 text-warning"></i>
                                    Live compilation of STEM and formulas will appear here in real-time
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-light d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" id="clearBuilderBtn">Clear All</button>
            <button type="button" class="btn btn-warning" id="submitBuilderBtn">Save All Questions to Test</button>
        </div>
    </div>
</div>
