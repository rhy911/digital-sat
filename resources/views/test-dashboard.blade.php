<x-layouts.admin title="Test Dashboard">
    @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
        <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.2/dist/css/tabulator_bootstrap5.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
        <style>
            .tom-select {
                height: auto !important;
            }

            .ts-control {
                border-radius: 0.375rem !important;
                padding: 0.5rem 0.75rem !important;
            }

            .ts-wrapper {
                position: relative !important;
                z-index: 5;
            }

            .ts-wrapper.focus {
                z-index: 1060 !important;
            }

            .ts-dropdown {
                z-index: 9999 !important;
                position: absolute !important;
            }

            .x-small {
                font-size: 0.75rem;
            }

            .font-monospace {
                font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
            }

            .editor-toolbar {
                border-color: #dee2e6;
                border-radius: 0.375rem 0.375rem 0 0;
            }

            .CodeMirror {
                border-color: #dee2e6;
                border-radius: 0 0 0.375rem 0.375rem;
            }

            .builder-block {
                transition: all 0.2s ease-in-out;
            }

            .builder-block:hover {
                border-color: #ffc107 !important;
            }

            .file-dropzone {
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .file-dropzone:hover {
                border-color: #0d6efd !important;
                background-color: rgba(13, 110, 253, 0.04) !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            }

            /* Preview elements typography and media parity styling */
            .passage-preview img, 
            .stem-preview img, 
            .edit-passage-preview img, 
            .edit-stem-preview img,
            #editQuestionPreviewContent img,
            .builder-block-preview img {
                display: block !important;
                margin: 12px auto !important;
                width: 55% !important;
                max-width: 55% !important;
                height: auto !important;
            }
            .passage-preview, 
            .stem-preview, 
            .edit-passage-preview, 
            .edit-stem-preview,
            #editQuestionPreviewContent,
            .builder-block-preview {
                font-family: "Noto Serif", Georgia, serif !important;
            }
            .passage-preview p, 
            .stem-preview p, 
            .edit-passage-preview p, 
            .edit-stem-preview p,
            #editQuestionPreviewContent p,
            .builder-block-preview p {
                margin-bottom: 0 !important;
            }
            .passage-preview ol, 
            .stem-preview ol, 
            .edit-passage-preview ol, 
            .edit-stem-preview ol,
            #editQuestionPreviewContent ol,
            .builder-block-preview ol {
                list-style-type: decimal !important;
                padding-left: 1.5rem !important;
                margin-bottom: 1rem !important;
            }
            .passage-preview ul, 
            .stem-preview ul, 
            .edit-passage-preview ul, 
            .edit-stem-preview ul,
            #editQuestionPreviewContent ul,
            .builder-block-preview ul {
                list-style-type: disc !important;
                padding-left: 1.5rem !important;
                margin-bottom: 1rem !important;
            }
            .passage-preview li, 
            .stem-preview li, 
            .edit-passage-preview li, 
            .edit-stem-preview li,
            #editQuestionPreviewContent li,
            .builder-block-preview li {
                display: list-item !important;
            }
        </style>
    @endpush

    <div class="container-fluid py-4">
        <div id="alert-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1060;"></div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Test Dashboard</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#quickAuthorWizardModal">
                    <i class="bi bi-magic"></i> + Create SAT Content
                </button>
                <button class="btn btn-outline-primary"
                    onclick="refreshTestDashboardData(captureTomSelectPreservation(null))">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Data
                </button>
            </div>
        </div>

        <ul class="nav nav-pills mb-4 shadow-sm p-2 bg-white rounded" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tests-tab" data-bs-toggle="tab" data-bs-target="#tests"
                    type="button" role="tab">Tests</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections"
                    type="button" role="tab">Sections</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="modules-tab" data-bs-toggle="tab" data-bs-target="#modules" type="button"
                    role="tab">Modules</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="questions-tab" data-bs-toggle="tab" data-bs-target="#questions"
                    type="button" role="tab">Questions & Bank</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="builder-tab" data-bs-toggle="tab" data-bs-target="#builder" type="button"
                    role="tab">
                    <i class="bi bi-magic"></i> Easy Builder
                </button>
            </li>
        </ul>

        <div class="tab-content" id="dashboardTabContent">
            <x-test-dashboard.tests-tab :tests="$tests" />
            <x-test-dashboard.sections-tab :tests="$tests" />
            <x-test-dashboard.modules-tab :tests="$tests" :all-modules="$allModules" />
            <x-test-dashboard.questions-tab :tests="$tests" :questions="$questions" :questions-total="$questionsTotal" />
            <x-test-dashboard.builder-tab :tests="$tests" />
        </div>
    </div>

    <x-test-dashboard.modals />
    <x-test-dashboard.quick-author-wizard />

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.2/dist/js/tabulator.min.js"></script>
        <script>
            window.TestDashboardConfig = {
                SNAPSHOT_URL: "{{ route('test-dashboard.snapshot') }}",
                QUESTIONS_LIST_URL: "{{ route('test-dashboard.questions.list') }}",
                QUESTIONS_SEARCH_URL: "{{ route('test-dashboard.questions.search') }}",
                CSV_BULK_URL: "{{ route('test-dashboard.questions.bulk-csv-store') }}",
                BULK_PREVIEW_URL: "{{ route('test-dashboard.questions.bulk-preview') }}",
                CSV_BULK_PREVIEW_URL: "{{ route('test-dashboard.questions.bulk-csv-preview') }}",
                BULK_STORE_URL: "{{ route('test-dashboard.questions.bulk-store') }}",
                MEDIA_UPLOAD_URL: "{{ route('test-dashboard.media.upload') }}",
                TESTS_STORE_URL: "{{ route('test-dashboard.tests.store') }}",
                SECTIONS_STORE_URL: "{{ route('test-dashboard.sections.store') }}",
                SECTIONS_LINK_MODULE_URL: "{{ route('test-dashboard.sections.link-module') }}",
                MODULES_STORE_URL: "{{ route('test-dashboard.modules.store') }}",
                QUESTIONS_ATTACH_URL: "{{ route('test-dashboard.questions.attach') }}",
                BASE_URL: "/test-dashboard",
                QUESTIONS_PER_PAGE: 25
            };

            window.TestDashboardExamples = {
                RW_JSON: {
                    items: [{
                        stem: 'Which choice best describes the **main idea** of the text?',
                        question_type: 'multiple_choice',
                        difficulty: 'medium',
                        skill_domain: 'information_and_ideas',
                        passage: {
                            content: 'The researcher noted that early observations were incomplete, yet they shaped every later hypothesis.',
                            source_title: 'Field notes (fictional sample)'
                        },
                        choices: [{
                                label: 'A',
                                content: 'Early observations were useless.',
                                is_correct: false
                            },
                            {
                                label: 'B',
                                content: 'Initial incomplete work influenced later science.',
                                is_correct: true
                            },
                            {
                                label: 'C',
                                content: 'Later teams refused to use older data.',
                                is_correct: false
                            },
                            {
                                label: 'D',
                                content: 'Hypotheses are never revised.',
                                is_correct: false
                            }
                        ],
                        explanation: 'The passage stresses that early incomplete observations still shaped later hypotheses.'
                    }]
                },
                MATH_JSON: {
                    items: [{
                            stem: 'What is **2 + 2**?',
                            question_type: 'multiple_choice',
                            difficulty: 'easy',
                            skill_domain: 'algebra',
                            choices: [{
                                    label: 'A',
                                    content: '3',
                                    is_correct: false
                                },
                                {
                                    label: 'B',
                                    content: '4',
                                    is_correct: true
                                },
                                {
                                    label: 'C',
                                    content: '5',
                                    is_correct: false
                                },
                                {
                                    label: 'D',
                                    content: '6',
                                    is_correct: false
                                }
                            ],
                            explanation: 'The sum of 2 and 2 is 4.'
                        },
                        {
                            stem: 'If $$x^2 = 9$$, what is the **positive** value of $$x$$?',
                            question_type: 'student_produced_response',
                            difficulty: 'medium',
                            skill_domain: 'advanced_math',
                            spr_correct_answers: ['3'],
                            spr_hint: 'Enter a positive number only.',
                            explanation: 'The positive square root of 9 is 3.'
                        }
                    ]
                }
            };
        </script>
        @vite(['resources/js/test-dashboard.js'])
    @endpush
</x-layouts.admin>
