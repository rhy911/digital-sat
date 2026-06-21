<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Validation\ValidationException;

class TestContentLockService
{
    public function isLocked(Test $test): bool
    {
        return $test->assignments()->where('status', 'published')->exists();
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
        if ($this->isLocked($test)) {
            throw ValidationException::withMessages(['test' => 'This test is locked because it belongs to an open assignment. Close or delete the assignment before editing it.']);
        }
    }

    public function ensureQuestionUnlocked(Question $question): void
    {
        $locked = $question->modules()->whereHas('sections.test.assignments', fn ($query) => $query->where('status', 'published'))->exists();
        if ($locked) {
            throw ValidationException::withMessages(['question' => 'This question belongs to a test in an open assignment. Close or delete the assignment before editing it.']);
        }
    }

    public function ensureModuleUnlocked(Module $module): void
    {
        if ($module->sections()->whereHas('test.assignments', fn ($query) => $query->where('status', 'published'))->exists()) {
            throw ValidationException::withMessages(['test' => 'This test is locked because it belongs to an open assignment. Close or delete the assignment before editing it.']);
        }
        foreach (array_unique(array_filter([$module->section_id, $module->getOriginal('section_id')])) as $sectionId) {
            $test = Section::find($sectionId)?->test;
            if ($test) $this->ensureUnlocked($test);
        }
    }

    public function ensureSectionUnlocked(Section $section): void
    {
        foreach (array_unique(array_filter([$section->test_id, $section->getOriginal('test_id')])) as $testId) {
            $this->ensureUnlocked(Test::findOrFail($testId));
        }
    }
}
