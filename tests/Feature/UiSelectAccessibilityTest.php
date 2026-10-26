<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiSelectAccessibilityTest extends TestCase
{
    public function test_select_associates_visible_control_with_its_label_and_listbox(): void
    {
        $html = Blade::render('<label for="assign_test_id">Test</label><x-ui.select name="assign_test_id" :options="[1 => \'One\']" />');

        $this->assertStringContainsString('<label for="assign_test_id">Test</label>', $html);
        $this->assertStringContainsString('id="assign_test_id"', $html);
        $this->assertStringContainsString('id="assign_test_id-value"', $html);
        $this->assertStringContainsString('name="assign_test_id"', $html);
        $this->assertStringContainsString('aria-controls="assign_test_id-options"', $html);
        $this->assertStringContainsString('id="assign_test_id-options"', $html);
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('@invalid.prevent=', $html);
        $this->assertStringContainsString('class="mt-1 text-xs text-red-700" role="alert" x-show="validationError">Please select an option.</p>', $html);
        $this->assertStringContainsString(":class=\"validationError ? 'border-red-600 ring-1 ring-red-600'", $html);
        $this->assertStringContainsString('tabindex="-1" aria-hidden="true"', $html);
        $this->assertStringContainsString('this.close(true)', $html);
        $this->assertStringContainsString('@click.outside="close()"', $html);
    }

    public function test_non_searchable_select_renders_native_select(): void
    {
        $html = Blade::render('<x-ui.select name="section_type" :searchable="false" :options="[\'reading_writing\' => \'Reading & Writing\', \'math\' => \'Math\']" />');

        $this->assertStringContainsString('<select name="section_type"', $html);
        $this->assertStringContainsString('id="section_type"', $html);
        $this->assertStringContainsString('<option value="reading_writing"', $html);
        $this->assertStringContainsString('<option value="math"', $html);
        $this->assertStringNotContainsString('role="combobox"', $html);
    }
}
