<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TestContentCopyService
{
    public function deriveFromSection(Section $source, string $title, int $userId): Test
    {
        return DB::transaction(function () use ($source, $title, $userId) {
            $test = $this->newDraft($title, 'section_only', $userId);
            $this->copySection($source, $test, $userId);
            $test->refreshTotalDuration();

            return $test->load('sections.modules.questions');
        });
    }

    public function deriveFromModule(Module $source, Section $sourceSection, string $title, int $userId): Test
    {
        return DB::transaction(function () use ($source, $sourceSection, $title, $userId) {
            $test = $this->newDraft($title, 'module_only', $userId);
            $section = $this->createSection($sourceSection, $test, $userId);
            $this->copyModule($source, $section, $userId);
            $test->refreshTotalDuration();

            return $test->load('sections.modules.questions');
        });
    }

    public function copyTest(Test $source, ?int $userId): Test
    {
        return DB::transaction(function () use ($source, $userId) {
            $source->load('sections.modules.questions');
            $test = $this->newDraft($source->title.' (Clone)', $source->test_type, $userId);
            $test->description = $source->description;
            $test->break_duration_minutes = $source->break_duration_minutes;
            $test->save();
            foreach ($source->sections as $section) {
                $this->copySection($section, $test, $userId);
            }
            $test->refreshTotalDuration();

            return $test->load('sections.modules.questions');
        });
    }

    public function copySection(Section $source, Test $destination, ?int $userId): Section
    {
        if ($destination->sections()->where('type', $source->type)->exists()) {
            throw ValidationException::withMessages(['destination_test_id' => 'Destination already contains this section type.']);
        }

        return DB::transaction(function () use ($source, $destination, $userId) {
            $source->load('modules.questions');
            $section = $this->createSection($source, $destination, $userId);
            foreach ($source->modules as $module) {
                $this->copyModule($module, $section, $userId);
            }
            $destination->refreshTotalDuration();

            return $section->load('modules.questions');
        });
    }

    public function copyModule(Module $source, ?Section $destination = null, ?int $userId = null): Module
    {
        if ($destination && $destination->modules()->where('module_number', $source->module_number)
            ->where('difficulty_level', $source->difficulty_level)->exists()) {
            throw ValidationException::withMessages(['destination_test_id' => 'Destination section already contains this module number and difficulty.']);
        }

        return DB::transaction(function () use ($source, $destination, $userId) {
            $source->load('questions');
            $module = $source->replicate();
            $module->ulid = (string) Str::ulid();
            $module->key = ($source->key ?: 'MODULE').'_CLONE_'.strtoupper(Str::random(6));
            $module->section_id = $destination?->id;
            $module->created_by = $userId;
            $module->is_public = false;
            $module->save();
            if ($destination) {
                $module->sections()->syncWithoutDetaching([$destination->id]);
            }

            foreach ($source->questions as $question) {
                $module->questions()->attach($question->id, ['position' => $question->pivot->position]);
            }

            return $module;
        });
    }

    private function newDraft(string $title, string $type, ?int $userId): Test
    {
        return Test::create([
            'title' => $title,
            'test_type' => $type,
            'status' => 'draft',
            'created_by' => $userId,
            'is_public' => false,
            'break_duration_minutes' => 0,
        ]);
    }

    private function createSection(Section $source, Test $test, ?int $userId): Section
    {
        return Section::create([
            'test_id' => $test->id,
            'name' => $source->name,
            'type' => $source->type,
            'order' => $source->order,
            'created_by' => $userId,
            'is_public' => false,
        ]);
    }
}
