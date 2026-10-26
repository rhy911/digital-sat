<?php

namespace Tests\Unit;

use App\Models\Module;
use App\Models\Section;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ModuleSectionFallbackTest extends TestCase
{
    public function test_loaded_pivot_sections_are_used_when_primary_section_is_null(): void
    {
        $section = new Section;
        $module = (new Module)
            ->setRelation('section', null)
            ->setRelation('sections', new Collection([$section]));

        $this->assertSame($section, $module->section);
    }
}
