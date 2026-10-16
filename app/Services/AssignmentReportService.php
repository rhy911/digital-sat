<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\UserTest;
use Illuminate\Support\Facades\DB;

class AssignmentReportService
{
    public function __construct(private AttemptEngine $engine) {}

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
        if ($perPage !== null) {
            $currentPage = $page ?? \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
            $totalCount = (clone $recipientsQuery)->reorder()->count('assignment_recipients.id');

            $pageRecipients = $recipientsQuery
                ->forPage($currentPage, $perPage)
                ->get();
        } else {
            $totalCount = null;
            $pageRecipients = $recipientsQuery->get();
        }

        $pageStudentIds = $pageRecipients->pluck('student_id');

        $pageAttempts = $pageStudentIds->isEmpty()
            ? collect()
            : UserTest::query()
                ->where('assignment_id', $assignment->id)
                ->whereIn('user_id', $pageStudentIds)
                ->with(['currentModule.section'])
                ->withCount([
                    'userAnswers as total_questions_count' => function ($q) {
                        $q->whereHas('question', fn ($qq) => $qq->where('is_pretest', false));
                    },
                    'userAnswers as correct_answers_count' => function ($q) {
                        $q->where('is_correct', true)
                          ->whereHas('question', fn ($qq) => $qq->where('is_pretest', false));
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
                        if ($q->is_pretest) continue;
                        
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
            $questionAnalysis = collect($questionsMap)
                ->filter(fn ($item) => $item['total_presented'] > 0)
                ->map(function ($item) {
                    $incorrectCount = count($item['incorrect_students']);
                    $item['incorrect_rate'] = $item['total_presented'] > 0 
                        ? (int) round(($incorrectCount / $item['total_presented']) * 100) 
                        : 0;
                    return $item;
                })
                ->sortBy(function ($item) {
                    $secWeight = ($item['section_type'] ?? '') === \App\Models\Section::TYPE_MATH ? 2 : 1;
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

    private function applyLiveElapsed($attempts): void
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
