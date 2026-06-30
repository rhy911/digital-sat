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
    public function show(UserTest $userTest)
    {
        $user = Auth::user();
        $this->authorize('view', $userTest);

        return view('student.scores.index', array_merge(
            ['user' => $user],
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
            ]
        ];

        foreach ($userTest->userAnswers as $answer) {
            $q = $answer->question;
            if (!$q || $q->is_pretest) continue;

            $section = $q->section_type === 'math' ? 'math' : 'reading_and_writing';
            $domain  = $q->skill_domain ?? 'Other';

            if (!isset($stats['sections'][$section]['domains'][$domain])) {
                $stats['sections'][$section]['domains'][$domain] = ['total' => 0, 'correct' => 0];
            }

            $stats['total']['questions']++;
            $stats['sections'][$section]['total']++;
            $stats['sections'][$section]['domains'][$domain]['total']++;

            if ($answer->selected_answer === null || $answer->selected_answer === '') {
                $stats['total']['omitted']++;
            } elseif ($answer->is_correct) {
                $stats['total']['correct']++;
                $stats['sections'][$section]['correct']++;
                $stats['sections'][$section]['domains'][$domain]['correct']++;
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

                $rows[] = [
                    'section' => $sectionLabel,
                    'domain' => $this->domainLabel($domain),
                    'correct' => $data['correct'],
                    'total' => $data['total'],
                    'percentCorrect' => $percentCorrect,
                    'coveragePercent' => $coveragePercent,
                    'performance' => $percentCorrect >= 80 ? 'High' : ($percentCorrect >= 50 ? 'Medium' : 'Low'),
                ];
            }
        }

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
