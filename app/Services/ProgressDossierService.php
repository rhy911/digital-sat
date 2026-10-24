<?php

namespace App\Services;

use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Support\QuestionPacing;
use App\Support\SatTaxonomy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable, longitudinal data contract for the student performance dossier.
 * All question attributes prefer the answer snapshot so later content edits do
 * not rewrite a student's historical performance.
 */
class ProgressDossierService
{
    private ?bool $hasReviewStorage = null;

    public function build(Collection $completedAttempts, ?int $targetScore = null): array
    {
        $activityAttempts = $completedAttempts
            ->sortBy(fn (UserTest $attempt) => $attempt->completed_at?->getTimestamp() ?? 0)
            ->values();

        $attempts = $activityAttempts
            ->filter(fn (UserTest $attempt) => $this->isComparable($attempt))
            ->values();

        $history = $attempts->map(function (UserTest $attempt, int $index) use ($attempts) {
            $total = (int) $attempt->total_score;

            return [
                'index' => $index + 1,
                'id' => $attempt->id,
                'date' => $attempt->completed_at?->format('M j, Y') ?? 'Undated',
                'shortDate' => $attempt->completed_at?->format('M j') ?? '#'.($index + 1),
                'title' => $attempt->test?->title ?? 'Practice Test',
                'total' => $total,
                'rw' => (int) $attempt->score_reading_writing,
                'math' => (int) $attempt->score_math,
                'rollingAverage' => (int) round($attempts->slice(max(0, $index - 2), 3)->avg('total_score')),
            ];
        })->all();

        $events = $this->questionEvents($activityAttempts);
        $skills = $this->skillMetrics($events, $activityAttempts->count());
        $domains = $this->domainMetrics($events);
        $score = $this->scoreSummary($history, $targetScore);
        $prioritySkills = collect($skills)
            ->filter(fn (array $skill) => $skill['evidenceLevel'] !== 'Insufficient')
            ->sortByDesc('opportunityIndex')
            ->take(5)
            ->values()
            ->all();

        $admissions = $this->admissionsMetrics($score);
        $pointLoss = $this->pointLossMetrics($events, $score, $admissions);
        $weakAreaSummaries = $this->weakAreaSummaries($events);
        $difficultyPerformanceSummaries = $this->difficultyPerformanceSummaries($events);
        $pacingSummaries = $this->pacingSummaries($events);
        $habits = $this->habitsMetrics($events);

        return [
            'activityAttemptCount' => $activityAttempts->count(),
            'scoredFullTestCount' => $attempts->count(),
            'history' => $history,
            'score' => $score,
            'admissions' => $admissions,
            'pointLoss' => $pointLoss,
            'domains' => $domains,
            'skills' => $skills,
            'difficulty' => $this->difficultyMetrics($events),
            'prescriptions' => $this->prescriptions($prioritySkills),
            'narrative' => $this->narrative($score, $domains, $prioritySkills),
            'weakAreaSummaries' => $weakAreaSummaries,
            'difficultyPerformanceSummaries' => $difficultyPerformanceSummaries,
            'pacingSummaries' => $pacingSummaries,
            'recommendations' => $this->recommendations($weakAreaSummaries),
            'skippedCount' => $habits['skippedCount'],
            'stuckCount' => $habits['stuckCount'],
            'rushedCount' => $habits['rushedCount'],
            'timeMatrix' => $this->timeMatrixMetrics($events),
            'sectionAccuracy' => $this->sectionAccuracyMetrics($events),
            'domainDifficultyStats' => $this->domainDifficultyMetrics($events),
            'timeBuckets' => $this->timeBucketsMetrics($events),
        ];
    }

    /**
     * Compute a single targeted recommendation tip without full dossier psychometrics.
     */
    public function studyTip(Collection $completedAttempts): string
    {
        $fallback = 'Take your first practice test to unlock a personalized study tip here.';
        if ($completedAttempts->isEmpty()) {
            return $fallback;
        }

        $events = $this->questionEvents($completedAttempts);
        if (empty($events)) {
            return $fallback;
        }

        $weakAreas = $this->weakAreaSummaries($events);
        $recommendations = $this->recommendations($weakAreas);

        return $recommendations[0] ?? $fallback;
    }

    /** @return array{skill: string, domain: string, correct: int, total: int}|null */
    public function studySuggestion(Collection $completedAttempts): ?array
    {
        if ($completedAttempts->isEmpty()) {
            return null;
        }

        $areas = $this->weakAreaSummaries($this->questionEvents($completedAttempts));
        foreach ($areas as $area) {
            foreach ($area['skills'] as $skill) {
                if ($skill['correct'] < $skill['total']) {
                    return [
                        'skill' => $skill['name'],
                        'domain' => $area['domain'],
                        'correct' => $skill['correct'],
                        'total' => $skill['total'],
                    ];
                }
            }
        }

        return null;
    }

    private function isComparable(UserTest $attempt): bool
    {
        return ! in_array($attempt->attempt_type, ['section'], true)
            && $attempt->total_score !== null
            && $attempt->score_reading_writing !== null
            && $attempt->score_math !== null;
    }

    /** @return list<array<string, mixed>> */
    private function questionEvents(Collection $attempts): array
    {
        $fallbackPositions = $this->fallbackPositions($attempts);
        $events = [];

        foreach ($attempts as $attemptIndex => $attempt) {
            foreach ($attempt->userAnswers as $answer) {
                $snapshot = $this->snapshot($answer);
                if ((bool) ($snapshot['is_pretest'] ?? $answer->question?->is_pretest)) {
                    continue;
                }

                $section = ($snapshot['section_type'] ?? $answer->question?->section_type) === 'math' ? 'math' : 'reading_and_writing';
                $domain = (string) ($snapshot['skill_domain'] ?? $answer->question?->skill_domain ?? 'other');
                $skill = (string) ($snapshot['skill_subdomain'] ?? $answer->question?->skill_subdomain ?? 'other');
                $difficulty = strtolower((string) ($snapshot['difficulty'] ?? $answer->question?->difficulty ?? 'unknown'));
                $expected = (int) ($snapshot['expected_time'] ?? $answer->question?->expected_time ?? 0);
                $expected = $expected > 0 ? $expected : QuestionPacing::expectedSeconds($section, $difficulty);
                $position = $snapshot['module_position'] ?? $fallbackPositions->get("{$answer->module_id}:{$answer->question_id}");
                $omitted = $answer->selected_answer === null || $answer->selected_answer === '';

                $events[] = [
                    'attemptIndex' => $attemptIndex,
                    'attemptId' => $attempt->id,
                    'section' => $section,
                    'domain' => $domain,
                    'domainLabel' => SatTaxonomy::domainLabel($domain),
                    'skill' => $skill,
                    'skillLabel' => SatTaxonomy::subdomainLabel($skill),
                    'difficulty' => in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'unknown',
                    'correct' => (bool) $answer->is_correct,
                    'omitted' => $omitted,
                    'time' => max(0, (int) $answer->time_spent),
                    'expected' => $expected,
                    'position' => is_numeric($position) ? (int) $position : null,
                    'errorType' => $this->effectiveErrorType($answer, $omitted),
                ];
            }
        }

        return $events;
    }

    private function snapshot(UserTestAnswer $answer): array
    {
        $snapshot = $answer->question_snapshot;

        return is_array($snapshot) ? $snapshot : [];
    }

    private function fallbackPositions(Collection $attempts): Collection
    {
        $pairs = $attempts->flatMap(fn (UserTest $attempt) => $attempt->userAnswers)
            ->filter(fn (UserTestAnswer $answer) => ! isset($this->snapshot($answer)['module_position']))
            ->map(fn (UserTestAnswer $answer) => ['module_id' => $answer->module_id, 'question_id' => $answer->question_id])
            ->filter(fn (array $pair) => $pair['module_id'] && $pair['question_id'])
            ->unique(fn (array $pair) => $pair['module_id'].':'.$pair['question_id'])
            ->values();

        if ($pairs->isEmpty()) {
            return collect();
        }

        $moduleIds = $pairs->pluck('module_id')->unique()->all();
        $questionIds = $pairs->pluck('question_id')->unique()->all();

        return DB::table('module_questions')
            ->whereIn('module_id', $moduleIds)
            ->whereIn('question_id', $questionIds)
            ->get(['module_id', 'question_id', 'position'])
            ->mapWithKeys(fn ($row) => ["{$row->module_id}:{$row->question_id}" => (int) $row->position]);
    }

    private function effectiveErrorType(UserTestAnswer $answer, bool $omitted): string
    {
        if (! $this->hasReviewStorage()) {
            return $omitted ? 'omitted' : 'unclassified';
        }

        $review = $answer->relationLoaded('review') ? $answer->review : null;

        return $review?->teacher_error_type
            ?? $review?->student_error_type
            ?? ($omitted ? 'omitted' : 'unclassified');
    }

    private function hasReviewStorage(): bool
    {
        return $this->hasReviewStorage ??= (bool) rescue(fn () => Schema::hasTable('user_test_answer_reviews'), false, false);
    }

    /** @param list<array<string, mixed>> $history */
    private function scoreSummary(array $history, ?int $targetScore): array
    {
        if ($history === []) {
            return [
                'latest' => null, 'best' => null, 'average' => null, 'recentAverage' => null,
                'median' => null, 'range' => null, 'bestThreeAverage' => null, 'standardDeviation' => null,
                'growth' => null, 'rwGrowth' => null, 'mathGrowth' => null, 'velocity' => null,
                'plateau' => 'Insufficient data', 'target' => $targetScore, 'targetGap' => null,
            ];
        }

        $totals = array_column($history, 'total');
        sort($totals);
        $count = count($history);
        $latest = $history[$count - 1];
        $mean = array_sum($totals) / $count;
        $variance = $count > 1
            ? array_sum(array_map(fn (int $value) => ($value - $mean) ** 2, $totals)) / ($count - 1)
            : null;
        $middle = intdiv($count, 2);
        $median = $count % 2 ? $totals[$middle] : (int) round(($totals[$middle - 1] + $totals[$middle]) / 2);
        $recent = array_slice($history, -min(3, $count));
        $recentDelta = count($recent) >= 3 ? $recent[array_key_last($recent)]['total'] - $recent[0]['total'] : null;
        $latestStepDelta = count($recent) >= 2 ? $recent[array_key_last($recent)]['total'] - $recent[count($recent) - 2]['total'] : null;

        return [
            'latest' => $latest,
            'best' => max($totals),
            'average' => (int) round($mean),
            'recentAverage' => (int) round(array_sum(array_column($recent, 'total')) / count($recent)),
            'median' => $median,
            'range' => max($totals) - min($totals),
            'bestThreeAverage' => (int) round(array_sum(array_slice(array_reverse($totals), 0, min(3, $count))) / min(3, $count)),
            'standardDeviation' => $variance === null ? null : (int) round(sqrt($variance)),
            'growth' => $latest['total'] - $history[0]['total'],
            'rwGrowth' => $latest['rw'] - $history[0]['rw'],
            'mathGrowth' => $latest['math'] - $history[0]['math'],
            'velocity' => $count >= 3 ? round($this->olsSlope(array_column($history, 'total')), 1) : null,
            'plateau' => $this->plateauState($recentDelta, $latestStepDelta),
            'target' => $targetScore,
            'targetGap' => $targetScore ? max(0, $targetScore - $latest['total']) : null,
        ];
    }

    private function plateauState(?int $delta, ?int $latestStepDelta = null): string
    {
        if ($delta === null) return 'Insufficient data';
        if ($latestStepDelta !== null && $latestStepDelta <= -20 && $delta > 20) {
            return 'Growth (Recent Dip)';
        }
        if ($delta > 50) return 'Rapid growth';
        if ($delta >= 20) return 'Improving';
        if ($delta >= -20) return 'Stable';
        return 'Declining';
    }

    /** @param list<array<string, mixed>> $events */
    private function domainMetrics(array $events): array
    {
        $groups = collect($events)->groupBy(fn (array $event) => $event['section'].'|'.$event['domain']);

        return $groups->map(function (Collection $items) {
            $first = $items->first();
            $total = $items->count();
            $correct = $items->where('correct', true)->count();

            return [
                'section' => $first['section'],
                'sectionLabel' => $first['section'] === 'math' ? 'Math' : 'Reading & Writing',
                'domain' => $first['domainLabel'],
                'correct' => $correct,
                'total' => $total,
                'accuracy' => $this->percent($correct, $total),
            ];
        })->sortBy([['section', 'asc'], ['accuracy', 'asc']])->values()->all();
    }

    /** @param list<array<string, mixed>> $events */
    private function skillMetrics(array $events, int $attemptCount): array
    {
        $groups = collect($events)->groupBy(fn (array $event) => $event['section'].'|'.$event['domainLabel'].'|'.$event['skillLabel']);

        return $groups->map(function (Collection $items) use ($attemptCount) {
            $first = $items->first();
            $total = $items->count();
            $correct = $items->where('correct', true)->count();
            $weightedCorrect = 0.0;
            $weightTotal = 0.0;
            foreach ($items as $item) {
                $weight = $attemptCount > 1 ? 0.6 + 0.4 * ($item['attemptIndex'] / ($attemptCount - 1)) : 1.0;
                $weightTotal += $weight;
                if ($item['correct']) $weightedCorrect += $weight;
            }
            $weightedAccuracy = $weightTotal > 0 ? ($weightedCorrect / $weightTotal) * 100 : 0;
            $confidence = 0.5 + 0.5 * min(1, sqrt($total / 20));
            $mastery = (int) round($weightedAccuracy * $confidence);
            $perTest = $items->groupBy('attemptIndex')->map(fn (Collection $attempt) => $this->percent($attempt->where('correct', true)->count(), $attempt->count()))->values()->all();
            $slope = count($perTest) >= 3 && $total >= 8 ? $this->olsSlope($perTest) : null;
            $hardShare = $total > 0 ? $items->where('difficulty', 'hard')->count() / $total : 0;
            $trendMultiplier = max(0.75, min(1.25, 1 - (($slope ?? 0) / 20)));
            $opportunity = $total < 8 ? null : round((100 - $mastery) * min(1, $total / 20) * $trendMultiplier * (1 + 0.15 * $hardShare), 1);
            $evidenceLevel = $total < 8 ? 'Insufficient' : ($total < 20 ? 'Moderate' : 'Strong');

            return [
                'section' => $first['section'],
                'sectionLabel' => $first['section'] === 'math' ? 'Math' : 'Reading & Writing',
                'domain' => $first['domainLabel'],
                'skill' => $first['skillLabel'],
                'skillKey' => $first['skill'],
                'correct' => $correct,
                'total' => $total,
                'accuracy' => $this->percent($correct, $total),
                'recentAccuracy' => $this->recentAccuracy($items),
                'weightedAccuracy' => (int) round($weightedAccuracy),
                'confidence' => round($confidence, 2),
                'evidenceLevel' => $evidenceLevel,
                'masteryIndex' => $mastery,
                'masteryBand' => $this->masteryBand($mastery),
                'trendSlope' => $slope === null ? null : round($slope, 1),
                'trend' => $this->trendLabel($slope, $evidenceLevel),
                'sparkline' => $perTest,
                'avgTime' => (int) round($items->avg('time')),
                'hardShare' => round($hardShare * 100),
                'opportunityIndex' => $opportunity,
            ];
        })->sortBy('opportunityIndex')->reverse()->values()->all();
    }

    private function recentAccuracy(Collection $items): int
    {
        $lastIndexes = $items->pluck('attemptIndex')->unique()->sort()->slice(-3)->values();
        $recent = $items->whereIn('attemptIndex', $lastIndexes);

        return $this->percent($recent->where('correct', true)->count(), $recent->count());
    }

    private function masteryBand(int $index): string
    {
        if ($index >= 90) return 'Mastered';
        if ($index >= 80) return 'Strong';
        if ($index >= 70) return 'Developing';
        if ($index >= 60) return 'At risk';
        return 'Priority';
    }

    private function trendLabel(?float $slope, string $evidenceLevel = 'Insufficient'): string
    {
        if ($slope === null) {
            return $evidenceLevel === 'Insufficient' ? 'Insufficient data' : 'Baseline (need 3+ tests)';
        }
        if ($slope >= 5) return 'Rapid improvement';
        if ($slope >= 2) return 'Improving';
        if ($slope >= -2) return 'Stable';
        return 'Declining';
    }

    /** @param list<array<string, mixed>> $events */
    private function difficultyMetrics(array $events): array
    {
        $groups = collect($events)->groupBy(fn (array $event) => $event['section'].'|'.$event['domain']);

        return $groups->map(function (Collection $items) {
            $first = $items->first();
            $tiers = [];
            foreach (['easy', 'medium', 'hard'] as $tier) {
                $tierItems = $items->where('difficulty', $tier);
                $tiers[$tier] = [
                    'correct' => $tierItems->where('correct', true)->count(),
                    'total' => $tierItems->count(),
                    'accuracy' => $this->percent($tierItems->where('correct', true)->count(), $tierItems->count()),
                ];
            }

            return ['section' => $first['section'], 'domain' => $first['domainLabel'], 'tiers' => $tiers];
        })->values()->all();
    }

    private function prescriptions(array $skills): array
    {
        return array_map(function (array $skill) {
            $key = $skill['skillKey'];
            $templates = [
                'inferences' => ['Identify answer requiring fewest unsupported assumptions.', '20-30 inference questions, medium to hard.', '75% across 20 recent questions.'],
                'words_in_context' => ['Separate contextual function from dictionary meaning.', 'Predict meaning before viewing choices.', '80% across 20 recent questions.'],
                'command_of_evidence' => ['Connect each claim directly to supporting evidence.', 'Practice claim-to-evidence matching under timing.', '75% across 20 recent questions.'],
                'transitions' => ['Identify logical relationship before selecting a connector.', 'Label contrast, cause, addition, and example transitions.', '80% across 20 recent questions.'],
            ];
            $default = $skill['section'] === 'math'
                ? ['Repair method selection before increasing speed.', 'Complete targeted medium-to-hard sets with written checks.', '80% across 20 recent questions.']
                : ['Repair reasoning process before increasing speed.', 'Complete targeted medium-to-hard sets with an evidence note.', '80% across 20 recent questions.'];
            [$objective, $practice, $success] = $templates[$key] ?? $default;

            return array_merge($skill, compact('objective', 'practice', 'success'));
        }, $skills);
    }

    private function narrative(array $score, array $domains, array $prioritySkills): string
    {
        if (! $score['latest']) return 'No comparable full-test score is available yet. Complete a scored full test to begin the longitudinal dossier.';

        $latest = $score['latest'];
        $bestDomain = collect($domains)->sortByDesc('accuracy')->first();
        $priority = $prioritySkills[0]['skill'] ?? null;
        $text = "Current performance is {$latest['total']}, with Reading & Writing {$latest['rw']} and Math {$latest['math']}.";
        if ($score['growth'] !== null) $text .= " Score change from baseline is ".($score['growth'] >= 0 ? '+' : '').$score['growth']." points.";
        if ($bestDomain) $text .= " Strongest measured domain is {$bestDomain['domain']} at {$bestDomain['accuracy']}%.";
        if ($priority) $text .= " Highest evidence-qualified study priority is {$priority}.";

        return $text;
    }

    private function percent(int $numerator, int $denominator): int
    {
        return $denominator > 0 ? (int) round(($numerator / $denominator) * 100) : 0;
    }

    /** @param list<int|float> $values */
    private function olsSlope(array $values): float
    {
        $count = count($values);
        if ($count < 2) return 0.0;
        $meanX = ($count - 1) / 2;
        $meanY = array_sum($values) / $count;
        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($values as $index => $value) {
            $deltaX = $index - $meanX;
            $numerator += $deltaX * ($value - $meanY);
            $denominator += $deltaX ** 2;
        }

        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    /**
     * Compute College Board Benchmarks, National Percentiles, and University Selectivity Tiers.
     *
     * @param array{latest: array{total: int|null, rw: int|null, math: int|null}|null} $score
     * @return array<string, mixed>
     */
    public function admissionsMetrics(array $score): array
    {
        $latest = $score['latest'] ?? null;
        $total = $latest['total'] ?? null;
        $rw = $latest['rw'] ?? null;
        $math = $latest['math'] ?? null;

        if ($total === null) {
            return [
                'hasData' => false,
                'benchmarks' => [],
                'percentiles' => ['total' => 0, 'rw' => 0, 'math' => 0, 'standing' => 'N/A'],
                'selectivity' => ['currentTier' => null, 'nextTier' => null, 'gapToNextTier' => 0, 'tiers' => []],
            ];
        }

        // Official College Board College Readiness Benchmarks
        $rwBenchmark = 480;
        $mathBenchmark = 530;
        $totalBenchmark = 1010;

        $benchmarks = [
            'rw' => [
                'name' => 'Reading & Writing',
                'score' => $rw,
                'benchmark' => $rwBenchmark,
                'met' => $rw >= $rwBenchmark,
                'margin' => $rw - $rwBenchmark,
                'status' => $rw >= $rwBenchmark ? 'Exceeds Benchmark' : 'Approaching Benchmark',
            ],
            'math' => [
                'name' => 'Math',
                'score' => $math,
                'benchmark' => $mathBenchmark,
                'met' => $math >= $mathBenchmark,
                'margin' => $math - $mathBenchmark,
                'status' => $math >= $mathBenchmark ? 'Exceeds Benchmark' : 'Approaching Benchmark',
            ],
            'total' => [
                'name' => 'Total Composite',
                'score' => $total,
                'benchmark' => $totalBenchmark,
                'met' => $total >= $totalBenchmark,
                'margin' => $total - $totalBenchmark,
                'status' => $total >= $totalBenchmark ? 'College Ready' : 'Benchmark Deficit',
            ],
        ];

        // Official College Board SAT User Group Percentiles (with linear interpolation)
        $totalTable = [
            1600 => 99, 1550 => 99, 1500 => 98, 1450 => 96, 1400 => 93,
            1350 => 90, 1300 => 86, 1280 => 83, 1250 => 80, 1200 => 73,
            1150 => 65, 1100 => 56, 1050 => 48, 1000 => 40, 950 => 32,
            900 => 24, 850 => 17, 800 => 12, 750 => 7, 700 => 3, 600 => 1, 400 => 1,
        ];
        $rwTable = [
            800 => 99, 750 => 98, 700 => 94, 650 => 87, 600 => 76,
            550 => 60, 530 => 53, 500 => 43, 450 => 27, 400 => 14,
            350 => 5, 300 => 1, 200 => 1,
        ];
        $mathTable = [
            800 => 99, 750 => 95, 700 => 90, 650 => 81, 600 => 69,
            550 => 54, 500 => 40, 450 => 26, 400 => 13, 350 => 4,
            300 => 1, 200 => 1,
        ];

        $totalPercentile = $this->calculatePercentile($total, $totalTable);
        $rwPercentile = $this->calculatePercentile($rw, $rwTable);
        $mathPercentile = $this->calculatePercentile($math, $mathTable);

        $percentiles = [
            'total' => $totalPercentile,
            'totalFormatted' => $this->ordinalSuffix($totalPercentile),
            'rw' => $rwPercentile,
            'rwFormatted' => $this->ordinalSuffix($rwPercentile),
            'math' => $mathPercentile,
            'mathFormatted' => $this->ordinalSuffix($mathPercentile),
            'standing' => sprintf('Top %d%% Nationally (User Group)', max(1, 100 - $totalPercentile)),
        ];

        // University Selectivity Fit Tiers
        $tiers = [
            ['name' => 'Ivy / Top 20 Reach', 'min' => 1500, 'max' => 1600, 'tag' => 'Highly Selective'],
            ['name' => 'Top 50 National', 'min' => 1350, 'max' => 1490, 'tag' => 'Selective'],
            ['name' => 'Top 100 Flagship', 'min' => 1200, 'max' => 1340, 'tag' => 'Competitive'],
            ['name' => 'Regional / State', 'min' => 1000, 'max' => 1190, 'tag' => 'Foundational'],
        ];

        $currentTier = null;
        $nextTier = null;
        $gapToNextTier = 0;

        foreach ($tiers as $idx => $t) {
            if ($total >= $t['min']) {
                $currentTier = $t;
                if ($idx > 0) {
                    $nextTier = $tiers[$idx - 1];
                    $gapToNextTier = $nextTier['min'] - $total;
                }
                break;
            }
        }
        if ($currentTier === null) {
            $currentTier = end($tiers);
            $nextTier = $tiers[count($tiers) - 2];
            $gapToNextTier = $nextTier['min'] - $total;
        }

        return [
            'hasData' => true,
            'benchmarks' => $benchmarks,
            'percentiles' => $percentiles,
            'selectivity' => [
                'currentTier' => $currentTier,
                'nextTier' => $nextTier,
                'gapToNextTier' => max(0, $gapToNextTier),
                'tiers' => $tiers,
            ],
        ];
    }

    /**
     * Compute point loss deductions by error classification and immediate score recovery potential.
     *
     * @param list<array<string, mixed>> $events
     * @param array<string, mixed> $score
     * @param array<string, mixed>|null $admissions
     * @return array<string, mixed>
     */
    public function pointLossMetrics(array $events, array $score, ?array $admissions = null): array
    {
        $currentScore = $score['latest']['total'] ?? 1000;
        $targetScore = $score['target'] ?? null;
        $nextTierTarget = $admissions['selectivity']['nextTier']['min'] ?? null;
        $effectiveTarget = $targetScore ?? $nextTierTarget;

        $testIds = array_unique(array_column($events, 'attemptId'));
        $testCount = max(1, count($testIds));

        $carelessCount = 0;
        $timetrapCount = 0;
        $omittedCount = 0;
        $conceptCount = 0;

        foreach ($events as $e) {
            if ($e['correct']) {
                continue;
            }
            if ($e['omitted']) {
                $omittedCount++;
            } elseif ($e['time'] > (1.4 * $e['expected'])) {
                $timetrapCount++;
            } elseif ($e['difficulty'] !== 'hard' && $e['time'] <= $e['expected']) {
                $carelessCount++;
            } else {
                $conceptCount++;
            }
        }

        $carelessPerTest = round($carelessCount / $testCount, 1);
        $timetrapPerTest = round($timetrapCount / $testCount, 1);
        $omittedPerTest = round($omittedCount / $testCount, 1);

        $carelessPointsLost = (int) round($carelessPerTest * 10);
        $timetrapPointsLost = (int) round($timetrapPerTest * 8);
        $omittedPointsLost = (int) round($omittedPerTest * 10);

        $avoidableRecovery = min(1600 - $currentScore, $carelessPointsLost + $timetrapPointsLost + $omittedPointsLost);
        $avoidableRecovery = max(0, $avoidableRecovery);

        $immediateAttainable = min(1600, $currentScore + $avoidableRecovery);
        $conceptGap = $effectiveTarget !== null
            ? max(0, $effectiveTarget - $immediateAttainable)
            : null;

        return [
            'avoidablePoints' => $avoidableRecovery,
            'immediateAttainable' => $immediateAttainable,
            'conceptGap' => $conceptGap,
            'targetScore' => $targetScore,
            'targetType' => 'Target Goal',
            'targetDisplay' => $targetScore !== null ? (string) $targetScore : '-',
            'categories' => [
                [
                    'type' => 'Careless / Rushing Errors',
                    'desc' => 'Missed on Easy/Medium questions at or below normal pace',
                    'count' => $carelessCount,
                    'perTest' => $carelessPerTest,
                    'pointsLost' => $carelessPointsLost,
                ],
                [
                    'type' => 'Time Trap Over-Investment',
                    'desc' => 'Missed questions after investing >1.4× expected time',
                    'count' => $timetrapCount,
                    'perTest' => $timetrapPerTest,
                    'pointsLost' => $timetrapPointsLost,
                ],
                [
                    'type' => 'Unforced Omissions',
                    'desc' => 'Left blank without guessing (no penalty on SAT)',
                    'count' => $omittedCount,
                    'perTest' => $omittedPerTest,
                    'pointsLost' => $omittedPointsLost,
                ],
            ],
        ];
    }

    private function calculatePercentile(?int $score, array $table): int
    {
        if ($score === null) {
            return 50;
        }

        $keys = array_keys($table);
        $maxScore = $keys[0];
        if ($score >= $maxScore) {
            return $table[$maxScore];
        }

        $minScore = end($keys);
        if ($score <= $minScore) {
            return $table[$minScore];
        }

        for ($i = 0; $i < count($keys) - 1; $i++) {
            $upperScore = $keys[$i];
            $lowerScore = $keys[$i + 1];
            if ($score <= $upperScore && $score >= $lowerScore) {
                $upperPct = $table[$upperScore];
                $lowerPct = $table[$lowerScore];
                if ($upperScore === $lowerScore) {
                    return $upperPct;
                }
                $fraction = ($score - $lowerScore) / ($upperScore - $lowerScore);

                return (int) round($lowerPct + $fraction * ($upperPct - $lowerPct));
            }
        }

        return 1;
    }

    private function habitsMetrics(array $events): array
    {
        $skipped = 0;
        $stuck = 0;
        $rushed = 0;

        foreach ($events as $event) {
            if ($event['omitted']) {
                $skipped++;
            } else {
                $expected = $event['expected'];
                if (! $event['correct'] && $expected > 0 && $event['time'] > $expected * 1.5) {
                    $stuck++;
                }
                if ($event['correct'] && $expected > 0 && $event['time'] < $expected * 0.4) {
                    $rushed++;
                }
            }
        }

        return [
            'skippedCount' => $skipped,
            'stuckCount' => $stuck,
            'rushedCount' => $rushed,
        ];
    }

    private function timeMatrixMetrics(array $events): array
    {
        $matrix = [
            'math' => [
                'correct' => ['total' => 0, 'count' => 0],
                'incorrect' => ['total' => 0, 'count' => 0],
            ],
            'reading_and_writing' => [
                'correct' => ['total' => 0, 'count' => 0],
                'incorrect' => ['total' => 0, 'count' => 0],
            ],
        ];

        foreach ($events as $event) {
            $sec = $event['section'] === 'math' ? 'math' : 'reading_and_writing';
            $status = $event['correct'] ? 'correct' : 'incorrect';
            $matrix[$sec][$status]['total'] += $event['time'];
            $matrix[$sec][$status]['count']++;
        }

        return [
            'math' => [
                'correctAvg' => $matrix['math']['correct']['count'] > 0
                    ? (int) round($matrix['math']['correct']['total'] / $matrix['math']['correct']['count'])
                    : 0,
                'incorrectAvg' => $matrix['math']['incorrect']['count'] > 0
                    ? (int) round($matrix['math']['incorrect']['total'] / $matrix['math']['incorrect']['count'])
                    : 0,
            ],
            'reading_and_writing' => [
                'correctAvg' => $matrix['reading_and_writing']['correct']['count'] > 0
                    ? (int) round($matrix['reading_and_writing']['correct']['total'] / $matrix['reading_and_writing']['correct']['count'])
                    : 0,
                'incorrectAvg' => $matrix['reading_and_writing']['incorrect']['count'] > 0
                    ? (int) round($matrix['reading_and_writing']['incorrect']['total'] / $matrix['reading_and_writing']['incorrect']['count'])
                    : 0,
            ],
        ];
    }

    private function weakAreaSummaries(array $events): array
    {
        $domainStats = [];
        foreach ($events as $event) {
            $section = $event['section'];
            $domain = $event['domain'];
            $skill = $event['skill'];
            $domainKey = "{$section}:{$domain}";

            if (! isset($domainStats[$domainKey])) {
                $domainStats[$domainKey] = [
                    'section' => $section,
                    'domain' => $domain,
                    'domainLabel' => $event['domainLabel'],
                    'total' => 0,
                    'correct' => 0,
                    'skills' => [],
                ];
            }
            $domainStats[$domainKey]['total']++;
            if ($event['correct']) {
                $domainStats[$domainKey]['correct']++;
            }

            if (! isset($domainStats[$domainKey]['skills'][$skill])) {
                $domainStats[$domainKey]['skills'][$skill] = [
                    'name' => $event['skillLabel'],
                    'total' => 0,
                    'correct' => 0,
                ];
            }
            $domainStats[$domainKey]['skills'][$skill]['total']++;
            if ($event['correct']) {
                $domainStats[$domainKey]['skills'][$skill]['correct']++;
            }
        }

        $rows = [];
        foreach ($domainStats as $data) {
            $percentCorrect = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;

            $skillsList = [];
            foreach ($data['skills'] as $skillData) {
                $skillPercent = $skillData['total'] > 0 ? (int) round(($skillData['correct'] / $skillData['total']) * 100) : 0;
                $skillsList[] = [
                    'name' => $skillData['name'],
                    'total' => $skillData['total'],
                    'correct' => $skillData['correct'],
                    'percentCorrect' => $skillPercent,
                    'performance' => $skillPercent >= 80 ? 'High' : ($skillPercent >= 50 ? 'Medium' : 'Low'),
                ];
            }
            usort($skillsList, fn ($a, $b) => $a['percentCorrect'] <=> $b['percentCorrect']);

            $rows[] = [
                'section' => $data['section'] === 'math' ? 'Math' : 'Reading and Writing',
                'domain' => $data['domainLabel'],
                'total' => $data['total'],
                'correct' => $data['correct'],
                'percentCorrect' => $percentCorrect,
                'performance' => $percentCorrect >= 80 ? 'High' : ($percentCorrect >= 50 ? 'Medium' : 'Low'),
                'skills' => $skillsList,
            ];
        }

        usort($rows, fn ($a, $b) => $a['percentCorrect'] <=> $b['percentCorrect']);

        return $rows;
    }

    private function difficultyPerformanceSummaries(array $events): array
    {
        $order = ['easy' => 0, 'medium' => 1, 'hard' => 2, 'unknown' => 3];
        $stats = [];

        foreach ($events as $event) {
            $diff = $event['difficulty'];
            if (! isset($stats[$diff])) {
                $stats[$diff] = ['total' => 0, 'correct' => 0];
            }
            $stats[$diff]['total']++;
            if ($event['correct']) {
                $stats[$diff]['correct']++;
            }
        }

        $rows = [];
        foreach ($stats as $difficulty => $data) {
            $percent = $data['total'] > 0 ? (int) round(($data['correct'] / $data['total']) * 100) : 0;
            $rows[] = [
                'difficulty' => $difficulty,
                'label' => ucfirst($difficulty),
                'total' => $data['total'],
                'correct' => $data['correct'],
                'percentCorrect' => $percent,
                'performance' => $percent >= 80 ? 'High' : ($percent >= 50 ? 'Medium' : 'Low'),
            ];
        }

        usort($rows, fn ($a, $b) => ($order[$a['difficulty']] ?? 99) <=> ($order[$b['difficulty']] ?? 99));

        return $rows;
    }

    private function pacingSummaries(array $events): array
    {
        $order = ['easy' => 0, 'medium' => 1, 'hard' => 2, 'unknown' => 3];
        $stats = [];

        foreach ($events as $event) {
            if ($event['expected'] <= 0) {
                continue;
            }
            $diff = $event['difficulty'];
            if (! isset($stats[$diff])) {
                $stats[$diff] = ['count' => 0, 'timeSpent' => 0, 'expectedTime' => 0];
            }
            $stats[$diff]['count']++;
            $stats[$diff]['timeSpent'] += $event['time'];
            $stats[$diff]['expectedTime'] += $event['expected'];
        }

        $rows = [];
        foreach ($stats as $difficulty => $data) {
            $avgTimeSpent = $data['count'] > 0 ? (int) round($data['timeSpent'] / $data['count']) : 0;
            $avgExpectedTime = $data['count'] > 0 ? (int) round($data['expectedTime'] / $data['count']) : 0;
            $pacePercent = $avgExpectedTime > 0 ? (int) round(($avgTimeSpent / $avgExpectedTime) * 100) : 0;

            $rows[] = [
                'difficulty' => $difficulty,
                'label' => ucfirst($difficulty),
                'avgTimeSpent' => $avgTimeSpent,
                'avgExpectedTime' => $avgExpectedTime,
                'pacePercent' => $pacePercent,
                'pace' => $pacePercent > 120 ? 'Slow' : ($pacePercent < 80 ? 'Fast' : 'On pace'),
            ];
        }

        usort($rows, fn ($a, $b) => ($order[$a['difficulty']] ?? 99) <=> ($order[$b['difficulty']] ?? 99));

        return $rows;
    }

    private function recommendations(array $weakAreaSummaries): array
    {
        $flatSkills = [];
        foreach ($weakAreaSummaries as $domainRow) {
            foreach ($domainRow['skills'] as $skill) {
                $flatSkills[] = [
                    'name' => $skill['name'],
                    'pct' => $skill['percentCorrect'],
                    'section' => $domainRow['section'],
                ];
            }
        }
        usort($flatSkills, fn ($a, $b) => $a['pct'] <=> $b['pct']);

        $recommendations = [];
        foreach (array_slice($flatSkills, 0, 3) as $skill) {
            if ($skill['pct'] < 75) {
                if (str_contains(strtolower($skill['section']), 'math')) {
                    $recommendations[] = "Focus study on **{$skill['name']}** (accuracy: {$skill['pct']}%). Focus on formulas and practice with the Desmos calculator to lock in speed.";
                } else {
                    $recommendations[] = "Practice **{$skill['name']}** (accuracy: {$skill['pct']}%). Re-read passages carefully and mark structural transition keywords to spot correct choices.";
                }
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Great work! You have strong accuracy across all content domains. Keep taking practice tests to maintain consistency!';
        }

        return $recommendations;
    }

    private function sectionAccuracyMetrics(array $events): array
    {
        $rwCorrect = 0;
        $rwTotal = 0;
        $mathCorrect = 0;
        $mathTotal = 0;

        foreach ($events as $event) {
            if ($event['section'] === 'math') {
                $mathTotal++;
                if ($event['correct']) {
                    $mathCorrect++;
                }
            } else {
                $rwTotal++;
                if ($event['correct']) {
                    $rwCorrect++;
                }
            }
        }

        $rwPct = $rwTotal > 0 ? (int) round(($rwCorrect / $rwTotal) * 100) : 0;
        $mathPct = $mathTotal > 0 ? (int) round(($mathCorrect / $mathTotal) * 100) : 0;
        $overallTotal = $rwTotal + $mathTotal;
        $overallCorrect = $rwCorrect + $mathCorrect;
        $overallPct = $overallTotal > 0 ? (int) round(($overallCorrect / $overallTotal) * 100) : 0;

        return [
            'labels' => ['Reading & Writing', 'Math'],
            'data' => [$rwPct, $mathPct],
            'counts' => [
                'rw' => ['correct' => $rwCorrect, 'total' => $rwTotal, 'pct' => $rwPct],
                'math' => ['correct' => $mathCorrect, 'total' => $mathTotal, 'pct' => $mathPct],
                'overall' => ['correct' => $overallCorrect, 'total' => $overallTotal, 'pct' => $overallPct],
            ],
        ];
    }

    private function domainDifficultyMetrics(array $events): array
    {
        $stats = [];
        foreach ($events as $event) {
            $section = $event['section'] === 'math' ? 'Math' : 'Reading & Writing';
            $domainKey = SatTaxonomy::canonicalDomainKey($event['domain']);
            $diff = in_array($event['difficulty'], ['easy', 'medium', 'hard'], true) ? $event['difficulty'] : 'medium';

            if (! isset($stats[$section][$domainKey][$diff])) {
                $stats[$section][$domainKey][$diff] = ['correct' => 0, 'total' => 0];
            }
            $stats[$section][$domainKey][$diff]['total']++;
            if ($event['correct']) {
                $stats[$section][$domainKey][$diff]['correct']++;
            }
        }

        return $stats;
    }

    private function timeBucketsMetrics(array $events): array
    {
        $buckets = [
            '0-15s' => ['correct' => 0, 'incorrect' => 0],
            '15-30s' => ['correct' => 0, 'incorrect' => 0],
            '30-60s' => ['correct' => 0, 'incorrect' => 0],
            '60-90s' => ['correct' => 0, 'incorrect' => 0],
            '90-120s' => ['correct' => 0, 'incorrect' => 0],
            '120s+' => ['correct' => 0, 'incorrect' => 0],
        ];

        foreach ($events as $event) {
            $time = $event['time'];
            $status = $event['correct'] ? 'correct' : 'incorrect';

            if ($time < 15) {
                $buckets['0-15s'][$status]++;
            } elseif ($time < 30) {
                $buckets['15-30s'][$status]++;
            } elseif ($time < 60) {
                $buckets['30-60s'][$status]++;
            } elseif ($time < 90) {
                $buckets['60-90s'][$status]++;
            } elseif ($time < 120) {
                $buckets['90-120s'][$status]++;
            } else {
                $buckets['120s+'][$status]++;
            }
        }

        return $buckets;
    }

    private function ordinalSuffix(int $number): string
    {
        $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number . 'th';
        }

        return $number . $ends[$number % 10];
    }
}
