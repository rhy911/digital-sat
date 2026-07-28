<?php

namespace App\Console\Commands;

use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair for attempts merged by TestProgressionService::autoMergeIfEligible()
 * before the answer copy carried `time_spent` (and the original timestamps) over.
 * Those merged attempts show "0s" per question for the section that finished last,
 * while the surviving assignment-linked section attempt still holds the real times.
 *
 * Only rows that are still zero/null are touched, and only when the source row is
 * an exact module_id + question_id match on a completed sibling section attempt.
 */
class BackfillMergedAnswerTimes extends Command
{
    protected $signature = 'sat:backfill-merged-answer-times {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Restore time_spent on merged full attempts from their surviving section attempts';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $mergedAttempts = UserTest::where('attempt_type', 'full')
            ->where('status', 'completed')
            ->get(['id', 'user_id', 'test_id']);

        $totalRows = 0;
        $totalAttempts = 0;

        foreach ($mergedAttempts as $attempt) {
            $sourceAnswers = UserTestAnswer::whereIn('user_test_id', UserTest::where('user_id', $attempt->user_id)
                ->where('test_id', $attempt->test_id)
                ->where('attempt_type', 'section')
                ->where('status', 'completed')
                ->where('id', '!=', $attempt->id)
                ->pluck('id'))
                ->where('time_spent', '>', 0)
                ->get(['module_id', 'question_id', 'time_spent', 'created_at', 'updated_at']);

            if ($sourceAnswers->isEmpty()) {
                continue;
            }

            $sourceByKey = $sourceAnswers->keyBy(fn ($answer) => $answer->module_id.'|'.$answer->question_id);

            $targets = UserTestAnswer::where('user_test_id', $attempt->id)
                ->where(fn ($query) => $query->where('time_spent', 0)->orWhereNull('time_spent'))
                ->get(['id', 'module_id', 'question_id']);

            $repaired = 0;

            foreach ($targets as $target) {
                $source = $sourceByKey->get($target->module_id.'|'.$target->question_id);
                if (! $source) {
                    continue;
                }

                if ($apply) {
                    DB::table('user_test_answers')->where('id', $target->id)->update([
                        'time_spent' => $source->time_spent,
                        'created_at' => $source->created_at,
                        'updated_at' => $source->updated_at,
                    ]);
                }

                $repaired++;
            }

            if ($repaired > 0) {
                $totalAttempts++;
                $totalRows += $repaired;
                $this->line(sprintf('attempt %d (user %d, test %d): %d answers', $attempt->id, $attempt->user_id, $attempt->test_id, $repaired));
            }
        }

        $this->info(sprintf(
            '%s %d answers across %d merged attempts.',
            $apply ? 'Repaired' : 'Would repair',
            $totalRows,
            $totalAttempts
        ));

        if (! $apply && $totalRows > 0) {
            $this->comment('Re-run with --apply to write the changes.');
        }

        return self::SUCCESS;
    }
}
