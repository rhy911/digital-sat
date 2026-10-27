<?php

namespace App\Data;

use Illuminate\Support\Collection;

/**
 * Static question data for the engine preview mode.
 * Keeps SessionController thin by externalizing ~200 lines of hardcoded data.
 */
class PreviewQuestions
{
    /** @return Collection<int, \stdClass> */
    public static function readingWriting(): Collection
    {
        /** @var array<int, array<string, mixed>> $data */
        $data = require __DIR__ . '/preview_rw_questions.php';

        return collect($data)->map(fn ($item) => (object) $item);
    }

    /** @return Collection<int, \stdClass> */
    public static function math(): Collection
    {
        /** @var array<int, array<string, mixed>> $data */
        $data = require __DIR__ . '/preview_math_questions.php';

        return collect($data)
            ->map(fn ($item) => (object) $item);
    }
}
