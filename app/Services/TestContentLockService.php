<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Validation\ValidationException;

class TestContentLockService
{
    public function ensureUnlocked(Test $test): void
    {
        if ($test->content_locked_at) {
            throw ValidationException::withMessages(['test' => 'This test is locked because an assigned attempt has started. Clone it to make a revised version.']);
        }
    }

    public function ensureQuestionUnlocked(Question $question): void
    {
        $locked = $question->modules()->whereHas('sections.test', fn ($query) => $query->whereNotNull('content_locked_at'))->exists();
        if ($locked) {
            throw ValidationException::withMessages(['question' => 'This question belongs to a locked assigned test. Clone it before editing.']);
        }
    }

    public function ensureModuleUnlocked(Module $module): void
    {
        if ($module->sections()->whereHas('test', fn ($query) => $query->whereNotNull('content_locked_at'))->exists()) {
            throw ValidationException::withMessages(['test' => 'This test is locked because an assigned attempt has started. Clone it to make a revised version.']);
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
