<?php

namespace App\Support;

/**
 * Normalizes the LaTeX inside `$$...$$` spans of question content.
 *
 * Question content used to be rendered by passing the whole string through
 * CommonMark before KaTeX. CommonMark treats `\` + ASCII punctuation as an
 * escape and eats the backslash, so authors were told to double it: `\\%`
 * reached KaTeX as `\%`. Commands followed by a letter (`\frac`, `\pi`) never
 * needed doubling, which left two conflicting conventions in the database.
 *
 * QuestionContentRenderer now hides math spans from CommonMark entirely, so
 * plain LaTeX is the rule going forward. This class keeps the legacy spelling
 * working by collapsing it back to plain LaTeX at render time — both
 * conventions produce identical output, and no data migration is required.
 *
 * The same normalization is reusable offline (see the content:normalize-latex
 * command) to rewrite stored content to a single convention.
 */
class LatexNormalizer
{
    private const MATH_SPAN = '/\$\$(.*?)\$\$/s';

    /**
     * ASCII punctuation CommonMark would have swallowed, minus `[`.
     *
     * `\\[` is excluded because `\\[6pt]` is a genuine LaTeX row break with
     * spacing, so collapsing it to `\[` would turn it into a display-math
     * delimiter.
     */
    private const LEGACY_ESCAPED_PUNCTUATION = '!"#$%&\'()*+,-./:;<=>?@]^_`{|}~';

    /**
     * Render-time normalization of a single math span (delimiters stripped).
     */
    public static function normalizeMath(string $math): string
    {
        return self::ensureDisplayStyle(self::collapseLegacyEscapes($math));
    }

    /**
     * Rewrite stored content from the legacy double-backslash convention to
     * plain LaTeX, leaving prose outside `$$...$$` untouched.
     *
     * Deliberately does NOT add `\displaystyle`: the renderer supplies that on
     * the fly, so writing it back would churn rows without changing output.
     * Running this twice is a no-op.
     */
    public static function rewriteStoredContent(?string $content): string
    {
        if ($content === null || $content === '') {
            return (string) $content;
        }

        return preg_replace_callback(
            self::MATH_SPAN,
            fn (array $matches): string => '$$'.self::collapseLegacyEscapes($matches[1]).'$$',
            $content
        ) ?? $content;
    }

    /**
     * Rewrite the historical double-backslash spelling to plain LaTeX.
     *
     *   `\\%` `\\$` `\\{`  -> `\%` `\$` `\{`   (legacy escaped punctuation)
     *   `\\\` and longer   -> `\\`             (legacy row break)
     *   `\\` + letter      -> `\\`             (already a real row break)
     *   `\\[`              -> unchanged        (row break with spacing)
     */
    private static function collapseLegacyEscapes(string $math): string
    {
        return preg_replace_callback('/(\\\\{2,})(.?)/s', function (array $matches): string {
            $run = $matches[1];
            $next = $matches[2] ?? '';

            if ($next === '[') {
                return $run.$next;
            }

            if (strlen($run) >= 3) {
                return '\\\\'.$next;
            }

            if ($next !== '' && str_contains(self::LEGACY_ESCAPED_PUNCTUATION, $next)) {
                return '\\'.$next;
            }

            return $run.$next;
        }, $math) ?? $math;
    }

    /**
     * Fractions render cramped without `\displaystyle`, so the guide used to
     * require authors to type it by hand. Add it for them instead.
     */
    private static function ensureDisplayStyle(string $math): string
    {
        if (! str_contains($math, '\frac') || str_contains($math, '\displaystyle')) {
            return $math;
        }

        return '\displaystyle '.ltrim($math);
    }
}
