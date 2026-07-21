<?php

namespace App\Services;

use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Support\QuestionMediaUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScoreReportService
{
    private const DOMAIN_LABELS = [
        'craft_and_structure'               => 'Craft and Structure',
        'information_and_ideas'             => 'Information and Ideas',
        'standard_english_conventions'      => 'Standard English Conventions',
        'expression_of_ideas'               => 'Expression of Ideas',
        'algebra'                           => 'Algebra',
        'advanced_math'                     => 'Advanced Math',
        'problem_solving'                   => 'Problem-Solving and Data Analysis',
        'problem_solving_and_data_analysis' => 'Problem-Solving and Data Analysis',
        'geometry'                          => 'Geometry and Trigonometry',
        'geometry_and_trigonometry'         => 'Geometry and Trigonometry',
    ];

    /**
     * Build the full report data array for a UserTest (real or merged).
     */
    public function buildReportData(UserTest $userTest): array
    {
        $moduleIds   = $userTest->userAnswers->pluck('module_id')->filter()->unique()->values();
        $questionIds = $userTest->userAnswers->pluck('question_id')->filter()->unique()->values();

        $questionPositions = $this->loadQuestionPositions($moduleIds, $questionIds);
        $stats             = $this->computeStats($userTest->userAnswers);
        $answers           = $this->buildAnswerRows($userTest, $questionPositions);

        $rwAnswers   = collect($answers)->where('sectionType', 'rw');
        $mathAnswers = collect($answers)->where('sectionType', 'math');

        $totalQuestions  = $stats['total']['questions'];
        $accuracyPercent = $totalQuestions > 0
            ? (int) round(($stats['total']['correct'] / $totalQuestions) * 100)
            : 0;

        return [
            'userTest'            => $userTest,
            'stats'               => $stats,
            'questionPositions'   => $questionPositions,
            'allAnswers'          => $answers,
            'totalQ'              => $totalQuestions,
            'correct'             => $stats['total']['correct'],
            'wrong'               => $stats['total']['incorrect'],
            'omitted'             => $stats['total']['omitted'],
            'rwTotal'             => $stats['sections']['reading_and_writing']['total'],
            'rwCorrect'           => $stats['sections']['reading_and_writing']['correct'],
            'rwWrong'             => $rwAnswers->where('statusKey', 'wrong')->count(),
            'rwOmitted'           => $rwAnswers->where('statusKey', 'omitted')->count(),
            'mTotal'              => $stats['sections']['math']['total'],
            'mCorrect'            => $stats['sections']['math']['correct'],
            'mWrong'              => $mathAnswers->where('statusKey', 'wrong')->count(),
            'mOmitted'            => $mathAnswers->where('statusKey', 'omitted')->count(),
            'accuracyPercent'     => $accuracyPercent,
            'isScaledSatResult'   => in_array($userTest->test->test_type, ['full_length', 'adaptive_full_length'], true)
                                     && $userTest->total_score !== null,
            'domainSummaries'     => $this->domainSummaries($stats),
            'difficultySummaries' => $this->difficultySummaries($stats),
        ];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function loadQuestionPositions(Collection $moduleIds, Collection $questionIds): Collection
    {
        if ($moduleIds->isEmpty() || $questionIds->isEmpty()) {
            return collect();
        }

        return DB::table('module_questions')
            ->whereIn('module_id', $moduleIds)
            ->whereIn('question_id', $questionIds)
            ->get(['module_id', 'question_id', 'position'])
            ->mapWithKeys(fn ($row) => ["{$row->module_id}:{$row->question_id}" => $row->position]);
    }

    private function computeStats(Collection $userAnswers): array
    {
        $stats = [
            'total'    => ['questions' => 0, 'correct' => 0, 'incorrect' => 0, 'omitted' => 0],
            'sections' => [
                'reading_and_writing' => ['total' => 0, 'correct' => 0, 'domains' => []],
                'math'                => ['total' => 0, 'correct' => 0, 'domains' => []],
            ],
            'difficulty' => [],
        ];

        foreach ($userAnswers as $answer) {
            $q = $answer->question;
            if (! $q || $q->is_pretest) {
                continue;
            }

            $section    = $q->section_type === 'math' ? 'math' : 'reading_and_writing';
            $domain     = $q->skill_domain    ?? 'Other';
            $subdomain  = $q->skill_subdomain ?? 'Other';
            $difficulty = strtolower($q->difficulty ?? 'unknown');

            $stats['sections'][$section]['domains'][$domain]                      ??= ['total' => 0, 'correct' => 0, 'skills' => []];
            $stats['sections'][$section]['domains'][$domain]['skills'][$subdomain] ??= ['total' => 0, 'correct' => 0];
            $stats['difficulty'][$difficulty]                                       ??= ['total' => 0, 'correct' => 0];

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

        return $stats;
    }

    public function buildAnswerRows(UserTest $userTest, Collection $questionPositions): array
    {
        $rows       = [];
        $displayIdx = 0;

        foreach ($userTest->userAnswers as $answer) {
            $question = $answer->question;
            if (! $question || $question->is_pretest) {
                continue;
            }

            $displayIdx++;
            $isOmitted       = $answer->selected_answer === null || $answer->selected_answer === '';
            $statusKey       = $isOmitted ? 'omitted' : ($answer->is_correct ? 'correct' : 'wrong');
            $sectionType     = $question->section_type === 'math' ? 'math' : 'rw';
            $correctAnswer   = $this->correctAnswerFor($answer);
            $formattedDomain = $this->domainLabel($question->skill_domain ?? 'other');

            $rows[] = [
                'idx'          => $questionPositions->get("{$answer->module_id}:{$answer->question_id}", $displayIdx),
                'answer'       => $answer,
                'statusKey'    => $statusKey,
                'sectionType'  => $sectionType,
                'sectionName'  => $sectionType === 'math' ? 'Math' : 'Reading & Writing',
                'moduleNumber' => $answer->module?->module_number,
                'correctAnswer' => $correctAnswer,
                'domainLabel'  => $formattedDomain,
                'difficulty'   => $question->difficulty    ?? 'N/A',
                'timeSpent'    => $answer->time_spent,
                'expectedTime' => $question->expected_time,
                'questionData' => [
                    'stem'          => $this->markdown($question->stem ?? ''),
                    'explanation'   => $this->markdown($question->explanation?->explanation ?? 'No explanation available.'),
                    'correct_answer' => $correctAnswer,
                    'your_answer'   => $answer->selected_answer ?? 'Omitted',
                    'status'        => $statusKey,
                    'question_type' => $question->question_type,
                    'choices'       => $question->answerChoices
                        ->map(fn ($c) => [
                            'label'      => $c->label,
                            'content'    => $this->markdown($c->content ?? ''),
                            'is_correct' => (bool) $c->is_correct,
                        ])
                        ->toArray(),
                ],
            ];
        }

        return $rows;
    }

    public function correctAnswerFor(UserTestAnswer $answer): string
    {
        return $answer->question->sprCorrectAnswers->pluck('answer')->implode(', ')
            ?: $answer->question->answerChoices->where('is_correct', true)->first()?->label
            ?? 'N/A';
    }

    public function domainLabel(string $domain): string
    {
        return self::DOMAIN_LABELS[$domain]
            ?? Str::of($domain)->replace('_', ' ')->title()->toString();
    }

    public function domainSummaries(array $stats): array
    {
        $sections = [
            'reading_and_writing' => 'Reading and Writing',
            'math'                => 'Math',
        ];

        $rows = [];
        foreach ($sections as $sectionKey => $sectionLabel) {
            $sectionTotal = $stats['sections'][$sectionKey]['total'];

            foreach ($stats['sections'][$sectionKey]['domains'] as $domain => $data) {
                $percentCorrect  = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;
                $coveragePercent = $sectionTotal > 0 ? (int) round(($data['total'] / $sectionTotal) * 100) : 0;

                $skillsList = [];
                foreach ($data['skills'] ?? [] as $subdomain => $sData) {
                    $sPct = $sData['total'] > 0 ? (int) round(($sData['correct'] / $sData['total']) * 100) : 0;
                    $skillsList[] = [
                        'name'           => Str::of($subdomain)->replace('_', ' ')->title()->toString(),
                        'total'          => $sData['total'],
                        'correct'        => $sData['correct'],
                        'percentCorrect' => $sPct,
                    ];
                }
                usort($skillsList, fn ($a, $b) => $a['percentCorrect'] <=> $b['percentCorrect']);

                $rows[] = [
                    'section'         => $sectionLabel,
                    'sectionKey'      => $sectionKey === 'math' ? 'math' : 'rw',
                    'domain'          => $this->domainLabel($domain),
                    'correct'         => $data['correct'],
                    'total'           => $data['total'],
                    'percentCorrect'  => $percentCorrect,
                    'coveragePercent' => $coveragePercent,
                    'performance'     => $percentCorrect >= 80 ? 'High' : ($percentCorrect >= 50 ? 'Medium' : 'Low'),
                    'skills'          => $skillsList,
                ];
            }
        }

        return $rows;
    }

    public function difficultySummaries(array $stats): array
    {
        $order = ['easy' => 0, 'medium' => 1, 'hard' => 2, 'unknown' => 3];
        $rows  = [];

        foreach ($stats['difficulty'] as $difficulty => $data) {
            $percentCorrect = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;
            $rows[] = [
                'difficulty'     => $difficulty,
                'label'          => ucfirst($difficulty),
                'correct'        => $data['correct'],
                'total'          => $data['total'],
                'percentCorrect' => $percentCorrect,
                'performance'    => $percentCorrect >= 80 ? 'High' : ($percentCorrect >= 50 ? 'Medium' : 'Low'),
            ];
        }

        usort($rows, fn ($a, $b) => ($order[$a['difficulty']] ?? 99) <=> ($order[$b['difficulty']] ?? 99));

        return $rows;
    }

    private function markdown(string $content): string
    {
        return Str::markdown(
            QuestionMediaUrl::normalizeMarkdown($content),
            ['html_input' => 'strip', 'allow_unsafe_links' => false]
        );
    }
}
