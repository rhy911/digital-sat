<?php

namespace App\Console\Commands;

use App\Support\LatexNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Rewrites stored question content from the legacy "double every backslash"
 * spelling to plain LaTeX.
 *
 * This is a clean-up, not a requirement: QuestionContentRenderer accepts both
 * conventions, so nothing breaks if this never runs. Running it lets the
 * database settle on one convention, which is what makes it safe to eventually
 * drop the compatibility shim in LatexNormalizer.
 *
 * Writes go through the query builder rather than Eloquent on purpose: this
 * changes how content is spelled, not what it says, so TestContentLockService's
 * "published tests are immutable" hooks must not block it.
 */
class NormalizeStoredLatex extends Command
{
    protected $signature = 'content:normalize-latex
                            {--apply : Write the changes (default is a dry run)}
                            {--samples=5 : How many before/after examples to print}';

    protected $description = 'Rewrite legacy double-backslash LaTeX in question content to plain LaTeX';

    /**
     * @var array<string, list<string>>
     */
    private const TARGETS = [
        'questions' => ['stem', 'spr_hint'],
        'answer_choices' => ['content'],
        'question_explanations' => [
            'explanation',
            'rationale_a',
            'rationale_b',
            'rationale_c',
            'rationale_d',
            'strategy_tip',
            'common_mistakes',
        ],
        'passages' => ['content'],
    ];

    /**
     * @var list<array{table: string, id: int, updates: array<string, string>}>
     */
    private array $pending = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $sampleBudget = max(0, (int) $this->option('samples'));

        $changes = [];
        $rowsScanned = 0;
        $samples = [];

        foreach (self::TARGETS as $table => $columns) {
            DB::table($table)
                ->select(array_merge(['id'], $columns))
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($table, $columns, &$changes, &$rowsScanned, &$samples, $sampleBudget) {
                    foreach ($rows as $row) {
                        $rowsScanned++;
                        $updates = [];

                        foreach ($columns as $column) {
                            $original = $row->$column;
                            if (! is_string($original) || ! str_contains($original, '$$')) {
                                continue;
                            }

                            $rewritten = LatexNormalizer::rewriteStoredContent($original);
                            if ($rewritten === $original) {
                                continue;
                            }

                            $updates[$column] = $rewritten;
                            $changes["{$table}.{$column}"] = ($changes["{$table}.{$column}"] ?? 0) + 1;

                            if (count($samples) < $sampleBudget) {
                                $samples[] = [
                                    'location' => "{$table}#{$row->id}.{$column}",
                                    'before' => $original,
                                    'after' => $rewritten,
                                ];
                            }
                        }

                        if ($updates !== []) {
                            $this->pending[] = ['table' => $table, 'id' => $row->id, 'updates' => $updates];
                        }
                    }
                });
        }

        $this->reportPlan($rowsScanned, $changes, $samples);

        if ($this->pending === []) {
            $this->info('Nothing to rewrite — stored content is already plain LaTeX.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Dry run. Re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        $backupPath = $this->writeBackup();
        $this->line("Backup written to: {$backupPath}");

        DB::transaction(function () {
            foreach ($this->pending as $change) {
                DB::table($change['table'])
                    ->where('id', $change['id'])
                    ->update($change['updates']);
            }
        });

        $this->info(count($this->pending).' row(s) rewritten.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $changes
     * @param  list<array{location: string, before: string, after: string}>  $samples
     */
    private function reportPlan(int $rowsScanned, array $changes, array $samples): void
    {
        $this->info("Scanned {$rowsScanned} row(s).");

        if ($changes === []) {
            return;
        }

        $this->newLine();
        $this->table(
            ['Column', 'Rows to rewrite'],
            collect($changes)->map(fn (int $count, string $column) => [$column, $count])->values()->all()
        );

        foreach ($samples as $sample) {
            $this->newLine();
            $this->line("<comment>{$sample['location']}</comment>");
            $this->line('  before: '.$this->excerpt($sample['before']));
            $this->line('  after:  '.$this->excerpt($sample['after']));
        }
    }

    private function excerpt(string $value): string
    {
        $collapsed = preg_replace('/\s+/', ' ', $value) ?? $value;

        return mb_strimwidth($collapsed, 0, 160, '…');
    }

    private function writeBackup(): string
    {
        $rows = [];
        foreach ($this->pending as $change) {
            $original = DB::table($change['table'])->where('id', $change['id'])->first(array_keys($change['updates']));
            $rows[] = [
                'table' => $change['table'],
                'id' => $change['id'],
                'original' => (array) $original,
            ];
        }

        $path = 'latex-normalization/'.now()->format('Y-m-d_His').'.json';
        Storage::disk('local')->put($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Storage::disk('local')->path($path);
    }
}
