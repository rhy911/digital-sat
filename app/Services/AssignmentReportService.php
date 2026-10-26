<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Question;
use App\Models\UserTest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignmentReportService
{
    public function __construct(private AttemptEngine $engine) {}

    /**
     * @return array<string, mixed>
     */
    public function buildRecipient(Assignment $assignment, AssignmentRecipient $recipient): array
    {
        $attempts = $assignment->attempts()
            ->where('user_id', $recipient->student_id)
            ->with([
                'user',
                'currentModule.section',
                'userAnswers.module.section',
                'userAnswers.question.answerChoices',
                'userAnswers.question.sprCorrectAnswers',
                'userAnswers.question.explanation',
                'moduleSubmissions',
            ])
            ->orderBy('attempt_number')
            ->get();
        $this->applyLiveElapsed($attempts);
        $completed = $attempts->where('status', 'completed');
        $best = $completed->sortByDesc('total_score')->first();

        $recipient->setRelation('assignment', $assignment);

        return [
            'recipient' => $recipient->loadMissing('student'),
            'attempts' => $attempts,
            'completed_count' => $completed->count(),
            'best' => $best,
            'in_progress' => $attempts->firstWhere('status', 'in_progress'),
            'late' => $best && $assignment->due_at ? $best->completed_at?->gt($assignment->due_at) : false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        Assignment $assignment,
        ?int $perPage = 15,
        ?int $page = null,
        bool $includeAnalysis = true,
        ?string $search = null,
        string $sortField = 'name',
        string $sortDirection = 'asc'
    ): array {
        $assignment->load(['classroom', 'test']);

        $activeRecipientIds = $assignment->recipients()->where('status', 'active')->pluck('student_id');
        $assignedCount = $activeRecipientIds->count();

        $bestScores = \App\Models\UserTest::where('assignment_id', $assignment->id)
            ->whereIn('user_id', $activeRecipientIds)
            ->where('status', 'completed')
            ->selectRaw('user_id, MAX(total_score) as best_score, MAX(score_reading_writing) as best_rw, MAX(score_math) as best_math')
            ->groupBy('user_id')
            ->get();

        $completedCount = $bestScores->count();
        $averageScore = $completedCount ? (int) round($bestScores->average('best_score')) : null;
        $averageRw = $completedCount ? (int) round($bestScores->average('best_rw')) : null;
        $averageMath = $completedCount ? (int) round($bestScores->average('best_math')) : null;

        $inProgressCount = \App\Models\UserTest::where('assignment_id', $assignment->id)
            ->whereIn('user_id', $activeRecipientIds)
            ->where('status', 'in_progress')
            ->distinct('user_id')
            ->count('user_id');

        $rankingScoreCol = match (true) {
            $assignment->assign_type === 'section' && $assignment->section_type === 'reading_writing' => 'score_reading_writing',
            $assignment->assign_type === 'section' => 'score_math',
            default => 'total_score',
        };

        $rankedSubquery = UserTest::query()
            ->select([
                'user_id',
                'id as best_attempt_id',
                'total_score as best_total_score',
                'score_reading_writing as best_rw',
                'score_math as best_math',
                'completed_at as best_completed_at',
                DB::raw("ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY {$rankingScoreCol} DESC, id DESC) as rn"),
            ])
            ->where('assignment_id', $assignment->id)
            ->where('status', 'completed');

        $bestAttemptsSubquery = DB::query()
            ->fromSub($rankedSubquery, 'ranked_tests')
            ->where('rn', 1);

        $attemptAggSubquery = UserTest::query()
            ->select([
                'user_id',
                DB::raw('COUNT(*) as total_count'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count"),
                DB::raw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count"),
            ])
            ->where('assignment_id', $assignment->id)
            ->groupBy('user_id');

        $attemptLimit = (int) ($assignment->attempt_limit ?? 1);
        $retryOrCompleted = $assignment->acceptsNewStarts() ? 'Retry available' : 'Completed';
        $assignmentClosed = $assignment->status === 'closed';
        $isOverdue = ($assignment->due_at && now()->gte($assignment->due_at));
        $isUpcoming = ($assignment->available_at && now()->lt($assignment->available_at));

        $statusCaseSql = "CASE
            WHEN assignment_recipients.status = 'withdrawn' THEN 'Withdrawn'
            WHEN COALESCE(attempt_agg.in_progress_count, 0) > 0 THEN 'In progress'
            WHEN COALESCE(attempt_agg.total_count, 0) >= ({$attemptLimit} + COALESCE(assignment_recipients.extra_attempts, 0)) THEN 'Completed'
            WHEN COALESCE(attempt_agg.completed_count, 0) > 0 THEN '{$retryOrCompleted}'
            WHEN " . ($assignmentClosed ? "1 = 1" : "1 = 0") . " THEN 'Closed'
            WHEN " . ($isOverdue ? "1 = 1" : "1 = 0") . " THEN 'Overdue'
            WHEN " . ($isUpcoming ? "1 = 1" : "1 = 0") . " THEN 'Upcoming'
            ELSE 'Open'
        END";

        $recipientsQuery = $assignment->recipients()
            ->join('users', 'assignment_recipients.student_id', '=', 'users.id')
            ->leftJoinSub($bestAttemptsSubquery, 'best_attempts', 'best_attempts.user_id', '=', 'assignment_recipients.student_id')
            ->leftJoinSub($attemptAggSubquery, 'attempt_agg', 'attempt_agg.user_id', '=', 'assignment_recipients.student_id')
            ->select('assignment_recipients.*')
            ->with('student');

        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $recipientsQuery->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)
                  ->orWhere('users.email', 'like', $like);
            });
        }

        $dir = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
        switch ($sortField) {
            case 'score':
                $scoreCol = ($assignment->assign_type === 'section')
                    ? ($assignment->section_type === 'reading_writing' ? 'best_attempts.best_rw' : 'best_attempts.best_math')
                    : 'best_attempts.best_total_score';
                $recipientsQuery->orderByRaw("COALESCE({$scoreCol}, -1) {$dir}")
                                ->orderBy('users.name', 'asc')
                                ->orderBy('assignment_recipients.id', 'asc');
                break;

            case 'rw':
                $recipientsQuery->orderByRaw("COALESCE(best_attempts.best_rw, -1) {$dir}")
                                ->orderBy('users.name', 'asc')
                                ->orderBy('assignment_recipients.id', 'asc');
                break;

            case 'math':
                $recipientsQuery->orderByRaw("COALESCE(best_attempts.best_math, -1) {$dir}")
                                ->orderBy('users.name', 'asc')
                                ->orderBy('assignment_recipients.id', 'asc');
                break;

            case 'attempts':
                $recipientsQuery->orderByRaw("COALESCE(attempt_agg.completed_count, 0) {$dir}")
                                ->orderBy('users.name', 'asc')
                                ->orderBy('assignment_recipients.id', 'asc');
                break;

            case 'status':
                $recipientsQuery->orderByRaw("({$statusCaseSql}) {$dir}")
                                ->orderBy('users.name', 'asc')
                                ->orderBy('assignment_recipients.id', 'asc');
                break;

            case 'name':
            default:
                $recipientsQuery->orderBy('users.name', $dir)
                                ->orderBy('assignment_recipients.id', 'asc');
                break;
        }

        $recipientsPaginator = null;
        $pageRecipients = collect();
        if ($perPage === 0) {
            $rows = collect();
        } elseif ($perPage !== null) {
            $currentPage = $page ?? \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
            $totalCount = (clone $recipientsQuery)->reorder()->count('assignment_recipients.id');

            $pageRecipients = $recipientsQuery
                ->forPage($currentPage, $perPage)
                ->get();
        } else {
            $totalCount = null;
            $pageRecipients = $recipientsQuery->get();
        }

        if ($perPage !== 0) {
            $pageStudentIds = $pageRecipients->pluck('student_id');

            $pageAttempts = $pageStudentIds->isEmpty()
                ? collect()
                : UserTest::query()
                    ->where('assignment_id', $assignment->id)
                    ->whereIn('user_id', $pageStudentIds)
                    ->with(['currentModule.section'])
                    ->withCount([
                        'userAnswers as total_questions_count',
                        'userAnswers as correct_answers_count' => function ($q) {
                            $q->where('is_correct', true);
                        },
                    ])
                    ->orderBy('attempt_number')
                    ->get();

            $this->applyLiveElapsed($pageAttempts);
            $attemptsByStudent = $pageAttempts->groupBy('user_id');

            $rows = $pageRecipients->map(function ($recipient) use ($attemptsByStudent, $assignment) {
                $recipient->setRelation('assignment', $assignment);
                $studentAttempts = $attemptsByStudent->get($recipient->student_id, collect());
                $completed = $studentAttempts->where('status', 'completed');
                if ($assignment->assign_type === 'section') {
                    $sortKey = $assignment->section_type === 'reading_writing' ? 'score_reading_writing' : 'score_math';
                    $best = $completed->sortByDesc($sortKey)->first();
                } else {
                    $best = $completed->sortByDesc('total_score')->first();
                }

                return [
                    'recipient' => $recipient,
                    'status' => \App\Support\AssignmentStatusResolver::resolve($assignment, $studentAttempts, $recipient),
                    'attempts' => $studentAttempts,
                    'completed_count' => $completed->count(),
                    'best' => $best,
                    'in_progress' => $studentAttempts->firstWhere('status', 'in_progress'),
                    'late' => $best && $assignment->due_at ? $best->completed_at?->gt($assignment->due_at) : false,
                ];
            });

            if ($perPage !== null) {
                $recipientsPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                    $rows,
                    $totalCount,
                    $perPage,
                    $currentPage,
                    ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => request()->query()]
                );
            }
        }

        $questionAnalysis = [];

        if ($includeAnalysis) {
            // 1. Gather all questions present in the assigned test / section
            $sectionQuery = $assignment->test->sections();
            if ($assignment->assign_type === 'section') {
                $sectionQuery->where('type', $assignment->section_type === 'reading_writing' ? 'reading_writing' : 'math');
            }
            $sections = $sectionQuery->with([
                'modules.questions.answerChoices',
                'modules.questions.sprCorrectAnswers',
                'modules.questions.explanation',
                'modules.questions.passage',
            ])->get();

            $questionsMap = [];
            foreach ($sections as $sec) {
                foreach ($sec->modules as $mod) {
                    foreach ($mod->questions as $q) {
                        $correctAns = '—';
                        if ($q->question_type === 'multiple_choice') {
                            $correctChoice = $q->answerChoices->where('is_correct', true)->first();
                            $correctAns = $correctChoice ? $correctChoice->label : '—';
                        } else {
                            $correctAns = $q->sprCorrectAnswers->pluck('answer')->implode(', ');
                        }

                        $questionsMap[$q->id] = [
                            'question' => $q,
                            'correct_answer' => $correctAns,
                            'position' => $q->pivot->position,
                            'module_number' => $mod->module_number,
                            'difficulty_level' => $mod->difficulty_level,
                            'section_label' => in_array($sec->type ?? $sec->section_type, ['math', \App\Models\Section::TYPE_MATH], true) ? 'Math' : 'Reading & Writing',
                            'section_type' => in_array($sec->type ?? $sec->section_type, ['math', \App\Models\Section::TYPE_MATH], true) ? \App\Models\Section::TYPE_MATH : 'reading_writing',
                            'module_label' => "Module {$mod->module_number}" . ($mod->difficulty_level === 'hard' ? ' (Hard)' : ($mod->difficulty_level === 'easy' ? ' (Easy)' : '')),
                            'total_presented' => 0,
                            'correct_count' => 0,
                            'incorrect_students' => [], // array of ['student' => User, 'status' => 'incorrect'|'omitted', 'selected' => string]
                            'students' => [], // complete list of student responses: ['student' => User, 'status' => 'correct'|'incorrect'|'omitted', 'selected' => string]
                        ];
                    }
                }
            }

            // 2. Fetch all completed attempts for this assignment to compute stats
            $allCompletedAttempts = \App\Models\UserTest::where('assignment_id', $assignment->id)
                ->where('status', 'completed')
                ->with(['user', 'userAnswers'])
                ->get();

            foreach ($allCompletedAttempts as $attempt) {
                $student = $attempt->user;
                foreach ($attempt->userAnswers as $answer) {
                    $qId = $answer->question_id;
                    if (!isset($questionsMap[$qId])) continue;

                    $questionsMap[$qId]['total_presented']++;

                    if ($answer->selected_answer === null || $answer->selected_answer === '') {
                        $record = [
                            'student' => $student,
                            'status' => 'omitted',
                            'selected' => 'Omitted',
                        ];
                        $questionsMap[$qId]['incorrect_students'][] = $record;
                        $questionsMap[$qId]['students'][] = $record;
                    } elseif ($answer->is_correct) {
                        $questionsMap[$qId]['correct_count']++;
                        $questionsMap[$qId]['students'][] = [
                            'student' => $student,
                            'status' => 'correct',
                            'selected' => $answer->selected_answer,
                        ];
                    } else {
                        $record = [
                            'student' => $student,
                            'status' => 'incorrect',
                            'selected' => $answer->selected_answer,
                        ];
                        $questionsMap[$qId]['incorrect_students'][] = $record;
                        $questionsMap[$qId]['students'][] = $record;
                    }
                }
            }

            // 3. Filter to only show questions that were presented to at least 1 student
            /** @var array<int|string, array<string, mixed>> $questionsMap */
            $questionAnalysis = collect($questionsMap)
                ->filter(fn ($item) => $item['total_presented'] > 0)
                ->map(function ($item) {
                    $incorrectCount = count($item['incorrect_students']);
                    $item['incorrect_rate'] = (int) round(($incorrectCount / $item['total_presented']) * 100);

                    return $item;
                })
                ->sortBy(function ($item) {
                    $secWeight = $item['section_type'] === \App\Models\Section::TYPE_MATH ? 2 : 1;
                    $diffWeight = match ($item['difficulty_level']) {
                        'standard' => 1,
                        'easy' => 2,
                        default => 3,
                    };

                    return [$secWeight, $item['module_number'], $diffWeight, $item['position']];
                })
                ->values()
                ->all();
        }

        return [
            'paginator' => $perPage !== null ? $recipientsPaginator : null,
            'rows' => $rows,
            'question_count' => $this->getAssignmentQuestionCount($assignment),
            'questionAnalysis' => $questionAnalysis,
            'metrics' => [
                'assigned' => $assignedCount,
                'completed' => $completedCount,
                'in_progress' => $inProgressCount,
                'average_score' => $averageScore,
                'average_rw' => $averageRw,
                'average_math' => $averageMath,
            ],
        ];
    }

    public function getAssignmentQuestionCount(Assignment $assignment): int
    {
        $sectionQuery = DB::table('module_questions')
            ->join('section_modules', 'section_modules.module_id', '=', 'module_questions.module_id')
            ->join('sections', 'sections.id', '=', 'section_modules.section_id')
            ->join('questions', 'questions.id', '=', 'module_questions.question_id')
            ->where('sections.test_id', $assignment->test_id)
            ->whereNull('sections.deleted_at')
            ->whereNull('questions.deleted_at');

        if ($assignment->isSectionAssignment()) {
            $sectionQuery->where('sections.type', $assignment->section_type === 'reading_writing' ? 'reading_writing' : 'math');
        }

        return (int) $sectionQuery->distinct()->count('module_questions.question_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAnalysisSummary(Assignment $assignment): array
    {
        $assignment->loadMissing(['classroom', 'test']);

        $sectionQuery = $assignment->test->sections();
        if ($assignment->assign_type === 'section') {
            $sectionQuery->where('type', $assignment->section_type === 'reading_writing' ? 'reading_writing' : 'math');
        }
        $sections = $sectionQuery->with([
            'modules.questions.answerChoices',
            'modules.questions.sprCorrectAnswers',
        ])->get();

        $questionsMap = [];
        foreach ($sections as $sec) {
            foreach ($sec->modules as $mod) {
                foreach ($mod->questions as $q) {
                    $correctAns = '—';
                    if ($q->question_type === 'multiple_choice') {
                        $correctChoice = $q->answerChoices->firstWhere('is_correct', true);
                        $correctAns = $correctChoice ? $correctChoice->label : '—';
                    } else {
                        $correctAns = $q->sprCorrectAnswers->pluck('answer')->implode(', ');
                    }

                    $questionsMap[$q->id] = [
                        'id' => $q->id,
                        'position' => $q->pivot->position,
                        'module_label' => "Module {$mod->module_number}" . ($mod->difficulty_level === 'hard' ? ' (Hard)' : ($mod->difficulty_level === 'easy' ? ' (Easy)' : '')),
                        'module_number' => $mod->module_number,
                        'difficulty_level' => $mod->difficulty_level,
                        'difficulty' => strtolower($q->difficulty ?? 'standard'),
                        'domain' => Str::headline(str_replace('_', ' ', filled($q->skill_domain) ? $q->skill_domain : 'General Domain')),
                        'section_label' => in_array($sec->type ?? $sec->section_type, ['math', \App\Models\Section::TYPE_MATH], true) ? 'Math' : 'Reading & Writing',
                        'section_type' => in_array($sec->type ?? $sec->section_type, ['math', \App\Models\Section::TYPE_MATH], true) ? \App\Models\Section::TYPE_MATH : 'reading_writing',
                        'question_type' => $q->question_type,
                        'is_pretest' => (bool) $q->is_pretest,
                        'stem' => \App\Support\QuestionContentRenderer::markdown(Str::limit($q->stem, 200)),
                        'correct_answer' => $correctAns,
                        'incorrect_rate' => 0,
                        'total_presented' => 0,
                        'correct_count' => 0,
                        'incorrect_count' => 0,
                        'students' => [],
                        'choices' => [],
                        'spr_answers' => [],
                        'explanation' => ['text' => null, 'strategy_tip' => null],
                        'passage_body' => null,
                        'passage_title' => null,
                        'details_loaded' => false,
                        'loading_details' => false,
                        'load_error' => null,
                    ];
                }
            }
        }

        $allCompletedAttempts = \App\Models\UserTest::where('assignment_id', $assignment->id)
            ->where('status', 'completed')
            ->with(['userAnswers:id,user_test_id,question_id,selected_answer,is_correct'])
            ->get();

        foreach ($allCompletedAttempts as $attempt) {
            foreach ($attempt->userAnswers as $answer) {
                $qId = $answer->question_id;
                if (!isset($questionsMap[$qId])) continue;

                $questionsMap[$qId]['total_presented']++;
                if ($answer->is_correct) {
                    $questionsMap[$qId]['correct_count']++;
                } else {
                    $questionsMap[$qId]['incorrect_count']++;
                }
            }
        }

        /** @var array<int|string, array<string, mixed>> $questionsMap */
        $questionList = collect($questionsMap)
            ->filter(fn ($item) => $item['total_presented'] > 0)
            ->map(function ($item) {
                $item['incorrect_rate'] = (int) round(($item['incorrect_count'] / $item['total_presented']) * 100);

                return $item;
            })
            ->sortBy(function ($item) {
                $secWeight = $item['section_type'] === \App\Models\Section::TYPE_MATH ? 2 : 1;
                $diffWeight = match ($item['difficulty_level']) {
                    'standard' => 1,
                    'easy' => 2,
                    default => 3,
                };

                return [$secWeight, $item['module_number'], $diffWeight, $item['position']];
            })
            ->values();

        $uniqueModules = $questionList->map(fn($q) => $q['section_label'] . ' — ' . $q['module_label'])->unique()->values();
        $uniqueDomains = $questionList->pluck('domain')->filter()->unique()->values();
        $avgErrorRate = $questionList->count() > 0 ? (int) round($questionList->avg('incorrect_rate')) : 0;
        $mostChallenging = $questionList->sortByDesc('incorrect_rate')->first();

        return [
            'questions' => $questionList->all(),
            'stats' => [
                'total' => $questionList->count(),
                'unique_modules' => $uniqueModules->all(),
                'unique_domains' => $uniqueDomains->all(),
                'avg_error_rate' => $avgErrorRate,
                'most_challenging' => $mostChallenging,
            ],
        ];
    }

    public function assignmentContainsQuestion(Assignment $assignment, Question $question): bool
    {
        $assignment->loadMissing('test');
        if (! $assignment->test_id || ! $assignment->test) {
            return false;
        }

        $sectionQuery = $assignment->test->sections();
        if ($assignment->isSectionAssignment()) {
            $sectionQuery->where('type', $assignment->section_type === 'reading_writing' ? 'reading_writing' : 'math');
        }

        return $sectionQuery->whereHas('modules.questions', function ($q) use ($question) {
            $q->where('questions.id', $question->id);
        })->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildQuestionInspection(Assignment $assignment, \App\Models\Question $question): array
    {
        $assignment->loadMissing(['classroom', 'test']);

        $question->loadMissing([
            'answerChoices',
            'sprCorrectAnswers',
            'explanation',
            'passage',
        ]);

        $choices = $question->answerChoices->map(fn($c) => [
            'label' => $c->label,
            'content' => \App\Support\QuestionContentRenderer::markdown($c->content),
            'is_correct' => (bool)$c->is_correct,
        ])->values()->all();

        $sprAnswers = $question->question_type === 'student_produced_response'
            ? $question->sprCorrectAnswers->pluck('answer')->all()
            : [];

        $allCompletedAttempts = \App\Models\UserTest::where('assignment_id', $assignment->id)
            ->where('status', 'completed')
            ->with(['user', 'userAnswers' => fn($q) => $q->where('question_id', $question->id)])
            ->get();

        $students = [];
        foreach ($allCompletedAttempts as $attempt) {
            $student = $attempt->user;
            $answer = $attempt->userAnswers->first();
            if (!$answer) continue;

            $status = ($answer->selected_answer === null || $answer->selected_answer === '')
                ? 'omitted'
                : ($answer->is_correct ? 'correct' : 'incorrect');

            $students[] = [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'initials' => $student->initials ?? strtoupper(substr($student->name, 0, 1)),
                'status' => $status,
                'selected' => ($answer->selected_answer === null || $answer->selected_answer === '') ? 'Omitted' : $answer->selected_answer,
            ];
        }

        return [
            'id' => $question->id,
            'is_pretest' => (bool)$question->is_pretest,
            'stem' => \App\Support\QuestionContentRenderer::markdown($question->stem),
            'passage_title' => $question->passage?->title,
            'passage_body' => $question->passage
                ? \App\Support\QuestionContentRenderer::markdown($question->passage->content ?? $question->passage->body ?? '')
                : null,
            'choices' => $choices,
            'spr_answers' => $sprAnswers,
            'explanation' => [
                'text' => $question->explanation?->explanation ? \App\Support\QuestionContentRenderer::markdown($question->explanation->explanation) : null,
                'strategy_tip' => $question->explanation?->strategy_tip ? \App\Support\QuestionContentRenderer::markdown($question->explanation->strategy_tip) : null,
            ],
            'students' => $students,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, UserTest>  $attempts
     */
    private function applyLiveElapsed(\Illuminate\Support\Collection $attempts): void
    {
        $attempts
            ->where('status', 'in_progress')
            ->filter(fn ($attempt) => $attempt->assignment_id && $attempt->currentModule && $attempt->current_module_started_at)
            ->each(function ($attempt) {
                $timing = $this->engine->timing($attempt, $attempt->currentModule);
                $attempt->current_module_elapsed_seconds = $timing['elapsed_seconds'];
            });
    }
}
