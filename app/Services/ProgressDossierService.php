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

        $events = $this->questionEvents($attempts);
        $skills = $this->skillMetrics($events, $attempts->count());
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

        return [
            'activityAttemptCount' => $activityAttempts->count(),
            'scoredFullTestCount' => $attempts->count(),
            'history' => $history,
            'score' => $score,
            'admissions' => $admissions,
            'pointLoss' => $pointLoss,
            'domains' => $domains,
            'skills' => $skills,
            'prioritySkills' => $prioritySkills,
            'difficulty' => $this->difficultyMetrics($events),
            'pacing' => $this->pacingMetrics($events),
            'stamina' => $this->staminaMetrics($events),
            'errors' => $this->errorMetrics($events),
            'quadrant' => $this->quadrantMetrics($skills),
            'prescriptions' => $this->prescriptions($prioritySkills),
            'narrative' => $this->narrative($score, $domains, $prioritySkills),
        ];
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

        $review = $answer->relationLoaded('review') ? $answer->review : $answer->review()->first();

        return $review?->teacher_error_type
            ?? $review?->student_error_type
            ?? ($omitted ? 'omitted' : 'unclassified');
    }

    private function hasReviewStorage(): bool
    {
        return $this->hasReviewStorage ??= Schema::hasTable('user_test_answer_reviews');
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

    /** @param list<array<string, mixed>> $events */
    private function pacingMetrics(array $events): array
    {
        $bands = [
            'Very fast (<0.5x)' => ['min' => 0, 'max' => 0.5],
            'On pace (0.5-1.0x)' => ['min' => 0.5, 'max' => 1.0],
            'Slow (1.0-1.5x)' => ['min' => 1.0, 'max' => 1.5],
            'Time trap (>1.5x)' => ['min' => 1.5, 'max' => INF],
        ];
        $matrix = [];
        foreach ($bands as $label => $range) {
            $items = collect($events)->filter(function (array $event) use ($range) {
                $ratio = $event['expected'] > 0 ? $event['time'] / $event['expected'] : 0;
                return $ratio >= $range['min'] && $ratio < $range['max'];
            });
            $matrix[] = [
                'label' => $label,
                'correct' => $items->where('correct', true)->count(),
                'incorrect' => $items->where('correct', false)->count(),
                'total' => $items->count(),
                'accuracy' => $this->percent($items->where('correct', true)->count(), $items->count()),
            ];
        }

        $skillTime = collect($events)->groupBy('skillLabel')->map(function (Collection $items) {
            return [
                'skill' => $items->first()['skillLabel'],
                'accuracy' => $this->percent($items->where('correct', true)->count(), $items->count()),
                'avgTime' => (int) round($items->avg('time')),
                'total' => $items->count(),
            ];
        })->sortBy('accuracy')->values()->take(10)->all();

        return ['matrix' => $matrix, 'skillTime' => $skillTime];
    }

    /** @param list<array<string, mixed>> $events */
    private function staminaMetrics(array $events): array
    {
        $bins = [
            'Q1-5' => [1, 5], 'Q6-10' => [6, 10], 'Q11-15' => [11, 15],
            'Q16-20' => [16, 20], 'Q21+' => [21, PHP_INT_MAX],
        ];
        $rows = [];
        foreach ($bins as $label => [$min, $max]) {
            $items = collect($events)->filter(fn (array $event) => $event['position'] !== null && $event['position'] >= $min && $event['position'] <= $max);
            if ($items->isEmpty()) continue;
            $rows[] = [
                'label' => $label,
                'total' => $items->count(),
                'accuracy' => $this->percent($items->where('correct', true)->count(), $items->count()),
                'avgTime' => (int) round($items->avg('time')),
            ];
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $events */
    private function errorMetrics(array $events): array
    {
        $labels = [
            'conceptual_gap' => 'Conceptual gap', 'misread_question' => 'Misread question',
            'misread_text_or_data' => 'Misread text/data', 'wrong_strategy' => 'Wrong strategy',
            'calculation' => 'Calculation', 'grammar_rule' => 'Grammar rule', 'elimination' => 'Elimination',
            'careless' => 'Careless', 'time_pressure' => 'Time pressure', 'guess' => 'Guess',
            'omitted' => 'Omitted', 'unclassified' => 'Unclassified',
        ];
        $missed = collect($events)->where('correct', false);

        return $missed->groupBy('errorType')->map(function (Collection $items, string $key) use ($labels) {
            return ['key' => $key, 'label' => $labels[$key] ?? 'Unclassified', 'count' => $items->count()];
        })->sortByDesc('count')->values()->all();
    }

    private function quadrantMetrics(array $skills): array
    {
        return collect($skills)->map(fn (array $skill) => [
            'skill' => $skill['skill'], 'accuracy' => $skill['accuracy'], 'volume' => $skill['total'],
            'evidenceLevel' => $skill['evidenceLevel'], 'masteryBand' => $skill['masteryBand'],
        ])->values()->all();
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

    private function ordinalSuffix(int $number): string
    {
        $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number . 'th';
        }

        return $number . $ends[$number % 10];
    }
}
