<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\UserTest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScoreController extends Controller
{
    public function index()
    {
        $latest = UserTest::where('user_id', Auth::id())
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->first();

        if (!$latest) {
            return view('student.scores.empty', ['user' => Auth::user()]);
        }

        return redirect()->route('student.scores.show', $latest);
    }

    public function show(UserTest $userTest)
    {
        $user = Auth::user();
        $this->authorize('view', $userTest);
        $userTest->loadMissing('assignment.classroom');

        $attempts = UserTest::with(['test', 'assignment.classroom'])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->get();

        return view('student.scores.show', array_merge(
            ['user' => $user, 'attempts' => $attempts, 'selectedUlid' => $userTest->ulid],
            $this->scoreReportData($userTest)
        ));
    }

    public function exportPdf(UserTest $userTest)
    {
        $this->authorize('view', $userTest);

        $report = $this->scoreReportData($userTest);
        $filename = sprintf(
            'score-report-%s-%s.pdf',
            Str::slug($report['userTest']->test->title ?: 'test-result'),
            optional($report['userTest']->completed_at)->format('Y-m-d') ?: now()->format('Y-m-d')
        );

        return Pdf::loadView('student.scores.export-pdf', $report)
            ->setPaper('letter')
            ->download($filename);
    }

    private function scoreReportData(UserTest $userTest): array
    {
        $userTest->load(['test', 'user', 'scoreConversionSet', 'userAnswers.module', 'userAnswers.question.explanation', 'userAnswers.question.answerChoices', 'userAnswers.question.sprCorrectAnswers']);
        $moduleIds = $userTest->userAnswers->pluck('module_id')->filter()->unique()->values();
        $questionIds = $userTest->userAnswers->pluck('question_id')->filter()->unique()->values();
        $questionPositions = collect();

        if ($moduleIds->isNotEmpty() && $questionIds->isNotEmpty()) {
            $questionPositions = DB::table('module_questions')
                ->whereIn('module_id', $moduleIds)
                ->whereIn('question_id', $questionIds)
                ->get(['module_id', 'question_id', 'position'])
                ->mapWithKeys(fn ($row) => ["{$row->module_id}:{$row->question_id}" => $row->position]);
        }

        $stats = [
            'total' => ['questions' => 0, 'correct' => 0, 'incorrect' => 0, 'omitted' => 0],
            'sections' => [
                'reading_and_writing' => [
                    'total' => 0, 'correct' => 0,
                    'domains' => []
                ],
                'math' => [
                    'total' => 0, 'correct' => 0,
                    'domains' => []
                ]
            ],
            'difficulty' => [],
        ];

        foreach ($userTest->userAnswers as $answer) {
            $q = $answer->question;
            if (!$q || $q->is_pretest) continue;

            $section = $q->section_type === 'math' ? 'math' : 'reading_and_writing';
            $domain  = $q->skill_domain ?? 'Other';
            $subdomain = $q->skill_subdomain ?? 'Other';
            $difficulty = strtolower($q->difficulty ?? 'unknown');

            if (!isset($stats['sections'][$section]['domains'][$domain])) {
                $stats['sections'][$section]['domains'][$domain] = ['total' => 0, 'correct' => 0, 'skills' => []];
            }

            if (!isset($stats['sections'][$section]['domains'][$domain]['skills'][$subdomain])) {
                $stats['sections'][$section]['domains'][$domain]['skills'][$subdomain] = ['total' => 0, 'correct' => 0];
            }

            if (!isset($stats['difficulty'][$difficulty])) {
                $stats['difficulty'][$difficulty] = ['total' => 0, 'correct' => 0];
            }

            $stats['total']['questions']++;
            $stats['sections'][$section]['total']++;
            $stats['sections'][$section]['domains'][$domain]['total']++;
            $stats['sections'][$section]['domains'][$domain]['skills'][$subdomain]['total']++;
            $stats['difficulty'][$difficulty]['total']++;

            if ($answer->selected_answer === null || $answer->selected_answer === '') {
                $stats['total']['omitted']++;
            } elseif ($answer->is_correct) {
                $stats['total']['correct']++;
                $stats['sections'][$section]['correct']++;
                $stats['sections'][$section]['domains'][$domain]['correct']++;
                $stats['sections'][$section]['domains'][$domain]['skills'][$subdomain]['correct']++;
                $stats['difficulty'][$difficulty]['correct']++;
            } else {
                $stats['total']['incorrect']++;
            }
        }

        $answers = $this->buildAnswerRows($userTest, $questionPositions);
        $rwAnswers = collect($answers)->where('sectionType', 'rw');
        $mathAnswers = collect($answers)->where('sectionType', 'math');
        $totalQuestions = $stats['total']['questions'];
        $accuracyPercent = $totalQuestions > 0
            ? (int) round(($stats['total']['correct'] / $totalQuestions) * 100)
            : 0;

        return [
            'userTest' => $userTest,
            'stats' => $stats,
            'questionPositions' => $questionPositions,
            'allAnswers' => $answers,
            'totalQ' => $totalQuestions,
            'correct' => $stats['total']['correct'],
            'wrong' => $stats['total']['incorrect'],
            'omitted' => $stats['total']['omitted'],
            'rwTotal' => $stats['sections']['reading_and_writing']['total'],
            'rwCorrect' => $stats['sections']['reading_and_writing']['correct'],
            'rwWrong' => $rwAnswers->where('statusKey', 'wrong')->count(),
            'rwOmitted' => $rwAnswers->where('statusKey', 'omitted')->count(),
            'mTotal' => $stats['sections']['math']['total'],
            'mCorrect' => $stats['sections']['math']['correct'],
            'mWrong' => $mathAnswers->where('statusKey', 'wrong')->count(),
            'mOmitted' => $mathAnswers->where('statusKey', 'omitted')->count(),
            'accuracyPercent' => $accuracyPercent,
            'isScaledSatResult' => in_array($userTest->test->test_type, ['full_length', 'adaptive_full_length'], true)
                && $userTest->total_score !== null,
            'domainSummaries' => $this->domainSummaries($stats),
            'difficultySummaries' => $this->difficultySummaries($stats),
        ];
    }

    private function buildAnswerRows(UserTest $userTest, $questionPositions): array
    {
        $rows = [];
        $displayIdx = 0;

        foreach ($userTest->userAnswers as $answer) {
            $question = $answer->question;
            if (! $question || $question->is_pretest) {
                continue;
            }

            $displayIdx++;
            $isOmitted = $answer->selected_answer === null || $answer->selected_answer === '';
            $statusKey = $isOmitted ? 'omitted' : ($answer->is_correct ? 'correct' : 'wrong');
            $sectionType = $question->section_type === 'math' ? 'math' : 'rw';
            $correctAnswer = $this->correctAnswerFor($answer);
            $rawDomain = $question->skill_domain ?? 'other';
            $formattedDomain = $this->domainLabel($rawDomain);

            $rows[] = [
                'idx' => $questionPositions->get("{$answer->module_id}:{$answer->question_id}", $displayIdx),
                'answer' => $answer,
                'statusKey' => $statusKey,
                'sectionType' => $sectionType,
                'sectionName' => $sectionType === 'math' ? 'Math' : 'Reading & Writing',
                'moduleNumber' => $answer->module?->module_number,
                'correctAnswer' => $correctAnswer,
                'domainLabel' => $formattedDomain,
                'difficulty' => $question->difficulty ?? 'N/A',
                'timeSpent' => $answer->time_spent,
                'expectedTime' => $question->expected_time,
                'questionData' => [
                    'stem' => $this->markdown($question->stem ?? ''),
                    'explanation' => $this->markdown($question->explanation?->explanation ?? 'No explanation available.'),
                    'correct_answer' => $correctAnswer,
                    'your_answer' => $answer->selected_answer ?? 'Omitted',
                    'status' => $statusKey,
                    'question_type' => $question->question_type,
                    'choices' => $question->answerChoices
                        ->map(fn ($choice) => [
                            'label' => $choice->label,
                            'content' => $this->markdown($choice->content ?? ''),
                            'is_correct' => (bool) $choice->is_correct,
                        ])
                        ->toArray(),
                ],
            ];
        }

        return $rows;
    }

    private function correctAnswerFor($answer): string
    {
        return $answer->question->sprCorrectAnswers->pluck('answer')->implode(', ')
            ?: $answer->question->answerChoices->where('is_correct', true)->first()?->label
            ?? 'N/A';
    }

    private function domainLabel(string $domain): string
    {
        return [
            'craft_and_structure' => 'Craft and Structure',
            'information_and_ideas' => 'Information and Ideas',
            'standard_english_conventions' => 'Standard English Conventions',
            'expression_of_ideas' => 'Expression of Ideas',
            'algebra' => 'Algebra',
            'advanced_math' => 'Advanced Math',
            'problem_solving' => 'Problem-Solving and Data Analysis',
            'problem_solving_and_data_analysis' => 'Problem-Solving and Data Analysis',
            'geometry' => 'Geometry and Trigonometry',
            'geometry_and_trigonometry' => 'Geometry and Trigonometry',
        ][$domain] ?? Str::of($domain)->replace('_', ' ')->title()->toString();
    }

    private function domainSummaries(array $stats): array
    {
        $sections = [
            'reading_and_writing' => 'Reading and Writing',
            'math' => 'Math',
        ];

        $rows = [];
        foreach ($sections as $sectionKey => $sectionLabel) {
            $sectionTotal = $stats['sections'][$sectionKey]['total'];
            foreach ($stats['sections'][$sectionKey]['domains'] as $domain => $data) {
                $percentCorrect = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;
                $coveragePercent = $sectionTotal > 0 ? (int) round(($data['total'] / $sectionTotal) * 100) : 0;

                $skillsList = [];
                if (isset($data['skills'])) {
                    foreach ($data['skills'] as $subdomain => $sData) {
                        $sPct = $sData['total'] > 0 ? (int) round(($sData['correct'] / $sData['total']) * 100) : 0;
                        $skillsList[] = [
                            'name' => Str::of($subdomain)->replace('_', ' ')->title()->toString(),
                            'total' => $sData['total'],
                            'correct' => $sData['correct'],
                            'percentCorrect' => $sPct,
                        ];
                    }
                    usort($skillsList, fn ($a, $b) => $a['percentCorrect'] <=> $b['percentCorrect']);
                }

                $rows[] = [
                    'section' => $sectionLabel,
                    'sectionKey' => $sectionKey === 'math' ? 'math' : 'rw',
                    'domain' => $this->domainLabel($domain),
                    'correct' => $data['correct'],
                    'total' => $data['total'],
                    'percentCorrect' => $percentCorrect,
                    'coveragePercent' => $coveragePercent,
                    'performance' => $percentCorrect >= 80 ? 'High' : ($percentCorrect >= 50 ? 'Medium' : 'Low'),
                    'skills' => $skillsList,
                ];
            }
        }

        return $rows;
    }

    private function difficultySummaries(array $stats): array
    {
        $order = ['easy' => 0, 'medium' => 1, 'hard' => 2, 'unknown' => 3];
        $rows = [];

        foreach ($stats['difficulty'] as $difficulty => $data) {
            $percentCorrect = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;

            $rows[] = [
                'difficulty' => $difficulty,
                'label' => ucfirst($difficulty),
                'correct' => $data['correct'],
                'total' => $data['total'],
                'percentCorrect' => $percentCorrect,
                'performance' => $percentCorrect >= 80 ? 'High' : ($percentCorrect >= 50 ? 'Medium' : 'Low'),
            ];
        }

        usort($rows, fn ($a, $b) => ($order[$a['difficulty']] ?? 99) <=> ($order[$b['difficulty']] ?? 99));

        return $rows;
    }

    private function markdown(string $content): string
    {
        return Str::markdown(\App\Support\QuestionMediaUrl::normalizeMarkdown($content), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
