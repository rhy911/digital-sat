<?php

namespace Tests\Unit;

use App\Services\BulkQuestionImportService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BulkQuestionImportServiceTest extends TestCase
{
    private BulkQuestionImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BulkQuestionImportService();
    }

    public function test_parse_csv_multiple_choice_with_bom(): void
    {
        $csv = "\xEF\xBB\xBFquestion_type,difficulty,skill_domain,stem,choice_a_content,choice_b_content,choice_c_content,choice_d_content,correct_choice\n" .
            "multiple_choice,medium,Algebra,What is 2 + 2?,1,2,3,4,D\n";

        $items = $this->service->parseCsvToItems($csv);

        $this->assertCount(1, $items);
        $this->assertSame('multiple_choice', $items[0]['question_type']);
        $this->assertSame('medium', $items[0]['difficulty']);
        $this->assertSame('Algebra', $items[0]['skill_domain']);
        $this->assertSame('What is 2 + 2?', $items[0]['stem']);
        $this->assertCount(4, $items[0]['choices']);
        $this->assertSame('4', $items[0]['choices'][3]['content']);
        $this->assertTrue($items[0]['choices'][3]['is_correct']);
        $this->assertFalse($items[0]['choices'][0]['is_correct']);
    }

    public function test_parse_csv_student_produced_response(): void
    {
        $csv = "question_type,difficulty,skill_domain,stem,spr_correct_answers\n" .
            "student_produced_response,hard,Advanced Math,Solve for x.,0.5|1/2\n";

        $items = $this->service->parseCsvToItems($csv);

        $this->assertCount(1, $items);
        $this->assertSame('student_produced_response', $items[0]['question_type']);
        $this->assertSame(['0.5', '1/2'], $items[0]['spr_correct_answers']);
    }

    public function test_parse_csv_missing_required_fields_throws_validation_exception(): void
    {
        $csv = "question_type,difficulty,skill_domain,stem\n" .
            "multiple_choice,medium,,What is this?\n";

        $this->expectException(ValidationException::class);
        $this->service->parseCsvToItems($csv);
    }

    public function test_parse_csv_tolerant_mode_collects_errors(): void
    {
        $csv = "question_type,difficulty,skill_domain,stem\n" .
            "multiple_choice,medium,,What is this?\n";

        $items = $this->service->parseCsvToItems($csv, tolerant: true);

        $this->assertCount(1, $items);
        $this->assertNotEmpty($items[0]['errors']);
    }
}
