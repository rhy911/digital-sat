<?php

namespace Tests\Unit;

use App\Support\LatexNormalizer;
use PHPUnit\Framework\TestCase;

class LatexNormalizerTest extends TestCase
{
    public function test_it_rewrites_legacy_escapes_inside_math_only(): void
    {
        $stored = 'Costs $$\\\$40$$ after a $$25\\\%$$ cut. Prose keeps its \\\ backslashes.';

        $this->assertSame(
            'Costs $$\$40$$ after a $$25\%$$ cut. Prose keeps its \\\ backslashes.',
            LatexNormalizer::rewriteStoredContent($stored)
        );
    }

    public function test_rewriting_stored_content_is_idempotent(): void
    {
        $once = LatexNormalizer::rewriteStoredContent('$$\\\$40$$ and $$\begin{cases}a\\\\\b\end{cases}$$');

        $this->assertSame($once, LatexNormalizer::rewriteStoredContent($once));
        $this->assertSame('$$\$40$$ and $$\begin{cases}a\\\\b\end{cases}$$', $once);
    }

    public function test_it_does_not_write_displaystyle_back_into_storage(): void
    {
        $this->assertSame('$$\frac{a}{b}$$', LatexNormalizer::rewriteStoredContent('$$\frac{a}{b}$$'));
        $this->assertSame('\displaystyle \frac{a}{b}', LatexNormalizer::normalizeMath('\frac{a}{b}'));
    }

    public function test_it_leaves_plain_latex_and_empty_content_alone(): void
    {
        $this->assertSame('$$50\%$$', LatexNormalizer::rewriteStoredContent('$$50\%$$'));
        $this->assertSame('No math here.', LatexNormalizer::rewriteStoredContent('No math here.'));
        $this->assertSame('', LatexNormalizer::rewriteStoredContent(''));
        $this->assertSame('', LatexNormalizer::rewriteStoredContent(null));
    }
}
