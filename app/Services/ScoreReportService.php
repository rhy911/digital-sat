<?php

namespace App\Services;

use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Support\QuestionContentRenderer;
use App\Support\QuestionPacing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScoreReportService
{
    private ?bool $hasReviewStorage = null;

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

        $totalQuestions  = $stats['total']['questions'];
        $accuracyPercent = $totalQuestions > 0
            ? (int) round(($stats['total']['correct'] / $totalQuestions) * 100)
            : 0;

        $scorePresentation = $this->buildScorePresentation($userTest, $stats);

        return [
            'userTest'            => $userTest,
            'stats'               => $stats,
            'questionPositions'   => $questionPositions,
            'allAnswers'          => $answers,
            'totalQ'              => $totalQuestions,
            'correct'             => $stats['total']['correct'],
            'wrong'               => $stats['total']['incorrect'],
            'omitted'             => $stats['total']['omitted'],
            'unscored'            => $stats['total']['unscored'],
            'rwTotal'             => $stats['sections']['reading_and_writing']['total'],
            'rwCorrect'           => $stats['sections']['reading_and_writing']['correct'],
            'rwWrong'             => $stats['sections']['reading_and_writing']['incorrect'],
            'rwOmitted'           => $stats['sections']['reading_and_writing']['omitted'],
            'rwUnscored'          => $stats['sections']['reading_and_writing']['unscored'],
            'mTotal'              => $stats['sections']['math']['total'],
            'mCorrect'            => $stats['sections']['math']['correct'],
            'mWrong'              => $stats['sections']['math']['incorrect'],
            'mOmitted'            => $stats['sections']['math']['omitted'],
            'mUnscored'           => $stats['sections']['math']['unscored'],
            'accuracyPercent'     => $accuracyPercent,
            'isScaledSatResult'   => $scorePresentation['isScaledSatResult'],
            'scorePresentation'   => $scorePresentation,
            'domainSummaries'     => $this->domainSummaries($stats),
            'difficultySummaries' => $this->difficultySummaries($stats),
            'difficultyBySection' => $this->difficultySummariesBySection($stats),
            'questionDataMap'     => collect($answers)->keyBy(fn ($r) => $r['answer']->id)->map(fn ($r) => $r['questionData'])->all(),
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
            'total'    => ['questions' => 0, 'correct' => 0, 'incorrect' => 0, 'omitted' => 0, 'unscored' => 0],
            'sections' => [
                'reading_and_writing' => [
                    'total' => 0,
                    'correct' => 0,
                    'incorrect' => 0,
                    'omitted' => 0,
                    'unscored' => 0,
                    'domains' => [],
                    'difficulty' => [],
                ],
                'math' => [
                    'total' => 0,
                    'correct' => 0,
                    'incorrect' => 0,
                    'omitted' => 0,
                    'unscored' => 0,
                    'domains' => [],
                    'difficulty' => [],
                ],
            ],
            'difficulty' => [],
        ];

        foreach ($userAnswers as $answer) {
            $q = $answer->question;
            if (! $q) {
                continue;
            }

            $rawSec     = $q->section_type;
            $section    = in_array($rawSec, ['math', 'Math'], true) ? 'math' : 'reading_and_writing';
            $domain     = $q->skill_domain ?: 'Other';
            $subdomain  = $q->skill_subdomain ?: 'Other';
            $difficulty = strtolower($q->difficulty ?: 'unknown');
            $isPretest  = (bool) ($q->is_pretest ?? false);

            $stats['sections'][$section]['domains'][$domain]                      ??= ['total' => 0, 'correct' => 0, 'skills' => []];
            $stats['sections'][$section]['domains'][$domain]['skills'][$subdomain] ??= ['total' => 0, 'correct' => 0];
            $stats['difficulty'][$difficulty]                                       ??= ['total' => 0, 'correct' => 0];
            $stats['sections'][$section]['difficulty'][$difficulty]                 ??= ['total' => 0, 'correct' => 0];

            $stats['total']['questions']++;
            $stats['sections'][$section]['total']++;
            $stats['sections'][$section]['domains'][$domain]['total']++;
            $stats['sections'][$section]['domains'][$domain]['skills'][$subdomain]['total']++;
            $stats['difficulty'][$difficulty]['total']++;
            $stats['sections'][$section]['difficulty'][$difficulty]['total']++;

            if ($isPretest) {
                $stats['total']['unscored']++;
                $stats['sections'][$section]['unscored']++;
            }

            if ($answer->selected_answer === null || $answer->selected_answer === '') {
                $stats['total']['omitted']++;
                $stats['sections'][$section]['omitted']++;
            } elseif ($answer->is_correct) {
                $stats['total']['correct']++;
                $stats['sections'][$section]['correct']++;
                $stats['sections'][$section]['domains'][$domain]['correct']++;
                $stats['sections'][$section]['domains'][$domain]['skills'][$subdomain]['correct']++;
                $stats['difficulty'][$difficulty]['correct']++;
                $stats['sections'][$section]['difficulty'][$difficulty]['correct']++;
            } else {
                $stats['total']['incorrect']++;
                $stats['sections'][$section]['incorrect']++;
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
            if (! $question) {
                continue;
            }

            $displayIdx++;
            $isOmitted       = $answer->selected_answer === null || $answer->selected_answer === '';
            $statusKey       = $isOmitted ? 'omitted' : ($answer->is_correct ? 'correct' : 'wrong');
            $sectionType     = in_array($question->section_type, ['math', 'Math'], true) ? 'math' : 'rw';
            $correctAnswer   = $this->correctAnswerFor($answer);
            $formattedDomain = $this->domainLabel($question->skill_domain ?? 'other');
            $review          = $this->reviewFor($answer);

            // Saved module position wins; legacy fallback is current test definition, then sequential counter
            $idx = $question->module_position
                ?? $questionPositions->get("{$answer->module_id}:{$answer->question_id}")
                ?? $displayIdx;

            $difficulty = $question->difficulty;
            $expectedTimeValue = $question->expected_time;
            $isFallbackExpected = empty($expectedTimeValue);
            $expectedTime = (int) ($expectedTimeValue ?: QuestionPacing::expectedSeconds($sectionType, $difficulty));
            $pacing = QuestionPacing::interpret($answer->time_spent, $expectedTime, $isFallbackExpected);

            // Passage: snapshot passage content wins, then relation fallback (handled by $question->passage)
            $rawPassage = $question->passage?->content;
            $passage = $rawPassage ? QuestionContentRenderer::markdown($rawPassage) : null;

            // Stem: snapshot stem wins, then relation fallback
            $stem = QuestionContentRenderer::markdown($question->stem ?? '');

            // Choices: snapshot choices win, then relation fallback
            $choices = $question->answerChoices
                ? $question->answerChoices
                    ->sortBy('order')
                    ->map(fn ($c) => [
                        'label'      => $c->label,
                        'content'    => QuestionContentRenderer::markdown($c->content ?? ''),
                        'is_correct' => (bool) $c->is_correct,
                    ])
                    ->values()
                    ->toArray()
                : [];

            // Explanation: snapshot explanation wins, preserving explicit null
            $rawExplanation = $question->explanation?->explanation;
            $hasExplanation = ! empty(trim($rawExplanation ?? ''));
            $explanationHtml = $hasExplanation ? QuestionContentRenderer::markdown($rawExplanation) : null;

            $isPretest = (bool) ($question->is_pretest ?? false);
            $questionType = $question->question_type ?: 'multiple_choice';

            $questionData = [
                'id'                   => $answer->id,
                'idx'                  => $idx,
                'review_url'           => route('student.scores.answers.review', [$userTest, $answer]),
                'reviewable'           => $this->hasReviewStorage() && ! $answer->is_correct,
                'effective_error_type' => $review?->effectiveErrorType($answer)
                    ?? ($isOmitted ? 'omitted' : 'unclassified'),
                'student_error_type'   => $review?->student_error_type,
                'teacher_error_type'   => $review?->teacher_error_type,
                'passage'              => $passage,
                'stem'                 => $stem,
                'explanation'          => $explanationHtml,
                'has_explanation'      => $hasExplanation,
                'correct_answer'       => $correctAnswer,
                'your_answer'          => $answer->selected_answer ?? 'Omitted',
                'status'               => $statusKey,
                'question_type'        => $questionType,
                'is_pretest'           => $isPretest,
                'section_type'         => $sectionType,
                'section_name'         => $sectionType === 'math' ? 'Math' : 'Reading & Writing',
                'module_number'        => $answer->module?->module_number,
                'domain_label'         => $formattedDomain,
                'difficulty'           => $difficulty ?? 'N/A',
                'pacing'               => $pacing,
                'time_spent'           => $answer->time_spent,
                'expected_time'        => $expectedTime,
                'is_fallback_expected' => $isFallbackExpected,
                'choices'              => $choices,
            ];

            $rows[] = [
                'idx'                => $idx,
                'answer'             => $answer,
                'statusKey'          => $statusKey,
                'sectionType'        => $sectionType,
                'sectionName'        => $sectionType === 'math' ? 'Math' : 'Reading & Writing',
                'moduleNumber'       => $answer->module?->module_number,
                'correctAnswer'      => $correctAnswer,
                'domainLabel'        => $formattedDomain,
                'difficulty'         => $difficulty ?? 'N/A',
                'timeSpent'          => $answer->time_spent,
                'expectedTime'       => $expectedTime,
                'isFallbackExpected' => $isFallbackExpected,
                'pacing'             => $pacing,
                'isPretest'          => $isPretest,
                'reviewUrl'          => route('student.scores.answers.review', [$userTest, $answer]),
                'questionData'       => $questionData,
            ];
        }

        return $rows;
    }

    public function buildScorePresentation(UserTest $userTest, array $stats): array
    {
        $isSectionOnly = $userTest->attempt_type === 'section';
        $isScaledSatResult = in_array($userTest->test?->test_type, ['full_length', 'adaptive_full_length'], true)
            && $userTest->total_score !== null;

        $availableSubjects = [];
        if (($stats['sections']['reading_and_writing']['total'] ?? 0) > 0) {
            $availableSubjects[] = 'rw';
        }
        if (($stats['sections']['math']['total'] ?? 0) > 0) {
            $availableSubjects[] = 'math';
        }
        if (count($availableSubjects) === 0) {
            $availableSubjects = ['rw', 'math'];
        }

        if ($isSectionOnly) {
            $rawSec = $userTest->section_type ?? '';
            $sectionType = in_array($rawSec, ['reading_writing', 'reading_and_writing', 'rw'], true) ? 'rw' : 'math';
            $score = $sectionType === 'rw' ? $userTest->score_reading_writing : $userTest->score_math;
            $hasScaledScore = $score !== null;

            if ($hasScaledScore) {
                $lower = $sectionType === 'rw' ? $userTest->score_reading_writing_lower : $userTest->score_math_lower;
                $upper = $sectionType === 'rw' ? $userTest->score_reading_writing_upper : $userTest->score_math_upper;
                $hasRange = $lower !== null && $upper !== null;
                $rangeFormatted = $this->formatScoreRange($lower, $upper);

                $meta = $this->resolveScoringPresentation($userTest, true, $lower, $upper);

                return array_merge([
                    'type' => 'section',
                    'isScaledSatResult' => false,
                    'isSectionOnly' => true,
                    'sectionType' => $sectionType,
                    'sectionLabel' => $sectionType === 'rw' ? 'Reading & Writing' : 'Math',
                    'contextLabel' => $meta['contextLabel'],
                    'score' => $score,
                    'maxScore' => 800,
                ], $this->buildCommonScaledMeta($userTest, $meta, $hasRange, $rangeFormatted, $availableSubjects));
            }

            // Raw / unscaled section attempt
            $secKey = $sectionType === 'rw' ? 'reading_and_writing' : 'math';
            $secCorrect = $stats['sections'][$secKey]['correct'] ?? 0;
            $secTotal = $stats['sections'][$secKey]['total'] ?? 0;

            return [
                'type' => 'raw_section',
                'isScaledSatResult' => false,
                'isSectionOnly' => true,
                'sectionType' => $sectionType,
                'sectionLabel' => ($sectionType === 'rw' ? 'Reading & Writing' : 'Math') . ' Section Practice',
                'contextLabel' => 'Practice performance',
                'score' => $secCorrect,
                'maxScore' => $secTotal,
                'hasRange' => false,
                'rangeFormatted' => null,
                'basisLabel' => 'Raw question accuracy',
                'scoreDisclosure' => 'Practice performance, not a calibrated SAT score.',
                'calculationDisclosure' => 'This section practice attempt does not produce a scaled SAT score estimate. Results reflect observed correct answers out of all practice questions in this section.',
                'unofficialNotice' => 'Not an official College Board score.',
                'availableSubjects' => $availableSubjects,
                'rwScore' => null,
                'rwRange' => null,
                'mathScore' => null,
                'mathRange' => null,
            ];
        }

        if ($isScaledSatResult) {
            $lower = $userTest->total_score_lower;
            $upper = $userTest->total_score_upper;
            $hasRange = $lower !== null && $upper !== null;
            $rangeFormatted = $this->formatScoreRange($lower, $upper);

            $meta = $this->resolveScoringPresentation($userTest, false, $lower, $upper);

            return array_merge([
                'type' => 'full',
                'isScaledSatResult' => true,
                'isSectionOnly' => false,
                'sectionType' => null,
                'sectionLabel' => 'Total SAT Practice Score',
                'contextLabel' => $meta['contextLabel'],
                'score' => $userTest->total_score,
                'maxScore' => 1600,
            ], $this->buildCommonScaledMeta($userTest, $meta, $hasRange, $rangeFormatted, $availableSubjects));
        }

        // Raw / unscaled practice attempt
        return [
            'type' => 'raw',
            'isScaledSatResult' => false,
            'isSectionOnly' => false,
            'sectionType' => null,
            'sectionLabel' => 'Raw Practice Score',
            'contextLabel' => 'Practice performance',
            'score' => $stats['total']['correct'],
            'maxScore' => $stats['total']['questions'],
            'hasRange' => false,
            'rangeFormatted' => null,
            'basisLabel' => 'Raw question accuracy',
            'scoreDisclosure' => 'Practice performance, not a calibrated SAT score.',
            'calculationDisclosure' => 'This practice test type does not produce a scaled SAT score estimate. Results reflect observed correct answers out of all practice questions.',
            'unofficialNotice' => 'Not an official College Board score.',
            'availableSubjects' => $availableSubjects,
            'rwScore' => null,
            'rwRange' => null,
            'mathScore' => null,
            'mathRange' => null,
        ];
    }

    private function formatScoreRange(?int $lower, ?int $upper): ?string
    {
        return ($lower !== null && $upper !== null) ? "{$lower} – {$upper}" : null;
    }

    private function buildCommonScaledMeta(UserTest $userTest, array $meta, bool $hasRange, ?string $rangeFormatted, array $availableSubjects): array
    {
        return [
            'hasRange' => $hasRange,
            'rangeFormatted' => $rangeFormatted,
            'basisLabel' => $meta['basisLabel'],
            'scoreDisclosure' => $meta['scoreDisclosure'],
            'calculationDisclosure' => $meta['calculationDisclosure'],
            'unofficialNotice' => 'Not an official College Board score.',
            'availableSubjects' => $availableSubjects,
            'rwScore' => $userTest->score_reading_writing,
            'rwRange' => $this->formatScoreRange($userTest->score_reading_writing_lower, $userTest->score_reading_writing_upper),
            'mathScore' => $userTest->score_math,
            'mathRange' => $this->formatScoreRange($userTest->score_math_lower, $userTest->score_math_upper),
        ];
    }

    private function resolveScoringPresentation(
        UserTest $userTest,
        bool $isSectionOnly,
        ?int $lower = null,
        ?int $upper = null
    ): array {
        $kind = $userTest->score_estimate_kind;
        $hasConversionSet = ! empty($userTest->score_conversion_set_id) && $userTest->scoreConversionSet !== null;
        $conversionVersion = $userTest->score_conversion_version;

        $isOfficial = $kind === 'official_form' || ($hasConversionSet && ! in_array($kind, ['normal_generic', 'generic', 'generic_ds_v1', 'adaptive_irt_provisional'], true));
        $isAdaptive = $kind === 'adaptive_irt_provisional';
        $isNormalGeneric = $kind === 'normal_generic';
        $isLegacyGeneric = in_array($kind, ['generic', 'generic_ds_v1'], true) || $conversionVersion === 'generic_ds_v1';
        $scopeSuffix = $isSectionOnly ? ' in this section' : '';

        if ($isAdaptive) {
            $scopeWord = $isSectionOnly ? 'section' : 'test';
            $rangeText = ($lower !== null && $upper !== null)
                ? " Your score is likely between {$lower} and {$upper}."
                : '';

            return [
                'basisLabel' => 'Adaptive IRT provisional estimate',
                'contextLabel' => 'Estimated practice score',
                'scoreDisclosure' => "This is an estimate based on the questions you saw.{$rangeText}",
                'calculationDisclosure' => "This {$scopeWord} used an adaptive route, so question difficulty adjusted based on your answers. We use a statistical model (EAP 3PL) to estimate your ability from the questions you actually saw. The scaled score mapping for this route is still provisional, which is why the result is shown as a range.",
            ];
        }

        if ($isNormalGeneric) {
            $versionSuffix = $conversionVersion ? " (v{$conversionVersion})" : ' (v1.0)';

            return [
                'basisLabel' => $isSectionOnly ? 'Standard conversion table' : "Standard conversion{$versionSuffix}",
                'contextLabel' => 'Estimated practice score · Normal conversion',
                'scoreDisclosure' => "This estimate uses all the questions you answered{$scopeSuffix}, converted with our standard scoring table.",
                'calculationDisclosure' => $isSectionOnly
                    ? 'This section score was converted using our standard Digital SAT scoring table.'
                    : 'Your raw score was converted to a 400–1600 scale using our standard conversion table' . ($conversionVersion ? " (v{$conversionVersion})" : '') . '. A form-specific conversion table, once available, may give a slightly more precise result.',
            ];
        }

        if ($isOfficial) {
            $setVersion = $userTest->scoreConversionSet?->version ?? '1.0';

            return [
                'basisLabel' => "Official form conversion (v{$setVersion})",
                'contextLabel' => 'Estimated practice score',
                'scoreDisclosure' => "This estimate uses all the questions you answered{$scopeSuffix}, converted with the official scoring table for this test.",
                'calculationDisclosure' => $isSectionOnly
                    ? 'This section was scored using the official conversion table for this test form.'
                    : "Your raw score was converted to a 400–1600 scale using the official conversion table for this test form (v{$setVersion}).",
            ];
        }

        if ($isLegacyGeneric) {
            $versionSuffix = $conversionVersion ? " ({$conversionVersion})" : '';

            return [
                'basisLabel' => "Legacy standard conversion{$versionSuffix}",
                'contextLabel' => 'Estimated practice score',
                'scoreDisclosure' => "This estimate uses all the questions you answered{$scopeSuffix}, converted with our legacy scoring table.",
                'calculationDisclosure' => $isSectionOnly
                    ? 'This section was scored using all questions answered, converted using our legacy standard conversion table.'
                    : 'Your raw score was converted using a legacy scoring conversion table (' . ($conversionVersion ?? 'legacy') . '). An official form conversion was not recorded for this attempt.',
            ];
        }

        // Unknown / unrecorded legacy kind with no conversion set
        return [
            'basisLabel' => $isSectionOnly ? 'Raw conversion' : 'Legacy conversion',
            'contextLabel' => 'Estimated practice score',
            'scoreDisclosure' => "This estimate uses all the questions you answered{$scopeSuffix}.",
            'calculationDisclosure' => $isSectionOnly
                ? 'This section was scored using all questions answered, converted to the 200–800 scale.'
                : 'Your score was calculated using legacy scoring conversion. No official form conversion set was recorded for this attempt.',
        ];
    }

    private function reviewFor(UserTestAnswer $answer): ?\App\Models\UserTestAnswerReview
    {
        if (! $this->hasReviewStorage()) {
            return null;
        }

        return $answer->relationLoaded('review') ? $answer->review : $answer->review()->first();
    }

    private function hasReviewStorage(): bool
    {
        return $this->hasReviewStorage ??= Schema::hasTable('user_test_answer_reviews');
    }

    public function correctAnswerFor(UserTestAnswer $answer): string
    {
        $question = $answer->question;
        if (! $question) {
            return 'N/A';
        }

        $spr = $question->sprCorrectAnswers?->pluck('answer')?->implode(', ');
        if (! empty($spr)) {
            return $spr;
        }

        return $question->answerChoices?->where('is_correct', true)->first()?->label ?? 'N/A';
    }

    public function domainLabel(string $domain): string
    {
        return \App\Support\SatTaxonomy::domainLabel($domain);
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

                $aggregatedSkills = [];
                foreach ($data['skills'] ?? [] as $subdomain => $sData) {
                    $skillName = \App\Support\SatTaxonomy::subdomainLabel($subdomain);
                    if (! isset($aggregatedSkills[$skillName])) {
                        $aggregatedSkills[$skillName] = [
                            'name' => $skillName,
                            'total' => 0,
                            'correct' => 0,
                        ];
                    }
                    $aggregatedSkills[$skillName]['total'] += $sData['total'];
                    $aggregatedSkills[$skillName]['correct'] += $sData['correct'];
                }

                $skillsList = [];
                foreach ($aggregatedSkills as $skill) {
                    $sPct = $skill['total'] > 0 ? (int) round(($skill['correct'] / $skill['total']) * 100) : 0;
                    $skillsList[] = [
                        'name'           => $skill['name'],
                        'total'          => $skill['total'],
                        'correct'        => $skill['correct'],
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
                    'skills'          => $skillsList,
                ];
            }
        }

        return $rows;
    }

    public function difficultySummaries(array $stats): array
    {
        return $this->difficultySummariesFor($stats['difficulty']);
    }

    public function difficultySummariesBySection(array $stats): array
    {
        return [
            'all'  => $this->difficultySummariesFor($stats['difficulty']),
            'rw'   => $this->difficultySummariesFor($stats['sections']['reading_and_writing']['difficulty'] ?? []),
            'math' => $this->difficultySummariesFor($stats['sections']['math']['difficulty'] ?? []),
        ];
    }

    public function difficultySummariesFor(array $difficultyData): array
    {
        $order = ['easy' => 0, 'medium' => 1, 'hard' => 2, 'unknown' => 3];
        $rows  = [];

        foreach ($difficultyData as $difficulty => $data) {
            $percentCorrect = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;
            $rows[] = [
                'difficulty'     => $difficulty,
                'label'          => ucfirst($difficulty),
                'correct'        => $data['correct'],
                'total'          => $data['total'],
                'percentCorrect' => $percentCorrect,
            ];
        }

        usort($rows, fn ($a, $b) => ($order[$a['difficulty']] ?? 99) <=> ($order[$b['difficulty']] ?? 99));

        return $rows;
    }
}
