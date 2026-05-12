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
