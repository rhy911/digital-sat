<x-layouts.admin :pageTitle="'Test Data Dashboard'">
    <div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-3">Test Data Input Dashboard</h2>
            <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tests-tab" data-bs-toggle="tab" data-bs-target="#tests" type="button" role="tab">Tests</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button" role="tab">Sections</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="modules-tab" data-bs-toggle="tab" data-bs-target="#modules" type="button" role="tab">Modules</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="passages-tab" data-bs-toggle="tab" data-bs-target="#passages" type="button" role="tab">Passages</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="questions-tab" data-bs-toggle="tab" data-bs-target="#questions" type="button" role="tab">Questions</button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="dashboardTabContent">
        <!-- Tests Tab -->
        <div class="tab-pane fade show active" id="tests" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Create New Test</h5>
                </div>
                <div class="card-body">
                    <form id="testForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="testTitle" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="testTitle" name="title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="testType" class="form-label">Test Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="testType" name="test_type" required>
                                    <option value="">Select type...</option>
                                    <option value="full_length">Full Length</option>
                                    <option value="section_only">Section Only</option>
                                    <option value="mini_quiz">Mini Quiz</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="testDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="testDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="totalDuration" class="form-label">Total Duration (minutes) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="totalDuration" name="total_duration_minutes" min="1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="breakDuration" class="form-label">Break Duration (minutes) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="breakDuration" name="break_duration_minutes" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="testStatus" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="testStatus" name="status" required>
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Test</button>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Existing Tests</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
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
                                    <td>{{ $test->title }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $test->test_type)) }}</td>
                                    <td><span class="badge bg-{{ $test->status === 'active' ? 'success' : ($test->status === 'draft' ? 'warning' : 'secondary') }}">{{ ucfirst($test->status) }}</span></td>
                                    <td>{{ $test->total_duration_minutes }}m</td>
                                    <td>
                                        <button class="btn btn-sm btn-danger delete-test-btn" data-id="{{ $test->id }}">Delete</button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No tests found</td>
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
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Create New Section</h5>
                </div>
                <div class="card-body">
                    <form id="sectionForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sectionTest" class="form-label">Test <span class="text-danger">*</span></label>
                                <select class="form-select" id="sectionTest" name="test_id" required>
                                    <option value="">Select test...</option>
                                    @foreach($tests as $test)
                                    <option value="{{ $test->id }}">{{ $test->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sectionName" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="sectionName" name="name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="sectionType" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="sectionType" name="type" required>
                                    <option value="">Select type...</option>
                                    <option value="reading_writing">Reading & Writing</option>
                                    <option value="math">Math</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="sectionOrder" class="form-label">Order <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="sectionOrder" name="order" min="1" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Section</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modules Tab -->
        <div class="tab-pane fade" id="modules" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Create New Module</h5>
                </div>
                <div class="card-body">
                    <form id="moduleForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="moduleSection" class="form-label">Section <span class="text-danger">*</span></label>
                                <select class="form-select" id="moduleSection" name="section_id" required>
                                    <option value="">Select section...</option>
                                    @foreach($tests as $test)
                                        @foreach($test->sections as $section)
                                        <option value="{{ $section->id }}">{{ $test->title }} - {{ $section->name }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="moduleNumber" class="form-label">Module Number <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="moduleNumber" name="module_number" min="1" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="moduleOrder" class="form-label">Order <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="moduleOrder" name="order" min="1" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="difficultyLevel" class="form-label">Difficulty Level <span class="text-danger">*</span></label>
                                <select class="form-select" id="difficultyLevel" name="difficulty_level" required>
                                    <option value="">Select...</option>
                                    <option value="standard">Standard</option>
                                    <option value="easy">Easy</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="moduleDuration" class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="moduleDuration" name="duration_minutes" min="1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="totalQuestions" class="form-label">Total Questions <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="totalQuestions" name="total_questions" min="1" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Module</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Passages Tab -->
        <div class="tab-pane fade" id="passages" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Create New Passage</h5>
                </div>
                <div class="card-body">
                    <form id="passageForm">
                        @csrf
                        <div class="mb-3">
                            <label for="passageContent" class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="passageContent" name="content" rows="6" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="passageType" class="form-label">Passage Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="passageType" name="passage_type" required>
                                    <option value="">Select...</option>
                                    <option value="single">Single</option>
                                    <option value="paired">Paired</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="genre" class="form-label">Genre <span class="text-danger">*</span></label>
                                <select class="form-select" id="genre" name="genre" required>
                                    <option value="">Select...</option>
                                    <option value="literary_narrative">Literary Narrative</option>
                                    <option value="social_science">Social Science</option>
                                    <option value="natural_science">Natural Science</option>
                                    <option value="humanities">Humanities</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="wordCount" class="form-label">Word Count</label>
                                <input type="number" class="form-control" id="wordCount" name="word_count" min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="sourceTitle" class="form-label">Source Title</label>
                                <input type="text" class="form-control" id="sourceTitle" name="source_title">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="sourceAuthor" class="form-label">Author</label>
                                <input type="text" class="form-control" id="sourceAuthor" name="source_author">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="sourceYear" class="form-label">Year</label>
                                <input type="number" class="form-control" id="sourceYear" name="source_year">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Passage</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Questions Tab -->
        <div class="tab-pane fade" id="questions" role="tabpanel">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Create New Question</h5>
                </div>
                <div class="card-body">
                    <form id="questionForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="questionModule" class="form-label">Module <span class="text-danger">*</span></label>
                                <select class="form-select" id="questionModule" name="module_id" required>
                                    <option value="">Select module...</option>
                                    @foreach($tests as $test)
                                        @foreach($test->sections as $section)
                                            @foreach($section->modules as $module)
                                            <option value="{{ $module->id }}">{{ $test->title }} - {{ $section->name }} - Module {{ $module->module_number }}</option>
                                            @endforeach
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="questionPosition" class="form-label">Position <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="questionPosition" name="position" min="1" value="1" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="questionPassage" class="form-label">Passage (optional)</label>
                                <select class="form-select" id="questionPassage" name="passage_id">
                                    <option value="">No passage</option>
                                    @foreach($passages as $passage)
                                    <option value="{{ $passage->id }}">{{ Str::limit($passage->content, 50) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="questionNumber" class="form-label">Question Number <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="questionNumber" name="question_number" min="1" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="questionType" class="form-label">Question Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="questionType" name="question_type" required>
                                    <option value="">Select...</option>
                                    <option value="multiple_choice">Multiple Choice</option>
                                    <option value="student_produced_response">Student Produced Response</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="questionStem" class="form-label">Question Stem <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="questionStem" name="stem" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="difficulty" class="form-label">Difficulty <span class="text-danger">*</span></label>
                                <select class="form-select" id="difficulty" name="difficulty" required>
                                    <option value="">Select...</option>
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="sectionType" class="form-label">Section Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="sectionType" name="section_type" required>
                                    <option value="">Select...</option>
                                    <option value="reading_writing">Reading & Writing</option>
                                    <option value="math">Math</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="skillDomain" class="form-label">Skill Domain <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="skillDomain" name="skill_domain" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="skillSubdomain" class="form-label">Skill Subdomain</label>
                                <input type="text" class="form-control" id="skillSubdomain" name="skill_subdomain">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="externalId" class="form-label">External ID</label>
                                <input type="text" class="form-control" id="externalId" name="external_id">
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="calculatorAllowed" name="calculator_allowed">
                                    <label class="form-check-label" for="calculatorAllowed">Calculator Allowed</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="sprHint" class="form-label">SPR Hint (for Student Produced Response)</label>
                            <input type="text" class="form-control" id="sprHint" name="spr_hint">
                        </div>
                        <button type="submit" class="btn btn-primary">Create Question</button>
                    </form>
                </div>
            </div>

            <!-- Answer Choices Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Add Answer Choices</h5>
                </div>
                <div class="card-body">
                    <form id="answerChoicesForm">
                        @csrf
                        <div class="mb-3">
                            <label for="answerQuestionId" class="form-label">Question <span class="text-danger">*</span></label>
                            <select class="form-select" id="answerQuestionId" name="question_id" required>
                                <option value="">Select question...</option>
                                @foreach($questions as $question)
                                <option value="{{ $question->id }}">Q{{ $question->question_number }}: {{ Str::limit($question->stem, 50) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="choicesContainer">
                            <div class="choice-row mb-3">
                                <div class="row">
                                    <div class="col-2">
                                        <input type="text" class="form-control" name="choices[0][label]" placeholder="Label (A)" required>
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control" name="choices[0][content]" placeholder="Content" required>
                                    </div>
                                    <div class="col-2">
                                        <input type="number" class="form-control" name="choices[0][order]" placeholder="Order" min="1" required>
                                    </div>
                                    <div class="col-2">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="choices[0][is_correct]">
                                            <label class="form-check-label">Correct</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary mb-3" id="addChoiceBtn">+ Add Choice</button>
                        <br>
                        <button type="submit" class="btn btn-primary">Save Answer Choices</button>
                    </form>
                </div>
            </div>

            <!-- Question Explanation Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Add Question Explanation</h5>
                </div>
                <div class="card-body">
                    <form id="explanationForm">
                        @csrf
                        <div class="mb-3">
                            <label for="explanationQuestionId" class="form-label">Question <span class="text-danger">*</span></label>
                            <select class="form-select" id="explanationQuestionId" name="question_id" required>
                                <option value="">Select question...</option>
                                @foreach($questions as $question)
                                <option value="{{ $question->id }}">Q{{ $question->question_number }}: {{ Str::limit($question->stem, 50) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="explanation" class="form-label">Explanation <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="explanation" name="explanation" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rationaleA" class="form-label">Rationale A</label>
                                <textarea class="form-control" id="rationaleA" name="rationale_a" rows="2"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rationaleB" class="form-label">Rationale B</label>
                                <textarea class="form-control" id="rationaleB" name="rationale_b" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rationaleC" class="form-label">Rationale C</label>
                                <textarea class="form-control" id="rationaleC" name="rationale_c" rows="2"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rationaleD" class="form-label">Rationale D</label>
                                <textarea class="form-control" id="rationaleD" name="rationale_d" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="strategyTip" class="form-label">Strategy Tip</label>
                            <textarea class="form-control" id="strategyTip" name="strategy_tip" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="commonMistakes" class="form-label">Common Mistakes</label>
                            <textarea class="form-control" id="commonMistakes" name="common_mistakes" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Explanation</button>
                    </form>
                </div>
            </div>

            <!-- Existing Questions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Existing Questions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>#</th>
                                    <th>Stem</th>
                                    <th>Type</th>
                                    <th>Difficulty</th>
                                    <th>Skill Domain</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="questionsTableBody">
                                @forelse($questions as $question)
                                <tr>
                                    <td>{{ $question->id }}</td>
                                    <td>{{ $question->question_number }}</td>
                                    <td>{{ Str::limit($question->stem, 50) }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $question->question_type)) }}</td>
                                    <td><span class="badge bg-{{ $question->difficulty === 'easy' ? 'success' : ($question->difficulty === 'medium' ? 'warning' : 'danger') }}">{{ ucfirst($question->difficulty) }}</span></td>
                                    <td>{{ $question->skill_domain }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-danger delete-question-btn" data-id="{{ $question->id }}">Delete</button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No questions found</td>
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
<div id="alertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;"></div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let choiceCount = 1;

    // Add choice button
    document.getElementById('addChoiceBtn').addEventListener('click', function() {
        const container = document.getElementById('choicesContainer');
        const labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        const label = labels[choiceCount] || String.fromCharCode(65 + choiceCount);
        
        const choiceRow = document.createElement('div');
        choiceRow.className = 'choice-row mb-3';
        choiceRow.innerHTML = `
            <div class="row">
                <div class="col-2">
                    <input type="text" class="form-control" name="choices[${choiceCount}][label]" placeholder="Label (${label})" required>
                </div>
                <div class="col-6">
                    <input type="text" class="form-control" name="choices[${choiceCount}][content]" placeholder="Content" required>
                </div>
                <div class="col-2">
                    <input type="number" class="form-control" name="choices[${choiceCount}][order]" placeholder="Order" min="1" value="${choiceCount + 1}" required>
                </div>
                <div class="col-2">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="choices[${choiceCount}][is_correct]">
                        <label class="form-check-label">Correct</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger mt-2 remove-choice">Remove</button>
                </div>
            </div>
        `;
        container.appendChild(choiceRow);
        choiceCount++;
    });

    // Remove choice (event delegation)
    document.getElementById('choicesContainer').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-choice')) {
            e.target.closest('.choice-row').remove();
        }
    });

    // Form submission handlers
    setupForm('testForm', '{{ route('test-dashboard.tests.store') }}', 'Test created successfully!');
    setupForm('sectionForm', '{{ route('test-dashboard.sections.store') }}', 'Section created successfully!');
    setupForm('moduleForm', '{{ route('test-dashboard.modules.store') }}', 'Module created successfully!');
    setupForm('passageForm', '{{ route('test-dashboard.passages.store') }}', 'Passage created successfully!');
    setupForm('questionForm', '{{ route('test-dashboard.questions.store') }}', 'Question created successfully!');
    setupForm('answerChoicesForm', '{{ route('test-dashboard.answer-choices.store') }}', 'Answer choices created successfully!');
    setupForm('explanationForm', '{{ route('test-dashboard.explanations.store') }}', 'Explanation created successfully!');

    // Delete handlers
    setupDelete('.delete-test-btn', '{{ route('test-dashboard.tests.delete', ':id') }}', 'Test deleted successfully!');
    setupDelete('.delete-section-btn', '{{ route('test-dashboard.sections.delete', ':id') }}', 'Section deleted successfully!');
    setupDelete('.delete-question-btn', '{{ route('test-dashboard.questions.delete', ':id') }}', 'Question deleted successfully!');
});

function setupForm(formId, url, successMessage) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Handle checkboxes
        if (form.querySelector('[name="calculator_allowed"]')) {
            data.calculator_allowed = form.querySelector('[name="calculator_allowed"]').checked;
        }
        
        // Handle answer choices array
        if (formId === 'answerChoicesForm') {
            data.choices = [];
            const choiceRows = document.querySelectorAll('.choice-row');
            choiceRows.forEach((row, index) => {
                const inputs = row.querySelectorAll('input');
                data.choices.push({
                    label: inputs[0].value,
                    content: inputs[1].value,
                    order: parseInt(inputs[2].value),
                    is_correct: inputs[3]?.checked || false
                });
            });
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
                showAlert('success', result.message || successMessage);
                form.reset();
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showAlert('danger', result.message || 'An error occurred');
            }
        } catch (error) {
            showAlert('danger', 'Network error: ' + error.message);
        }
    });
}

function setupDelete(selector, baseUrl, successMessage) {
    document.querySelectorAll(selector).forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to delete this item?')) return;
            
            const id = this.dataset.id;
            const url = baseUrl.replace(':id', id);

            try {
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const result = await response.json();

                if (response.ok) {
                    showAlert('success', result.message || successMessage);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert('danger', result.message || 'An error occurred');
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        });
    });
}

function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    container.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}
</script>
@endpush
</x-layouts.admin>
