<?php

namespace App\Console\Commands;

use App\Models\ScoreConversionSet;
use App\Models\Test;
use App\Services\FormScoringAuditService;
use Illuminate\Console\Command;

class AuditSatForm extends Command
{
    protected $signature = 'sat:audit-form {test : Test ID or ULID} {--set= : Conversion set ID} {--json}';

    protected $description = 'Audit a full-length form and optional score conversion for reporting eligibility';

    public function handle(FormScoringAuditService $audit): int
    {
        $identifier = $this->argument('test');
        $test = Test::withTrashed()->where('id', $identifier)->orWhere('ulid', $identifier)->firstOrFail();
        $set = $this->option('set') ? ScoreConversionSet::findOrFail($this->option('set')) : null;
        $report = $audit->audit($test, $set);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info($report['eligible'] ? 'PASS: form is eligible for estimated score reporting.' : 'FAIL: form is not score eligible.');
            foreach ($report['errors'] as $error) {
                $this->error($error);
            }
            foreach ($report['warnings'] as $warning) {
                $this->warn($warning);
            }
            $this->line('Form checksum: '.$report['form_checksum']);
        }

        return $report['eligible'] ? self::SUCCESS : self::FAILURE;
    }
}
