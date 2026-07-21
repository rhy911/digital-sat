<?php

namespace App\Data;

use Illuminate\Support\Collection;

/**
 * Static question data for the engine preview mode.
 * Keeps SessionController thin by externalizing ~200 lines of hardcoded data.
 */
class PreviewQuestions
{
    public static function readingWriting(): Collection
    {
        return collect(require __DIR__ . '/preview_rw_questions.php')
            ->map(fn ($item) => (object) $item);
    }

    public static function math(): Collection
    {
        return collect(require __DIR__ . '/preview_math_questions.php')
            ->map(fn ($item) => (object) $item);
    }
}
