<?php

namespace App\Console\Commands;

use App\Models\UserTest;
use App\Services\SatScoringPipeline;
use Illuminate\Console\Command;

class AuditScoreCandidates extends Command
{
    protected $signature = 'sat:scores-audit {--output= : Optional CSV path; defaults to stdout}';

    protected $description = 'Compare stored completed scores with EAP v2 and experimental MLE candidates without changing records';

    public function handle(SatScoringPipeline $pipeline): int
    {
        $path = $this->option('output');
        $stream = $path ? @fopen($path, 'wb') : STDOUT;
        if (! $stream) {
            $this->error('Could not open CSV output path.');

            return self::FAILURE;
        }

        fputcsv($stream, ['attempt_id', 'test_type', 'current_total', 'eap_curve_v2_total', 'mle_candidate_v2_total', 'mle_delta', 'rw_eap', 'rw_mle', 'math_eap', 'math_mle', 'status', 'reason']);
        UserTest::with('test')->where('status', 'completed')->orderBy('id')->eachById(function (UserTest $attempt) use ($pipeline, $stream): void {
            try {
                $result = $pipeline->candidateComparison($attempt);
            } catch (\Throwable $exception) {
                $result = ['status' => 'skipped', 'reason' => 'comparison_failed: '.$exception->getMessage()];
            }
            fputcsv($stream, [
                $attempt->id, $attempt->test?->test_type, $result['current_total'] ?? $attempt->total_score,
                $result['eap_curve_v2_total'] ?? null, $result['mle_candidate_v2_total'] ?? null,
                $result['mle_delta'] ?? null, $result['rw_eap'] ?? null, $result['rw_mle'] ?? null,
                $result['math_eap'] ?? null, $result['math_mle'] ?? null,
                $result['status'] ?? 'skipped', $result['reason'] ?? '',
            ]);
        });

        if ($path) {
            fclose($stream);
            $this->info("Read-only comparison written to {$path}");
        }

        return self::SUCCESS;
    }
}
