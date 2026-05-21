<!-- Quick Authoring Wizard Modal -->
<div class="modal fade" id="quickAuthorWizardModal" tabindex="-1" aria-labelledby="quickAuthorWizardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 bg-primary text-white">
                <h5 class="modal-title fw-bold" id="quickAuthorWizardModalLabel"><i class="bi bi-magic me-2"></i> Quick Authoring Wizard</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                
                <!-- Recent Continuation Section -->
                <div id="wizard-recent-work-container" class="mb-4 d-none">
                    <h6 class="text-muted text-uppercase small fw-bold mb-3">Continue Recent Work</h6>
                    <div class="d-flex gap-2 flex-wrap" id="wizard-recent-work-list">
                        <!-- Populated via JS -->
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Option 1: Full SAT -->
                    <div class="col-md-6">
                        <div class="card h-100 border border-primary hover-shadow cursor-pointer transition-all" id="wizard-btn-full-sat">
                            <div class="card-body text-center p-4">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-3 mb-3">
                                    <i class="bi bi-journal-text fs-2"></i>
                                </div>
                                <h5 class="fw-bold">New Full SAT Test</h5>
                                <p class="text-muted small mb-0">Instantly generate a complete 6-module structure and start adding questions.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Option 2: Custom Content -->
                    <div class="col-md-6">
                        <div class="card h-100 border border-secondary hover-shadow cursor-pointer transition-all" id="wizard-btn-custom">
                            <div class="card-body text-center p-4">
                                <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-inline-flex p-3 mb-3">
                                    <i class="bi bi-puzzle fs-2"></i>
                                </div>
                                <h5 class="fw-bold">Custom Module / Section</h5>
                                <p class="text-muted small mb-0">Create individual pieces or jump directly into the builder for an existing test.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Flow Steps (Hidden initially) -->
                <div id="wizard-custom-flow" class="mt-4 d-none border-top pt-4">
                    <!-- Step 1: Parent Test -->
                    <div class="mb-4" id="wizard-step-test">
                        <label class="form-label fw-bold">Select Parent Test</label>
                        <select class="form-select" id="wizard-select-test">
                            <option value="">Choose a test...</option>
                            <!-- Populated via JS -->
                        </select>
                    </div>

                    <!-- Step 2: Subject & Target -->
                    <div class="row mb-4 d-none" id="wizard-step-target">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Domain</label>
                            <select class="form-select" id="wizard-select-domain">
                                <option value="reading_writing">Reading & Writing</option>
                                <option value="math">Math</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Module Position</label>
                            <select class="form-select" id="wizard-select-module">
                                <option value="1_standard">Module 1 (Standard)</option>
                                <option value="2_easy">Module 2 (Easy)</option>
                                <option value="2_hard">Module 2 (Hard)</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-end d-none" id="wizard-step-launch">
                        <button class="btn btn-success fw-bold px-4" id="wizard-btn-launch">
                            Launch Builder <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div id="wizard-loading" class="text-center p-5 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted">Generating structure...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-shadow:hover {
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    transform: translateY(-2px);
}
.cursor-pointer {
    cursor: pointer;
}
.transition-all {
    transition: all .2s ease-in-out;
}
</style>
