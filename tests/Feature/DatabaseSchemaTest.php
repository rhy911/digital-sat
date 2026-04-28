<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    // ================================================================
    // LỚP 1: MIGRATION INTEGRITY
    // Đảm bảo tất cả bảng tồn tại và có đúng các cột cần thiết
    // ================================================================

    public function test_all_content_and_scoring_tables_exist(): void
    {
        $tables = [
            'tests', 'sections', 'modules', 'module_routing',
            'passages', 'paired_passages', 'questions', 'module_questions',
            'question_media', 'answer_choices', 'spr_correct_answers',
            'question_explanations', 'module_blueprints', 'score_conversions',
        ];

        foreach ($tables as $t) {
            $this->assertTrue(Schema::hasTable($t), "Table [{$t}] should exist");
        }
    }

    public function test_tests_table_has_expected_columns(): void
    {
        $cols = [
            'id', 'title', 'description', 'test_type',
            'total_duration_minutes', 'break_duration_minutes',
            'status', 'created_at', 'updated_at',
        ];

        foreach ($cols as $col) {
            $this->assertTrue(
                Schema::hasColumn('tests', $col),
                "Column [tests.{$col}] should exist"
            );
        }
    }

    public function test_questions_table_has_expected_columns(): void
    {
        $cols = [
            'id', 'passage_id', 'paired_passage_id', 'question_number',
            'stem', 'question_type', 'difficulty', 'section_type',
            'skill_domain', 'skill_subdomain', 'spr_hint',
            'calculator_allowed', 'external_id',
        ];

        foreach ($cols as $col) {
            $this->assertTrue(
                Schema::hasColumn('questions', $col),
                "Column [questions.{$col}] should exist"
            );
        }
    }

    public function test_modules_table_has_expected_columns(): void
    {
        $cols = [
            'id', 'section_id', 'module_number', 'difficulty_level',
            'duration_minutes', 'total_questions', 'order',
        ];

        foreach ($cols as $col) {
            $this->assertTrue(
                Schema::hasColumn('modules', $col),
                "Column [modules.{$col}] should exist"
            );
        }
    }

    public function test_score_conversions_table_has_expected_columns(): void
    {
        $cols = [
            'id', 'test_id', 'section_type', 'm2_difficulty',
            'raw_score', 'scaled_score',
        ];

        foreach ($cols as $col) {
            $this->assertTrue(
                Schema::hasColumn('score_conversions', $col),
                "Column [score_conversions.{$col}] should exist"
            );
        }
    }

    // ================================================================
    // LỚP 2: RELATIONSHIP & CONSTRAINT TESTS
    // FK cascade, unique constraints, enum validation
    // ================================================================

    // ----------------------------------------------------------------
    // 2A: CASCADE DELETE — Xóa parent → child bị xóa theo
    // ----------------------------------------------------------------

    public function test_deleting_test_cascades_to_sections(): void
    {
        $testId = DB::table('tests')->insertGetId([
            'title' => 'SAT Practice 1',
            'test_type' => 'full_length',
            'total_duration_minutes' => 134,
            'break_duration_minutes' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sections')->insert([
            'test_id' => $testId,
            'name' => 'Reading and Writing',
            'type' => 'reading_writing',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('sections', 1);

        DB::table('tests')->where('id', $testId)->delete();

        $this->assertDatabaseCount('sections', 0);
    }

    public function test_deleting_section_cascades_to_modules(): void
    {
        $testId = $this->createTest();
        $sectionId = $this->createSection($testId);

        DB::table('modules')->insert([
            'section_id' => $sectionId,
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 32,
            'total_questions' => 27,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sections')->where('id', $sectionId)->delete();

        $this->assertDatabaseCount('modules', 0);
    }

    public function test_deleting_module_cascades_to_routing(): void
    {
        $testId = $this->createTest();
        $sectionId = $this->createSection($testId);
        $m1Id = $this->createModule($sectionId, 1, 'standard');
        $m2Id = $this->createModule($sectionId, 2, 'hard');

        DB::table('module_routing')->insert([
            'from_module_id' => $m1Id,
            'to_module_id' => $m2Id,
            'condition' => 'score_above',
            'threshold_score' => 18,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('modules')->where('id', $m1Id)->delete();

        $this->assertDatabaseCount('module_routing', 0);
    }

    public function test_deleting_question_cascades_to_choices_media_spr_explanations(): void
    {
        $qId = $this->createStandaloneQuestion();

        // Tạo answer choices
        foreach (['A', 'B', 'C', 'D'] as $i => $label) {
            DB::table('answer_choices')->insert([
                'question_id' => $qId,
                'label' => $label,
                'content' => "Choice {$label}",
                'is_correct' => $i === 0,
                'order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Tạo media
        DB::table('question_media')->insert([
            'question_id' => $qId,
            'media_type' => 'image',
            'file_path' => '/images/q1.png',
            'position' => 'stem',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tạo SPR answers
        DB::table('spr_correct_answers')->insert([
            'question_id' => $qId,
            'answer' => '2.5',
            'answer_type' => 'exact',
            'created_at' => now(),
        ]);

        // Tạo explanation
        DB::table('question_explanations')->insert([
            'question_id' => $qId,
            'explanation' => 'Because...',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Xóa question → tất cả child phải bị xóa
        DB::table('questions')->where('id', $qId)->delete();

        $this->assertDatabaseCount('answer_choices', 0);
        $this->assertDatabaseCount('question_media', 0);
        $this->assertDatabaseCount('spr_correct_answers', 0);
        $this->assertDatabaseCount('question_explanations', 0);
    }

    public function test_deleting_module_cascades_to_module_questions(): void
    {
        $testId = $this->createTest();
        $sectionId = $this->createSection($testId);
        $moduleId = $this->createModule($sectionId, 1, 'standard');
        $qId = $this->createStandaloneQuestion();

        DB::table('module_questions')->insert([
            'module_id' => $moduleId,
            'question_id' => $qId,
            'position' => 1,
            'created_at' => now(),
        ]);

        DB::table('modules')->where('id', $moduleId)->delete();

        $this->assertDatabaseCount('module_questions', 0);
        // Question vẫn tồn tại (chỉ pivot bị xóa)
        $this->assertDatabaseHas('questions', ['id' => $qId]);
    }

    public function test_deleting_passage_cascades_to_paired_passages(): void
    {
        $pA = $this->createPassage('Passage A');
        $pB = $this->createPassage('Passage B');

        DB::table('paired_passages')->insert([
            'passage_a_id' => $pA,
            'passage_b_id' => $pB,
            'created_at' => now(),
        ]);

        DB::table('passages')->where('id', $pA)->delete();

        $this->assertDatabaseCount('paired_passages', 0);
    }

    public function test_deleting_test_cascades_to_score_conversions(): void
    {
        $testId = $this->createTest();

        DB::table('score_conversions')->insert([
            'test_id' => $testId,
            'section_type' => 'reading_writing',
            'm2_difficulty' => 'hard',
            'raw_score' => 54,
            'scaled_score' => 800,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tests')->where('id', $testId)->delete();

        $this->assertDatabaseCount('score_conversions', 0);
    }

    // ----------------------------------------------------------------
    // 2B: NULL ON DELETE — Xóa parent → child giữ lại, FK = null
    // ----------------------------------------------------------------

    public function test_deleting_passage_nullifies_question_passage_id(): void
    {
        $passageId = $this->createPassage('Sample passage');

        $qId = DB::table('questions')->insertGetId([
            'passage_id' => $passageId,
            'question_number' => 1,
            'stem' => 'What is the main idea?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'medium',
            'section_type' => 'reading_writing',
            'skill_domain' => 'information_and_ideas',
            'calculator_allowed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('passages')->where('id', $passageId)->delete();

        $this->assertDatabaseHas('questions', [
            'id' => $qId,
            'passage_id' => null,
        ]);
    }

    public function test_deleting_paired_passage_nullifies_question_reference(): void
    {
        $pA = $this->createPassage('Text A');
        $pB = $this->createPassage('Text B');

        $ppId = DB::table('paired_passages')->insertGetId([
            'passage_a_id' => $pA,
            'passage_b_id' => $pB,
            'created_at' => now(),
        ]);

        $qId = DB::table('questions')->insertGetId([
            'passage_id' => $pA,
            'paired_passage_id' => $ppId,
            'question_number' => 1,
            'stem' => 'Compare the two passages.',
            'question_type' => 'multiple_choice',
            'difficulty' => 'hard',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'calculator_allowed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('paired_passages')->where('id', $ppId)->delete();

        $this->assertDatabaseHas('questions', [
            'id' => $qId,
            'paired_passage_id' => null,
        ]);
    }

    // ----------------------------------------------------------------
    // 2C: UNIQUE CONSTRAINTS — Chặn dữ liệu trùng lặp
    // ----------------------------------------------------------------

    public function test_module_questions_rejects_duplicate_question_in_same_module(): void
    {
        $testId = $this->createTest();
        $sectionId = $this->createSection($testId);
        $moduleId = $this->createModule($sectionId, 1, 'standard');
        $qId = $this->createStandaloneQuestion();

        DB::table('module_questions')->insert([
            'module_id' => $moduleId,
            'question_id' => $qId,
            'position' => 1,
            'created_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('module_questions')->insert([
            'module_id' => $moduleId,
            'question_id' => $qId,
            'position' => 2,
            'created_at' => now(),
        ]);
    }

    public function test_module_questions_rejects_duplicate_position_in_same_module(): void
    {
        $testId = $this->createTest();
        $sectionId = $this->createSection($testId);
        $moduleId = $this->createModule($sectionId, 1, 'standard');
        $q1 = $this->createStandaloneQuestion();
        $q2 = $this->createStandaloneQuestion();

        DB::table('module_questions')->insert([
            'module_id' => $moduleId,
            'question_id' => $q1,
            'position' => 1,
            'created_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('module_questions')->insert([
            'module_id' => $moduleId,
            'question_id' => $q2,
            'position' => 1,
            'created_at' => now(),
        ]);
    }

    public function test_score_conversions_rejects_duplicate_combination(): void
    {
        $testId = $this->createTest();

        $data = [
            'test_id' => $testId,
            'section_type' => 'math',
            'm2_difficulty' => 'hard',
            'raw_score' => 44,
            'scaled_score' => 800,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('score_conversions')->insert($data);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $data['scaled_score'] = 790;
        DB::table('score_conversions')->insert($data);
    }

    // ----------------------------------------------------------------
    // 2D: ENUM VALIDATION — Chặn giá trị không hợp lệ
    // ----------------------------------------------------------------

    public function test_test_type_enum_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('tests')->insert([
            'title' => 'Bad Test',
            'test_type' => 'invalid_type',
            'total_duration_minutes' => 60,
            'break_duration_minutes' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_question_difficulty_enum_rejects_invalid_value(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('questions')->insert([
            'question_number' => 1,
            'stem' => 'Test?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'nightmare',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'calculator_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ================================================================
    // LỚP 3: BUSINESS LOGIC DATA — Scenario thực tế Bluebook
    // ================================================================

    public function test_can_create_full_sat_structure(): void
    {
        $testId = $this->createTest();

        // R&W Section
        $rwSectionId = $this->createSection($testId, 'Reading and Writing', 'reading_writing', 1);
        $rwM1 = $this->createModule($rwSectionId, 1, 'standard', 32, 27);
        $rwM2Easy = $this->createModule($rwSectionId, 2, 'easy', 32, 27);
        $rwM2Hard = $this->createModule($rwSectionId, 2, 'hard', 32, 27);

        // Math Section
        $mathSectionId = $this->createSection($testId, 'Math', 'math', 2);
        $mathM1 = $this->createModule($mathSectionId, 1, 'standard', 35, 22);
        $mathM2Easy = $this->createModule($mathSectionId, 2, 'easy', 35, 22);
        $mathM2Hard = $this->createModule($mathSectionId, 2, 'hard', 35, 22);

        // Routing rules
        DB::table('module_routing')->insert([
            [
                'from_module_id' => $rwM1,
                'to_module_id' => $rwM2Hard,
                'condition' => 'score_above',
                'threshold_score' => 18,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'from_module_id' => $rwM1,
                'to_module_id' => $rwM2Easy,
                'condition' => 'score_below_equal',
                'threshold_score' => 18,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'from_module_id' => $mathM1,
                'to_module_id' => $mathM2Hard,
                'condition' => 'score_above',
                'threshold_score' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'from_module_id' => $mathM1,
                'to_module_id' => $mathM2Easy,
                'condition' => 'score_below_equal',
                'threshold_score' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertDatabaseCount('sections', 2);
        $this->assertDatabaseCount('modules', 6);
        $this->assertDatabaseCount('module_routing', 4);
    }

    public function test_question_can_be_shared_across_modules(): void
    {
        $testA = $this->createTest();
        $testB = $this->createTest();

        $secA = $this->createSection($testA);
        $secB = $this->createSection($testB);

        $modA = $this->createModule($secA, 2, 'easy');
        $modB = $this->createModule($secB, 2, 'hard');

        $sharedQ = $this->createStandaloneQuestion();

        DB::table('module_questions')->insert([
            [
                'module_id' => $modA,
                'question_id' => $sharedQ,
                'position' => 5,
                'created_at' => now(),
            ],
            [
                'module_id' => $modB,
                'question_id' => $sharedQ,
                'position' => 12,
                'created_at' => now(),
            ],
        ]);

        $this->assertDatabaseCount('module_questions', 2);
        $this->assertDatabaseCount('questions', 1);
    }

    public function test_spr_question_accepts_multiple_equivalent_answers(): void
    {
        $qId = DB::table('questions')->insertGetId([
            'question_number' => 1,
            'stem' => 'What is 5 divided by 2?',
            'question_type' => 'student_produced_response',
            'difficulty' => 'easy',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'spr_hint' => 'Enter a fraction or decimal',
            'calculator_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $answers = [
            ['answer' => '2.5', 'answer_type' => 'exact'],
            ['answer' => '2.50', 'answer_type' => 'exact'],
            ['answer' => '5/2', 'answer_type' => 'fraction_equivalent'],
            ['answer' => '10/4', 'answer_type' => 'fraction_equivalent'],
        ];

        foreach ($answers as $a) {
            DB::table('spr_correct_answers')->insert([
                'question_id' => $qId,
                'answer' => $a['answer'],
                'answer_type' => $a['answer_type'],
                'created_at' => now(),
            ]);
        }

        $count = DB::table('spr_correct_answers')
            ->where('question_id', $qId)
            ->count();

        $this->assertEquals(4, $count);
    }

    public function test_cross_text_question_links_to_paired_passages(): void
    {
        $passageA = $this->createPassage('Author A argues that...');
        $passageB = $this->createPassage('Author B counters that...');

        $ppId = DB::table('paired_passages')->insertGetId([
            'passage_a_id' => $passageA,
            'passage_b_id' => $passageB,
            'relationship' => 'contrasting',
            'created_at' => now(),
        ]);

        $qId = DB::table('questions')->insertGetId([
            'passage_id' => $passageA,
            'paired_passage_id' => $ppId,
            'question_number' => 1,
            'stem' => 'How would Author B respond to Author A?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'hard',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'calculator_allowed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $question = DB::table('questions')->find($qId);
        $this->assertEquals($ppId, $question->paired_passage_id);
        $this->assertEquals($passageA, $question->passage_id);
    }

    public function test_score_conversion_hard_module_gives_higher_score(): void
    {
        $testId = $this->createTest();

        DB::table('score_conversions')->insert([
            [
                'test_id' => $testId,
                'section_type' => 'reading_writing',
                'm2_difficulty' => 'easy',
                'raw_score' => 40,
                'scaled_score' => 580,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'test_id' => $testId,
                'section_type' => 'reading_writing',
                'm2_difficulty' => 'hard',
                'raw_score' => 40,
                'scaled_score' => 680,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $easyScore = DB::table('score_conversions')
            ->where('test_id', $testId)
            ->where('m2_difficulty', 'easy')
            ->where('raw_score', 40)
            ->value('scaled_score');

        $hardScore = DB::table('score_conversions')
            ->where('test_id', $testId)
            ->where('m2_difficulty', 'hard')
            ->where('raw_score', 40)
            ->value('scaled_score');

        $this->assertGreaterThan($easyScore, $hardScore);
    }

    public function test_module_blueprint_defines_domain_distribution(): void
    {
        $blueprints = [
            ['skill_domain' => 'information_and_ideas', 'min' => 12, 'max' => 14],
            ['skill_domain' => 'craft_and_structure', 'min' => 8, 'max' => 10],
            ['skill_domain' => 'expression_of_ideas', 'min' => 3, 'max' => 4],
            ['skill_domain' => 'standard_english_conventions', 'min' => 2, 'max' => 3],
        ];

        foreach ($blueprints as $bp) {
            DB::table('module_blueprints')->insert([
                'section_type' => 'reading_writing',
                'module_number' => 1,
                'difficulty_level' => 'standard',
                'skill_domain' => $bp['skill_domain'],
                'min_questions' => $bp['min'],
                'max_questions' => $bp['max'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $total_min = DB::table('module_blueprints')
            ->where('section_type', 'reading_writing')
            ->where('module_number', 1)
            ->sum('min_questions');

        $total_max = DB::table('module_blueprints')
            ->where('section_type', 'reading_writing')
            ->where('module_number', 1)
            ->sum('max_questions');

        $this->assertGreaterThanOrEqual(25, $total_min);
        $this->assertLessThanOrEqual(31, $total_max);
        $this->assertGreaterThanOrEqual($total_min, 27);
        $this->assertLessThanOrEqual($total_max, 27);
    }

    public function test_question_with_all_four_choices_and_one_correct(): void
    {
        $qId = $this->createStandaloneQuestion();

        $choices = [
            ['label' => 'A', 'content' => 'Choice A', 'is_correct' => true],
            ['label' => 'B', 'content' => 'Choice B', 'is_correct' => false],
            ['label' => 'C', 'content' => 'Choice C', 'is_correct' => false],
            ['label' => 'D', 'content' => 'Choice D', 'is_correct' => false],
        ];

        foreach ($choices as $i => $c) {
            DB::table('answer_choices')->insert([
                'question_id' => $qId,
                'label' => $c['label'],
                'content' => $c['content'],
                'is_correct' => $c['is_correct'],
                'order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $totalChoices = DB::table('answer_choices')
            ->where('question_id', $qId)->count();
        $correctCount = DB::table('answer_choices')
            ->where('question_id', $qId)
            ->where('is_correct', true)->count();

        $this->assertEquals(4, $totalChoices);
        $this->assertEquals(1, $correctCount);
    }

    public function test_passage_can_be_linked_to_multiple_questions(): void
    {
        $passageId = $this->createPassage('A long reading passage...');

        for ($i = 1; $i <= 3; $i++) {
            DB::table('questions')->insert([
                'passage_id' => $passageId,
                'question_number' => $i,
                'stem' => "Question {$i} about the passage",
                'question_type' => 'multiple_choice',
                'difficulty' => 'medium',
                'section_type' => 'reading_writing',
                'skill_domain' => 'information_and_ideas',
                'calculator_allowed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $count = DB::table('questions')
            ->where('passage_id', $passageId)->count();

        $this->assertEquals(3, $count);
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    private function createTest(): int
    {
        return DB::table('tests')->insertGetId([
            'title' => 'SAT Practice Test',
            'test_type' => 'full_length',
            'total_duration_minutes' => 134,
            'break_duration_minutes' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSection(
        int $testId,
        string $name = 'Reading and Writing',
        string $type = 'reading_writing',
        int $order = 1
        ): int
    {
        return DB::table('sections')->insertGetId([
            'test_id' => $testId,
            'name' => $name,
            'type' => $type,
            'order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createModule(
        int $sectionId,
        int $moduleNumber = 1,
        string $difficulty = 'standard',
        int $duration = 32,
        int $totalQ = 27
        ): int
    {
        return DB::table('modules')->insertGetId([
            'section_id' => $sectionId,
            'module_number' => $moduleNumber,
            'difficulty_level' => $difficulty,
            'duration_minutes' => $duration,
            'total_questions' => $totalQ,
            'order' => $moduleNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPassage(string $content = 'Sample passage'): int
    {
        return DB::table('passages')->insertGetId([
            'content' => $content,
            'passage_type' => 'single',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStandaloneQuestion(): int
    {
        return DB::table('questions')->insertGetId([
            'question_number' => 1,
            'stem' => 'What is 2 + 2?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'calculator_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}