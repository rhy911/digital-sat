<?php

namespace App\Support;

/**
 * Attaches sanitized, KaTeX-ready HTML to bulk-import preview items.
 *
 * The import wizard used to build preview markup in JavaScript without running
 * markdown at all, while the test engine renders through
 * QuestionContentRenderer. The two disagreed on every backslash escape, so a
 * teacher could "fix" a formula until the preview looked right and thereby
 * break it for students. Rendering server-side keeps one pipeline, and drops
 * raw teacher-supplied markup out of the admin DOM.
 */
class QuestionPreviewPresenter
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function decorate(array $items): array
    {
        foreach ($items as &$item) {
            if (! is_array($item)) {
                continue;
            }

            $item['stem_html'] = QuestionContentRenderer::markdown(self::text($item['stem'] ?? null));
            $item['explanation_html'] = QuestionContentRenderer::markdown(self::text($item['explanation'] ?? null));
            $item['passage_html'] = QuestionContentRenderer::markdown(self::passageContent($item['passage'] ?? null));

            if (isset($item['choices']) && is_array($item['choices'])) {
                foreach ($item['choices'] as &$choice) {
                    if (is_array($choice)) {
                        $choice['content_html'] = QuestionContentRenderer::markdown(self::text($choice['content'] ?? null));
                    }
                }
                unset($choice);
            }
        }
        unset($item);

        return $items;
    }

    /**
     * A passage arrives as a plain string from AI-generated JSON, or as an
     * array once normalizePassageStringsInItems() has run.
     */
    private static function passageContent(mixed $passage): string
    {
        if (is_string($passage)) {
            return $passage;
        }

        if (is_array($passage)) {
            return self::text($passage['content'] ?? null);
        }

        return '';
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
