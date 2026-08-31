<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Validation\ValidationException;

class TestContentLockService
{
    public function isLocked(Test $test): bool
    {
        return $test->assignments()->where('status', 'published')->exists()
            || ($test->test_type === Test::TYPE_FULL
                && $test->scoreConversionSets()->where('status', \App\Models\ScoreConversionSet::STATUS_APPROVED)->exists());
    }

    public function syncLock(Test $test): void
    {
        $test = Test::lockForUpdate()->findOrFail($test->id);
        $locked = $this->isLocked($test);

        if ($locked === ($test->content_locked_at !== null)) {
            return;
        }

        $test->forceFill(['content_locked_at' => $locked ? now() : null])->save();
    }

    public function ensureUnlocked(Test $test): void
    {
        // Edit blocker removed completely - published/locked tests can be edited
    }

    public function ensureQuestionUnlocked(Question $question): void
    {
        // Edit blocker removed completely - published/locked tests can be edited
    }

    public function ensureModuleUnlocked(Module $module): void
    {
        // Edit blocker removed completely - published/locked tests can be edited
    }

    public function ensureSectionUnlocked(Section $section): void
    {
        // Edit blocker removed completely - published/locked tests can be edited
    }
}
