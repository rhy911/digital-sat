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
                    <button class="nav-link" id="passages-tab" data-bs-toggle="tab" data-bs-target="#passages" type="button" role="tab">4. Passages</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="questions-tab" data-bs-toggle="tab" data-bs-target="#questions" type="button" role="tab">5. Questions & Answers</button>
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
                            <div class="col-md-4 mb-3">
                                <label for="totalDuration" class="form-label">Total Duration (min) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="totalDuration" name="total_duration_minutes" value="134" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="breakDuration" class="form-label">Break (min) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="breakDuration" name="break_duration_minutes" value="10" required>
                            </div>
                            <div class="col-md-4 mb-3">
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
                                        <button class="btn btn-sm btn-outline-danger delete-test-btn" data-id="{{ $test->id }}">Delete</button>
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
                                <select class="form-select" id="sectionTest" name="test_id" required>
                                    <option value="">Select test...</option>
                                    @foreach($tests as $test)
                                    <option value="{{ $test->id }}">{{ $test->title }}</option>
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
                            <div class="col-md-6 mb-3">
                                <label for="sectionName" class="form-label">Display Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="sectionName" name="name" placeholder="Reading and Writing" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sectionOrder" class="form-label">Order <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="sectionOrder" name="order" min="1" value="1" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">Create Section</button>
                    </form>
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
                                <select class="form-select" id="moduleSection" name="section_id" required onchange="applyModuleDefaults(this)">
                                    <option value="">Select section...</option>
                                    @foreach($tests as $test)
                                        @foreach($test->sections as $section)
                                        <option value="{{ $section->id }}" data-type="{{ $section->type }}">
                                            {{ $test->title }} - {{ $section->name }}
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
                            <div class="col-md-4 mb-3">
                                <label for="moduleOrder" class="form-label">Sequence Order <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="moduleOrder" name="order" min="1" value="1" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-info text-white">Create Module</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Passages Tab -->
        <div class="tab-pane fade" id="passages" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Create New Passage</h5>
                </div>
                <div class="card-body">
                    <form id="passageForm">
                        @csrf
                        <div class="mb-3">
                            <label for="passageContent" class="form-label">Passage Text (HTML Allowed) <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="passageContent" name="content" rows="6" placeholder="Paste passage text here..." required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="passageType" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="passageType" name="passage_type" required>
                                    <option value="single" selected>Single Passage</option>
                                    <option value="paired">Paired Passage</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="genre" class="form-label">Genre <span class="text-danger">*</span></label>
                                <select class="form-select" id="genre" name="genre" required>
                                    <option value="literary_narrative">Literary Narrative</option>
                                    <option value="social_science">Social Science</option>
                                    <option value="natural_science">Natural Science</option>
                                    <option value="humanities">Humanities</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="wordCount" class="form-label">Word Count</label>
                                <input type="number" class="form-control" id="wordCount" name="word_count" placeholder="e.g. 120">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sourceTitle" class="form-label">Source / Attribution</label>
                                <input type="text" class="form-control" id="sourceTitle" name="source_title" placeholder="Author/Work">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sourceYear" class="form-label">Year</label>
                                <input type="number" class="form-control" id="sourceYear" name="source_year">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-dark">Create Passage</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Questions Tab -->
        <div class="tab-pane fade" id="questions" role="tabpanel">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Create New Question</h5>
                </div>
                <div class="card-body">
                    <form id="questionForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="questionModule" class="form-label">Assign to Module <span class="text-danger">*</span></label>
                                <select class="form-select" id="questionModule" name="module_id" required>
                                    <option value="">Select module...</option>
                                    @foreach($tests as $test)
                                        @foreach($test->sections as $section)
                                            @foreach($section->modules as $module)
                                            <option value="{{ $module->id }}">
                                                {{ $test->title }} - {{ $section->name }} - Mod {{ $module->module_number }} ({{ ucfirst($module->difficulty_level) }})
                                            </option>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="questionPosition" class="form-label">Position in Module <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="questionPosition" name="position" min="1" value="1" required>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="isPretest" name="is_pretest" value="1">
                                    <label class="form-check-label text-danger" for="isPretest">
                                        <strong>Is Unscored (Pretest)?</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="questionPassage" class="form-label">Passage (Required for R&W)</label>
                                <select class="form-select" id="questionPassage" name="passage_id">
                                    <option value="">No passage (Standalone)</option>
                                    @foreach($passages as $passage)
                                    <option value="{{ $passage->id }}">{{ Str::limit($passage->content, 80) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="questionType" class="form-label">Question Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="questionType" name="question_type" required>
                                    <option value="multiple_choice" selected>Multiple Choice</option>
                                    <option value="student_produced_response">Student Produced (SPR)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="questionStem" class="form-label">Question Stem / Prompt <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="questionStem" name="stem" rows="3" placeholder="What is the value of x?" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="qSectionType" class="form-label">Section <span class="text-danger">*</span></label>
                                <select class="form-select" id="qSectionType" name="section_type" required onchange="updateSkillDomains(this)">
                                    <option value="">Select...</option>
                                    <option value="reading_writing">Reading & Writing</option>
                                    <option value="math">Math</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="skillDomain" class="form-label">Skill Domain <span class="text-danger">*</span></label>
                                <select class="form-select" id="skillDomain" name="skill_domain" required>
                                    <option value="">First select section...</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="difficulty" class="form-label">Difficulty <span class="text-danger">*</span></label>
                                <select class="form-select" id="difficulty" name="difficulty" required>
                                    <option value="medium" selected>Medium</option>
                                    <option value="easy">Easy</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="skillSubdomain" class="form-label">Skill Subdomain</label>
                                <input type="text" class="form-control" id="skillSubdomain" name="skill_subdomain" placeholder="e.g. Linear Equations">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sprHint" class="form-label">SPR Hint (Grid-in helper)</label>
                                <input type="text" class="form-control" id="sprHint" name="spr_hint" placeholder="e.g. Enter as a fraction">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning">Create Question</button>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <!-- Answer Choices Form -->
                    <div class="card mb-4 shadow-sm border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Step 2: Add Answer Choices (MCQ)</h5>
                        </div>
                        <div class="card-body">
                            <form id="answerChoicesForm">
                                @csrf
                                <div class="mb-3">
                                    <label for="answerQuestionId" class="form-label">Target Question <span class="text-danger">*</span></label>
                                    <select class="form-select" id="answerQuestionId" name="question_id" required>
                                        <option value="">Select question...</option>
                                        @foreach($questions as $question)
                                        <option value="{{ $question->id }}">ID:{{ $question->id }} - {{ Str::limit($question->stem, 40) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div id="choicesContainer">
                                    @foreach(['A', 'B', 'C', 'D'] as $index => $label)
                                    <div class="choice-row mb-2 pb-2 border-bottom">
                                        <div class="row align-items-center">
                                            <div class="col-2 text-center"><strong>{{ $label }}</strong></div>
                                            <input type="hidden" name="choices[{{ $index }}][label]" value="{{ $label }}">
                                            <div class="col-7">
                                                <input type="text" class="form-control form-control-sm" name="choices[{{ $index }}][content]" placeholder="Option content" required>
                                            </div>
                                            <div class="col-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="is_correct_radio" value="{{ $index }}" {{ $index === 0 ? 'checked' : '' }}>
                                                    <label class="form-check-label small">Correct</label>
                                                </div>
                                            </div>
                                            <input type="hidden" name="choices[{{ $index }}][order]" value="{{ $index + 1 }}">
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Save Answers</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <!-- Question Explanation Form -->
                    <div class="card mb-4 shadow-sm border-info">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Step 3: Explanation & Rationales</h5>
                        </div>
                        <div class="card-body">
                            <form id="explanationForm">
                                @csrf
                                <div class="mb-3">
                                    <label for="explanationQuestionId" class="form-label">Target Question <span class="text-danger">*</span></label>
                                    <select class="form-select" id="explanationQuestionId" name="question_id" required>
                                        <option value="">Select question...</option>
                                        @foreach($questions as $question)
                                        <option value="{{ $question->id }}">ID:{{ $question->id }} - {{ Str::limit($question->stem, 40) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="explanation" class="form-label">Correct Answer Explanation <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="explanation" name="explanation" rows="3" required></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="small">Rationale A</label>
                                        <textarea class="form-control form-control-sm" name="rationale_a" rows="1"></textarea>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small">Rationale B</label>
                                        <textarea class="form-control form-control-sm" name="rationale_b" rows="1"></textarea>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small">Rationale C</label>
                                        <textarea class="form-control form-control-sm" name="rationale_c" rows="1"></textarea>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small">Rationale D</label>
                                        <textarea class="form-control form-control-sm" name="rationale_d" rows="1"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-info w-100 text-white">Save Explanation</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Questions -->
            <div class="card shadow-sm mt-2">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Questions Pool</h5>
                    <span class="badge bg-secondary">{{ count($questions) }} Total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Sec</th>
                                    <th>Stem Snippet</th>
                                    <th>Pretest?</th>
                                    <th>Domain</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="questionsTableBody">
                                @forelse($questions as $question)
                                <tr>
                                    <td>{{ $question->id }}</td>
                                    <td><small>{{ $question->section_type === 'reading_writing' ? 'R&W' : 'Math' }}</small></td>
                                    <td>{{ Str::limit($question->stem, 40) }}</td>
                                    <td>{!! $question->is_pretest ? '<span class="text-danger">● Yes</span>' : 'No' !!}</td>
                                    <td><small>{{ $question->skill_domain }}</small></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger delete-question-btn" data-id="{{ $question->id }}">×</button>
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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Container -->
<div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

@push('scripts')
<script>
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

function updateSectionName(select) {
    const nameInput = document.getElementById('sectionName');
    if (select.value === 'reading_writing') {
        nameInput.value = 'Reading and Writing';
    } else if (select.value === 'math') {
        nameInput.value = 'Math';
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
    
    domainSelect.innerHTML = '<option value="">Select Domain...</option>';
    
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
    // Form submission handlers
    setupForm('testForm', '{{ route('test-dashboard.tests.store') }}');
    setupForm('sectionForm', '{{ route('test-dashboard.sections.store') }}');
    setupForm('moduleForm', '{{ route('test-dashboard.modules.store') }}');
    setupForm('passageForm', '{{ route('test-dashboard.passages.store') }}');
    setupForm('questionForm', '{{ route('test-dashboard.questions.store') }}');
    setupForm('answerChoicesForm', '{{ route('test-dashboard.answer-choices.store') }}');
    setupForm('explanationForm', '{{ route('test-dashboard.explanations.store') }}');

    // Delete handlers
    setupDelete('.delete-test-btn', '{{ route('test-dashboard.tests.delete', ':id') }}');
    setupDelete('.delete-question-btn', '{{ route('test-dashboard.questions.delete', ':id') }}');
});

function setupForm(formId, url) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok) {
                showAlert('success', 'Created successfully!');
                form.reset();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert('danger', result.message || 'Validation failed');
            }
        } catch (error) {
            showAlert('danger', 'Network error: ' + error.message);
        }
    });
}

function setupDelete(selector, baseUrl) {
    document.querySelectorAll(selector).forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Permanently delete this item?')) return;
            
            const id = this.dataset.id;
            const url = baseUrl.replace(':id', id);

            try {
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    showAlert('success', 'Deleted successfully');
                    setTimeout(() => window.location.reload(), 800);
                }
            } catch (error) {
                showAlert('danger', 'Error: ' + error.message);
            }
        });
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
</script>
@endpush
</x-layouts.admin>
