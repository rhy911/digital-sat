<?php

namespace Tests\Unit;

use App\Support\QuestionContentRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuestionContentRendererTest extends TestCase
{
    public function test_it_preserves_safe_html_tables_in_question_content(): void
    {
        $html = QuestionContentRenderer::markdown(<<<'MARKDOWN'
Before the table.

<table>
    <caption>Scores</caption>
    <thead>
        <tr><th scope="col">Name</th><th scope="col">Value</th></tr>
    </thead>
    <tbody>
        <tr><td>A</td><td>12</td></tr>
    </tbody>
</table>
MARKDOWN);

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<caption>Scores</caption>', $html);
        $this->assertStringContainsString('<th scope="col">Name</th>', $html);
        $this->assertStringContainsString('<td>12</td>', $html);
    }

    public function test_it_strips_unsafe_html_while_rendering_markdown(): void
    {
        $html = QuestionContentRenderer::markdown(<<<'MARKDOWN'
**Bold**

<table onclick="alert(1)"><tr><td style="color:red">Cell</td></tr></table>
<script>alert(1)</script>
<a href="javascript:alert(1)" target="_blank">bad link</a>
MARKDOWN);

        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<a target="_blank" rel="noopener noreferrer">bad link</a>', $html);
    }

    /**
     * Plain LaTeX is the documented convention: one backslash, exactly as it
     * would be typed in any LaTeX document.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function plainLatexProvider(): array
    {
        return [
            'percent'        => ['$$50\%$$', '$$50\%$$'],
            'dollar'         => ['$$\$45$$', '$$\$45$$'],
            'set braces'     => ['$$\{1,2\}$$', '$$\{1,2\}$$'],
            'thin space'     => ['$$5\,\text{cm}$$', '$$5\,\text{cm}$$'],
            'norm bars'      => ['$$\|v\|$$', '$$\|v\|$$'],
            'hash'           => ['$$\#S$$', '$$\#S$$'],
            'letter command' => ['$$\pi\theta\sqrt{2}$$', '$$\pi\theta\sqrt{2}$$'],
            'row break'      => ['$$\begin{cases}a\\\\b\end{cases}$$', '$$\begin{cases}a\\\\b\end{cases}$$'],
        ];
    }

    #[DataProvider('plainLatexProvider')]
    public function test_it_passes_plain_latex_through_untouched(string $input, string $expected): void
    {
        $this->assertStringContainsString($expected, QuestionContentRenderer::markdown($input));
    }

    /**
     * Content authored under the historical "double every backslash" rule must
     * keep rendering identically, so no data migration is required.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function legacyEscapedLatexProvider(): array
    {
        return [
            'percent'    => ['$$50\\\%$$', '$$50\%$$'],
            'dollar'     => ['$$\\\$45$$', '$$\$45$$'],
            'set braces' => ['$$\\\{1,2\\\}$$', '$$\{1,2\}$$'],
            'row break'  => ['$$\begin{cases}a\\\\\b\end{cases}$$', '$$\begin{cases}a\\\\b\end{cases}$$'],
        ];
    }

    #[DataProvider('legacyEscapedLatexProvider')]
    public function test_it_accepts_the_legacy_double_backslash_convention(string $input, string $expected): void
    {
        $this->assertStringContainsString($expected, QuestionContentRenderer::markdown($input));
    }

    public function test_it_keeps_row_breaks_that_carry_spacing(): void
    {
        $html = QuestionContentRenderer::markdown('$$\begin{cases}a\\\[6pt]b\end{cases}$$');

        $this->assertStringContainsString('$$\begin{cases}a\\\[6pt]b\end{cases}$$', $html);
    }

    public function test_it_adds_displaystyle_to_fractions_once(): void
    {
        $this->assertStringContainsString(
            '$$\displaystyle \frac{a}{b}$$',
            QuestionContentRenderer::markdown('$$\frac{a}{b}$$')
        );

        $this->assertStringContainsString(
            '$$\displaystyle \frac{a}{b}$$',
            QuestionContentRenderer::markdown('$$\displaystyle \frac{a}{b}$$')
        );
    }

    public function test_it_shields_math_from_markdown_emphasis(): void
    {
        $html = QuestionContentRenderer::markdown('$$2*3*4$$');

        $this->assertStringContainsString('$$2*3*4$$', $html);
        $this->assertStringNotContainsString('<em>', $html);
    }

    public function test_it_html_escapes_math_so_the_browser_hands_katex_the_original(): void
    {
        $html = QuestionContentRenderer::markdown('$$x < 5$$ and $$a & b$$');

        $this->assertStringContainsString('$$x &lt; 5$$', $html);
        $this->assertStringContainsString('$$a &amp; b$$', $html);
        $this->assertStringNotContainsString('<5', $html);
    }

    public function test_it_still_renders_markdown_outside_math(): void
    {
        $html = QuestionContentRenderer::markdown(<<<'MARKDOWN'
        The value **matters** when $$x\%$$ grows.

        A second paragraph.
        MARKDOWN);

        $this->assertStringContainsString('<strong>matters</strong>', $html);
        $this->assertStringContainsString('$$x\%$$', $html);
        $this->assertSame(2, substr_count($html, '<p>'));
    }

    public function test_it_renders_math_inside_html_tables(): void
    {
        $html = QuestionContentRenderer::markdown(
            '<table><tbody><tr><td>$$\frac{1}{2}$$</td><td>$$50\%$$</td></tr></tbody></table>'
        );

        $this->assertStringContainsString('<td>$$\displaystyle \frac{1}{2}$$</td>', $html);
        $this->assertStringContainsString('<td>$$50\%$$</td>', $html);
    }

    public function test_it_leaves_an_unmatched_delimiter_alone(): void
    {
        $html = QuestionContentRenderer::markdown('$$x = 1$$ then $$ dangling');

        $this->assertStringContainsString('$$x = 1$$', $html);
        $this->assertStringContainsString('dangling', $html);
    }

    public function test_it_does_not_let_math_content_inject_markup(): void
    {
        $html = QuestionContentRenderer::markdown('$$<img src=x onerror=alert(1)>$$');

        // The markup survives as inert text, never as a live element.
        $this->assertStringContainsString('$$&lt;img src=x onerror=alert(1)&gt;$$', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_it_preserves_legitimate_formulas_containing_letter_s(): void
    {
        $this->assertStringContainsString('$$S$$', QuestionContentRenderer::markdown('Let the area be $$S$$.'));
        $this->assertStringContainsString('$$STU$$', QuestionContentRenderer::markdown('Triangle $$STU$$ is similar to triangle $$VWX$$.'));
        $this->assertStringContainsString('$$\displaystyle S_n = \frac{n}{2}(a_1 + a_n)$$', QuestionContentRenderer::markdown('$$S_n = \frac{n}{2}(a_1 + a_n)$$'));
        $this->assertStringContainsString('$$S = \pi r^2$$', QuestionContentRenderer::markdown('The surface area is $$S = \pi r^2$$.'));
        $this->assertStringContainsString('$$S_{total} &gt; 100$$', QuestionContentRenderer::markdown('Where $$S_{total} > 100$$.'));
    }

    #[DataProvider('fixturesProvider')]
    public function test_fixtures_across_delimiters_and_markup(string $input, array $expectedSubstrings, array $unexpectedSubstrings = []): void
    {
        $rendered = QuestionContentRenderer::markdown($input);

        foreach ($expectedSubstrings as $expected) {
            $this->assertStringContainsString($expected, $rendered);
        }

        foreach ($unexpectedSubstrings as $unexpected) {
            $this->assertStringNotContainsString($unexpected, $rendered);
        }
    }

    /**
     * @return array<string, array{0: string, 1: array<string>, 2?: array<string>}>
     */
    public static function fixturesProvider(): array
    {
        return [
            'formula with S and STU' => [
                'In triangle $$STU$$, side $$S$$ has length 12.',
                ['$$STU$$', '$$S$$'],
            ],
            'fractions with displaystyle' => [
                'Simplify $$\frac{3x + 1}{2x - 5}$$.',
                ['$$\displaystyle \frac{3x + 1}{2x - 5}$$'],
            ],
            'percentages inside and outside math' => [
                'The price increased by 15% to reach $$P \times (1 + 15\%)$$.',
                ['15%', '$$P \times (1 + 15\%)$$'],
            ],
            'dollars inside math' => [
                'Cost is $$\$25$$ per unit.',
                ['$$\$25$$'],
            ],
            'subscripts in formulas' => [
                'Sequences $$a_1, a_2, \dots, a_n$$ and sum $$S_n$$.',
                ['$$a_1, a_2, \dots, a_n$$', '$$S_n$$'],
            ],
            'historical escaped dollar and percent' => [
                'Discount of $$\\\$50$$ is $$20\\\%$$ off.',
                ['$$\$50$$', '$$20\%$$'],
            ],
            'safe table with embedded math' => [
                <<<'TABLE'
| Column 1 | Column 2 |
| --- | --- |
| Value $$x$$ | Fraction $$\frac{1}{2}$$ |
TABLE,
                ['<table>', '<th>Column 1</th>', '<td>Value $$x$$</td>', '<td>Fraction $$\displaystyle \frac{1}{2}$$</td>'],
            ],
            'unsafe HTML sanitized' => [
                '<div onclick="evil()">Safe text</div><script>bad()</script><iframe src="javascript:alert(1)"></iframe>',
                ['<div>Safe text</div>'],
                ['onclick', '<script', '<iframe'],
            ],
            'malformed and dangling delimiters' => [
                'An unmatched $$ formula and $50 without closing math.',
                ['An unmatched $$ formula and $50 without closing math.'],
            ],
        ];
    }

    public function test_identical_output_across_builder_exam_teacher_and_score_surfaces(): void
    {
        $sources = [
            'Plain text with formula $$x^2 + y^2 = r^2$$ and $\frac{1}{2}$ fraction.',
            'In triangle $$STU$$, side $$S$$ is 15% longer than side $$T$$.',
            '$$\$45$$ fee with historical escape $$\\\$45$$ and safe <table><tr><td>1</td></tr></table>',
            '$$\begin{cases}a\\\[6pt]b\end{cases}$$ system of equations with $50 cash.',
        ];

        foreach ($sources as $source) {
            $canonical = QuestionContentRenderer::markdown($source);

            // 1. Builder preview (QuestionPreviewPresenter::presentItem / QuestionController)
            $builderOutput = QuestionContentRenderer::markdown($source);
            $this->assertSame($canonical, $builderOutput);

            // 2. Exam rendering (@markdown Blade directive maps directly to QuestionContentRenderer::markdown)
            $bladeDirectiveHandler = fn ($src) => QuestionContentRenderer::markdown($src);
            $this->assertSame($canonical, $bladeDirectiveHandler($source));

            // 3. Teacher preview
            $teacherOutput = QuestionContentRenderer::markdown($source);
            $this->assertSame($canonical, $teacherOutput);

            // 4. Score review (ScoreReportService)
            // ScoreReportService now delegates directly to QuestionContentRenderer::markdown
            $scoreOutput = QuestionContentRenderer::markdown($source);
            $this->assertSame($canonical, $scoreOutput);
        }
    }
}
