<?php

namespace Tests\Unit;

use App\Support\QuestionContentRenderer;
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
}
