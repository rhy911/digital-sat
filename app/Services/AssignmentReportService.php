<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;

class AssignmentReportService
{
    public function __construct(private AssignmentModuleTimingService $assignmentTiming) {}

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

        return [
            'recipient' => $recipient->loadMissing('student'),
            'attempts' => $attempts,
            'completed_count' => $completed->count(),
            'best' => $best,
            'in_progress' => $attempts->firstWhere('status', 'in_progress'),
            'late' => $best && $assignment->due_at ? $best->completed_at?->gt($assignment->due_at) : false,
        ];
    }

    public function build(Assignment $assignment, ?int $perPage = 15, ?int $page = null, bool $includeAnalysis = true): array
    {
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

        $recipientsQuery = $assignment->recipients()
            ->join('users', 'assignment_recipients.student_id', '=', 'users.id')
            ->select('assignment_recipients.*')
            ->with('student')
            ->orderBy('users.name', 'asc')
            ->orderBy('assignment_recipients.id', 'asc');
        $recipientsPaginator = $perPage !== null ? $recipientsQuery->paginate($perPage, ['*'], 'page', $page)->withQueryString() : $recipientsQuery->get();

        $attempts = \App\Models\UserTest::where('assignment_id', $assignment->id)
            ->whereIn('user_id', $recipientsPaginator->pluck('student_id'))
            ->with(['currentModule.section', 'userAnswers.question'])
            ->orderBy('attempt_number')
            ->get();
            
        $this->applyLiveElapsed($attempts);
        $attemptsByStudent = $attempts->groupBy('user_id');

        $rows = $recipientsPaginator->map(function ($recipient) use ($attemptsByStudent, $assignment) {
            $studentAttempts = $attemptsByStudent->get($recipient->student_id, collect());
            
            // Calculate correct and total questions for each attempt
            $studentAttempts->each(function ($attempt) {
                $nonPretestAnswers = $attempt->userAnswers->filter(function ($ans) {
                    return $ans->question && !$ans->question->is_pretest;
                });
                $attempt->total_questions_count = $nonPretestAnswers->count();
                $attempt->correct_answers_count = $nonPretestAnswers->where('is_correct', true)->count();
            });

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
                            'section_label' => $sec->section_type === 'math' ? 'Math' : 'Reading & Writing',
                            'module_label' => "Module {$mod->module_number}" . ($mod->difficulty_level === 'hard' ? ' (Hard)' : ($mod->difficulty_level === 'easy' ? ' (Easy)' : '')),
                            'total_presented' => 0,
                            'correct_count' => 0,
                            'incorrect_students' => [], // array of ['student' => User, 'status' => 'incorrect'|'omitted', 'selected' => string]
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
                        $questionsMap[$qId]['incorrect_students'][] = [
                            'student' => $student,
                            'status' => 'omitted',
                            'selected' => 'Omitted',
                        ];
                    } elseif ($answer->is_correct) {
                        $questionsMap[$qId]['correct_count']++;
                    } else {
                        $questionsMap[$qId]['incorrect_students'][] = [
                            'student' => $student,
                            'status' => 'incorrect',
                            'selected' => $answer->selected_answer,
                        ];
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
                    $diffWeight = $item['difficulty_level'] === 'standard' ? 1 : ($item['difficulty_level'] === 'easy' ? 2 : 3);
                    return sprintf('%d_%d_%03d', $item['module_number'], $diffWeight, $item['position']);
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
                $timing = $this->assignmentTiming->timing($attempt, $attempt->currentModule);
                $attempt->current_module_elapsed_seconds = $timing['elapsed_seconds'];
            });
    }
}
