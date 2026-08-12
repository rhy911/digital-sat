<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\AnswerChoice;
use App\Models\Question;
use App\Services\BulkQuestionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BulkQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Test $test;
    private Section $section;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);

        $this->test = Test::create([
            'title' => 'Test SAT Import',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
        ]);

        $this->section = Section::create([
            'test_id' => $this->test->id,
            'type' => 'reading_writing',
            'name' => 'Reading and Writing',
            'order' => 1,
        ]);

        $this->module = Module::create([
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 32,
            'total_questions' => 27,
            'key' => 'MOD_IMPORT',
            'order' => 1,
        ]);

        $this->module->sections()->attach($this->section->id);
    }

    /**
     * The import preview and the test engine must render question content
     * through the same pipeline. They used to disagree: the preview ran no
     * markdown at all, so `\%` looked right there and broke during the test,
     * while `\\%` did the opposite.
     */
    public function test_bulk_preview_returns_html_identical_to_the_engine_renderer(): void
    {
        $stem = 'A price drops by $$25\%$$ to $$\$630$$. What was $$\displaystyle \frac{a}{b}$$?';

        $response = $this->actingAs($this->user)->postJson(route('home-dashboard.questions.bulk-preview'), [
            'module_id' => $this->module->id,
            'items' => [[
                'question_type' => 'multiple_choice',
                'difficulty' => 'medium',
                'skill_domain' => 'craft_and_structure',
                'stem' => $stem,
                'passage' => 'A short passage with $$x\%$$ inside.',
                'choices' => ['A' => '$$\$840$$', 'B' => '$$\$472$$', 'C' => '$$50\%$$', 'D' => '$$60\%$$'],
                'correct_choice' => 'A',
                'explanation' => 'Because $$0.75P = 630$$.',
            ]],
        ]);

        $response->assertOk();
        $item = $response->json('data.items.0');

        $this->assertSame(\App\Support\QuestionContentRenderer::markdown($stem), $item['stem_html']);
        $this->assertSame(
            \App\Support\QuestionContentRenderer::markdown('A short passage with $$x\%$$ inside.'),
            $item['passage_html']
        );
        $this->assertSame(
            \App\Support\QuestionContentRenderer::markdown('Because $$0.75P = 630$$.'),
            $item['explanation_html']
        );
        $this->assertSame(
            \App\Support\QuestionContentRenderer::markdown('$$\$840$$'),
            $item['choices'][0]['content_html']
        );

        // The escape survives all the way to the browser, in plain-LaTeX form.
        $this->assertStringContainsString('$$25\%$$', $item['stem_html']);
        $this->assertStringContainsString('$$\$630$$', $item['stem_html']);
    }

    public function test_render_preview_endpoint_matches_the_engine_renderer(): void
    {
        $legacy = '$$40\\\%$$ of $$\\\$50$$';

        $response = $this->actingAs($this->user)->postJson(route('home-dashboard.questions.render-preview'), [
            'fields' => ['stem' => $legacy],
        ]);

        $response->assertOk();
        $this->assertSame(
            \App\Support\QuestionContentRenderer::markdown($legacy),
            $response->json('data.fields.stem')
        );
        // Legacy double-backslash content collapses to plain LaTeX.
        $this->assertStringContainsString('$$40\%$$', $response->json('data.fields.stem'));
        $this->assertStringContainsString('$$\$50$$', $response->json('data.fields.stem'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function readingWritingItem(array $overrides = []): array
    {
        return array_merge([
            'question_type' => 'multiple_choice',
            'difficulty' => 'medium',
            'skill_domain' => 'information_and_ideas',
            'skill_subdomain' => 'inferences',
            'stem' => 'What can be inferred from the text?',
            'passage' => 'A short passage.',
            'choices' => ['A' => 'One', 'B' => 'Two', 'C' => 'Three', 'D' => 'Four'],
            'correct_choice' => 'B',
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function importItems(array $items): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->postJson(route('home-dashboard.questions.bulk-store'), [
            'module_id' => $this->module->id,
            'items' => $items,
        ]);
    }

    /**
     * Validation keys contain literal dots ("items.0.stem"), which dot-notation
     * assertions cannot address, so read the error bag directly.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function assertImportRejects(array $items, string $errorKey, string $expectedFragment): void
    {
        $response = $this->importItems($items);
        $response->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertArrayHasKey($errorKey, $errors, 'Expected a validation error on '.$errorKey);
        $this->assertStringContainsString($expectedFragment, $errors[$errorKey][0]);
    }

    public function test_it_rejects_a_domain_that_is_not_in_the_taxonomy(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(['skill_domain' => 'algebra'])],
            'items.0.skill_domain',
            'Question 1: unknown skill_domain "algebra"'
        );
    }

    public function test_it_rejects_a_subdomain_borrowed_from_another_domain(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(['skill_subdomain' => 'words_in_context'])],
            'items.0.skill_subdomain',
            'belongs to domain "craft_and_structure"'
        );
    }

    public function test_it_rejects_a_multiple_choice_question_with_no_correct_answer(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(['correct_choice' => 'E'])],
            'items.0.correct_choice',
            'Question 1: no answer choice is marked correct'
        );
    }

    public function test_it_rejects_content_with_unbalanced_math_delimiters(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(['stem' => 'What is $$x$$ when $$y = 2 ?'])],
            'items.0.stem',
            'Question 1 stem has unbalanced $$ delimiters'
        );
    }

    public function test_it_rejects_latex_that_was_not_escaped_for_json(): void
    {
        // json_decode turns a single-backslash "\frac" into a form feed, which
        // parses cleanly and silently corrupts the formula.
        $corrupted = json_decode('{"stem":"Value of $$\frac{1}{2}$$?"}', true)['stem'];

        $this->assertImportRejects(
            [$this->readingWritingItem(['stem' => $corrupted])],
            'items.0.stem',
            'control characters'
        );
    }

    public function test_it_rejects_duplicate_stems_within_one_import(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(), $this->readingWritingItem()],
            'items.1.stem',
            'Question 2 has the same stem as question 1'
        );
    }

    /**
     * Laravel's defaults name the zero-indexed key and never the accepted
     * values: "The items.1.stem field is required." and "The selected
     * items.3.question_type is invalid." Both sent authors to the wrong
     * question with no idea what to write instead.
     */
    public function test_a_missing_required_field_names_the_question_in_human_terms(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(), $this->readingWritingItem(['stem' => null])],
            'items.1.stem',
            'Question 2 stem is missing. Every question needs stem text.'
        );
    }

    public function test_an_invalid_difficulty_lists_the_accepted_values(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(['difficulty' => 'super-hard'])],
            'items.0.difficulty',
            'Question 1 difficulty must be "easy", "medium" or "hard".'
        );
    }

    public function test_an_invalid_question_type_lists_the_accepted_values(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(['question_type' => 'mcq'])],
            'items.0.question_type',
            'Question 1 question type must be "multiple_choice" or "student_produced_response".'
        );
    }

    public function test_choices_in_the_wrong_shape_show_the_expected_shape(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(['choices' => 'A,B,C,D'])],
            'items.0.choices',
            'Question 1 answer choices must be a list, or an object like'
        );
    }

    public function test_a_reading_and_writing_question_without_a_passage_says_so(): void
    {
        $this->assertImportRejects(
            [$this->readingWritingItem(['passage' => null])],
            'items.0.passage',
            'Question 1 has no passage.'
        );
    }

    public function test_an_spr_without_accepted_answers_says_what_to_add(): void
    {
        // Math module so SPR is allowed at all.
        $mathSection = Section::create([
            'test_id' => $this->test->id,
            'type' => 'math',
            'name' => 'Math',
            'order' => 2,
        ]);
        $mathModule = Module::create([
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 35,
            'total_questions' => 22,
            'key' => 'MOD_MATH_IMPORT',
            'order' => 1,
        ]);
        $mathModule->sections()->attach($mathSection->id);

        $response = $this->actingAs($this->user)->postJson(route('home-dashboard.questions.bulk-store'), [
            'module_id' => $mathModule->id,
            'items' => [[
                'stem' => 'Solve for $$x$$.',
                'question_type' => 'student_produced_response',
                'difficulty' => 'easy',
                'skill_domain' => 'algebra',
                'skill_subdomain' => 'linear_equations_in_one_variable',
            ]],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'Question 1 is a student-produced response but has no accepted answer',
            $response->json('errors')['items.0.spr_correct_answers'][0]
        );
    }

    public function test_an_empty_import_says_there_are_no_questions(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('home-dashboard.questions.bulk-store'), [
            'module_id' => $this->module->id,
            'items' => [],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'No questions found to import.',
            collect($response->json('errors'))->flatten()->implode(' ')
        );
    }

    public function test_it_still_imports_a_well_formed_question(): void
    {
        $this->importItems([$this->readingWritingItem(['stem' => 'A clean stem with $$50\%$$ in it.'])])
            ->assertStatus(201);

        $this->assertDatabaseHas('questions', ['skill_subdomain' => 'inferences']);
    }

    public function test_render_preview_endpoint_requires_authentication(): void
    {
        $this->postJson(route('home-dashboard.questions.render-preview'), [
            'fields' => ['stem' => 'x'],
        ])->assertUnauthorized();
    }

    /**
     * Build a ZIP in memory and post it to the ZIP import endpoint.
     *
     * @param  array<string, string>  $entries  path inside the archive => contents
     */
    private function importZip(array $entries): \Illuminate\Testing\TestResponse
    {
        Storage::fake('public');
        Storage::fake('local');

        $zipPath = tempnam(sys_get_temp_dir(), 'zip_error_case') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Could not create test ZIP file.');
        }
        foreach ($entries as $path => $contents) {
            $zip->addFromString($path, $contents);
        }
        $zip->close();

        $response = $this->actingAs($this->user)->postJson(route('home-dashboard.questions.bulk-zip'), [
            'zip_file' => new UploadedFile($zipPath, 'package.zip', 'application/zip', null, true),
            'module_id' => $this->module->id,
            'start_position' => 1,
        ]);

        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function zipReadyItem(array $overrides = []): array
    {
        return array_merge([
            'stem' => 'A question that needs a figure.',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'skill_domain' => 'information_and_ideas',
            'skill_subdomain' => 'inferences',
            'passage' => 'Some passage context',
            'correct_choice' => 'A',
            'choices' => ['A' => 'One', 'B' => 'Two', 'C' => 'Three', 'D' => 'Four'],
        ], $overrides);
    }

    /**
     * Every ZIP failure used to collapse into one 500: "ZIP Import Failed due to
     * a server error." These assert the teacher now learns which file, which
     * question and what to do about it.
     */
    public function test_zip_reports_the_file_that_contains_invalid_json(): void
    {
        $response = $this->importZip(['questions.json' => '{"items": [ {"stem": "broken" ]}']);

        $response->assertStatus(422);
        $message = collect($response->json('errors'))->flatten()->implode(' ');
        $this->assertStringContainsString('questions.json is not valid JSON', $message);
        // The hint that covers the most common cause: single-backslash LaTeX.
        $this->assertStringContainsString('write "\\\\frac", not "\\frac"', $message);
    }

    public function test_zip_reports_json_that_parses_but_holds_no_questions(): void
    {
        $response = $this->importZip(['questions.json' => '{"meta": {"author": "someone"}}']);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'contains no questions',
            collect($response->json('errors'))->flatten()->implode(' ')
        );
    }

    public function test_zip_lists_its_contents_when_no_data_file_is_present(): void
    {
        $response = $this->importZip([
            'images/q01_graph.png' => 'fake image binary data',
            'readme.txt' => 'notes',
        ]);

        $response->assertStatus(422);
        $message = collect($response->json('errors'))->flatten()->implode(' ');
        $this->assertStringContainsString('No .json or .csv data file found', $message);
        $this->assertStringContainsString('readme.txt', $message);
    }

    public function test_zip_names_every_question_whose_image_is_missing(): void
    {
        $json = json_encode(['items' => [
            $this->zipReadyItem(['stem' => 'First question [Media:q01_graph.png]']),
            $this->zipReadyItem(['stem' => 'Second question is fine.']),
            $this->zipReadyItem([
                'stem' => 'Third question [Media:q03_triangle.png]',
                'explanation' => 'See [Media:q03_solution.png]',
            ]),
        ]]);

        $response = $this->importZip([
            'questions.json' => $json,
            'images/q01_graph.png' => 'fake image binary data',
        ]);

        $response->assertStatus(422);
        $message = collect($response->json('errors'))->flatten()->implode("\n");

        // Question 1's image is present, so it must not be reported.
        $this->assertStringNotContainsString('q01_graph.png', $message);
        $this->assertStringContainsString('Question 3 stem: image "q03_triangle.png"', $message);
        $this->assertStringContainsString('Question 3 explanation: image "q03_solution.png"', $message);
        $this->assertStringContainsString('not present in the ZIP', $message);

        // Nothing may reach the database when media is unresolved.
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_zip_rejects_an_unsupported_image_type(): void
    {
        $json = json_encode(['items' => [
            $this->zipReadyItem(['stem' => 'Look at [Media:diagram.bmp]']),
        ]]);

        $response = $this->importZip(['questions.json' => $json, 'diagram.bmp' => 'data']);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'is not a supported image type',
            collect($response->json('errors'))->flatten()->implode(' ')
        );
    }

    /**
     * Test importing questions from a ZIP file where answer choices contain media.
     */
    public function test_import_zip_with_choices_media(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        // Create a temporary zip file
        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_import') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail("Could not create test ZIP file.");
        }

        // Add a fake image to the zip
        $dummyImageContent = 'fake image binary data';
        $zip->addFromString('images/choice_a_image.png', $dummyImageContent);

        // Add a json file to the zip with questions
        $questionsJson = json_encode([
            'items' => [
                [
                    'stem' => 'What is the answer for this question?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'easy',
                    'skill_domain' => 'information_and_ideas',
                    'passage' => 'Some passage context',
                    'correct_choice' => 'A',
                    'choices' => [
                        'A' => 'This is choice A [Media:choice_a_image.png]',
                        'B' => 'This is choice B without media',
                        'C' => 'This is choice C',
                        'D' => 'This is choice D'
                    ],
                    'explanation' => 'Some explanation text.'
                ]
            ]
        ]);

        $zip->addFromString('questions.json', $questionsJson);
        $zip->close();

        // Wrap the zip in an UploadedFile
        $uploadedFile = new UploadedFile(
            $zipPath,
            'questions_import.zip',
            'application/zip',
            null,
            true // test mode
        );

        $response = $this->actingAs($this->user)
            ->postJson(route('home-dashboard.questions.bulk-zip'), [
                'zip_file' => $uploadedFile,
                'module_id' => $this->module->id,
                'start_position' => 1,
            ]);

        // Clean up temp file
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success'
        ]);

        // Assert question was created
        $question = Question::where('stem', 'What is the answer for this question?')->first();
        $this->assertNotNull($question);

        // Assert choice A contains the media URL markdown
        $choiceA = AnswerChoice::where('question_id', $question->id)->where('label', 'A')->first();
        $this->assertNotNull($choiceA);
        $this->assertStringContainsString('![](', $choiceA->content);
        $this->assertStringContainsString('/media/', $choiceA->content);

        // Verify choice B does not have media
        $choiceB = AnswerChoice::where('question_id', $question->id)->where('label', 'B')->first();
        $this->assertNotNull($choiceB);
        $this->assertEquals('This is choice B without media', $choiceB->content);

        // Assert file was stored on public disk
        $matches = [];
        preg_match('/\/media\/([a-zA-Z0-9]+\.png)/', $choiceA->content, $matches);
        $this->assertNotEmpty($matches);
        $storedFilename = $matches[1];
        Storage::disk('public')->assertExists('media/' . $storedFilename);
    }

    public function test_import_with_subdomain_and_other_fields(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        // Create a temporary zip file
        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_import') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail("Could not create test ZIP file.");
        }

        // Add a json file to the zip with questions
        $questionsJson = json_encode([
            'items' => [
                [
                    'stem' => 'A question testing subdomain import.',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'easy',
                    'skill_domain' => 'information_and_ideas',
                    'skill_subdomain' => 'inferences',
                    'passage' => 'Some passage context',
                    'correct_choice' => 'A',
                    'choices' => [
                        'A' => 'Correct option',
                        'B' => 'Incorrect option',
                        'C' => 'Incorrect option',
                        'D' => 'Incorrect option'
                    ],
                    'explanation' => 'Main explanation text.',
                    'strategy_tip' => 'Solve carefully.',
                    'common_mistakes' => "Don't rush.",
                    'spr_hint' => 'Pick option A',
                    'calculator_allowed' => false,
                    'is_pretest' => true,
                    'external_id' => 'EXT-12345'
                ]
            ]
        ]);

        $zip->addFromString('questions.json', $questionsJson);
        $zip->close();

        // Wrap the zip in an UploadedFile
        $uploadedFile = new UploadedFile(
            $zipPath,
            'questions_import.zip',
            'application/zip',
            null,
            true // test mode
        );

        $response = $this->actingAs($this->user)
            ->postJson(route('home-dashboard.questions.bulk-zip'), [
                'zip_file' => $uploadedFile,
                'module_id' => $this->module->id,
                'start_position' => 1,
            ]);

        // Clean up temp file
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $response->assertStatus(201);

        // Assert question was created with all fields
        $question = Question::where('stem', 'A question testing subdomain import.')->first();
        $this->assertNotNull($question);
        $this->assertEquals('inferences', $question->skill_subdomain);
        $this->assertEquals('EXT-12345', $question->external_id);
        $this->assertEquals('Pick option A', $question->spr_hint);
        $this->assertFalse($question->calculator_allowed);
        $this->assertTrue($question->is_pretest);

        // Assert explanation, strategy_tip, common_mistakes are created
        $explanation = $question->explanation;
        $this->assertNotNull($explanation);
        $this->assertEquals('Main explanation text.', $explanation->explanation);
        $this->assertEquals('Solve carefully.', $explanation->strategy_tip);
        $this->assertEquals("Don't rush.", $explanation->common_mistakes);
    }

    public function test_import_sets_created_by_correctly(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'teacher_approval_status' => 'approved']);
        $this->module->update(['created_by' => $teacher->id]);

        $response = $this->actingAs($teacher)
            ->postJson(route('home-dashboard.questions.bulk-store'), [
                'module_id' => $this->module->id,
                'start_position' => 1,
                'items' => [
                    [
                        'stem' => 'Teacher created question via bulk import.',
                        'question_type' => 'multiple_choice',
                        'difficulty' => 'easy',
                        'skill_domain' => 'information_and_ideas',
                        'passage' => 'Some passage context',
                        'correct_choice' => 'A',
                        'choices' => [
                            'A' => 'Choice A',
                            'B' => 'Choice B',
                            'C' => 'Choice C',
                            'D' => 'Choice D'
                        ]
                    ]
                ]
            ]);

        $response->assertStatus(201);

        $question = Question::where('stem', 'Teacher created question via bulk import.')->first();
        $this->assertNotNull($question);
        $this->assertEquals($teacher->id, $question->created_by);
    }

    public function test_import_zip_rejects_path_traversal_in_filename(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_import') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail("Could not create test ZIP file.");
        }

        $questionsJson = json_encode([
            'items' => [
                [
                    'stem' => 'question',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'easy',
                    'skill_domain' => 'information_and_ideas',
                    'passage' => 'Some passage',
                    'correct_choice' => 'A',
                    'choices' => [
                        'A' => 'Choice A',
                        'B' => 'Choice B'
                    ]
                ]
            ]
        ]);

        $zip->addFromString('questions.json', $questionsJson);
        $zip->addFromString('../../../etc/passwd', 'fake content');
        $zip->close();

        $uploadedFile = new UploadedFile($zipPath, 'questions_import.zip', 'application/zip', null, true);

        $response = $this->actingAs($this->user)
            ->postJson(route('home-dashboard.questions.bulk-zip'), [
                'zip_file' => $uploadedFile,
                'module_id' => $this->module->id,
                'start_position' => 1,
            ]);

        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['zip_file']);
    }

    public function test_import_zip_prevents_local_file_disclosure_via_media_tag(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        
        $secretPath = storage_path('app/secret.txt');
        file_put_contents($secretPath, 'SUPER SECRET CONTENT');

        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_import') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail("Could not create test ZIP file.");
        }

        $questionsJson = json_encode([
            'items' => [
                [
                    'stem' => 'What is the answer for this question?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'easy',
                    'skill_domain' => 'information_and_ideas',
                    'passage' => 'Some passage',
                    'correct_choice' => 'A',
                    'choices' => [
                        'A' => 'Choice A [Media:../../secret.txt]',
                        'B' => 'Choice B'
                    ]
                ]
            ]
        ]);

        $zip->addFromString('questions.json', $questionsJson);
        $zip->close();

        $uploadedFile = new UploadedFile($zipPath, 'questions_import.zip', 'application/zip', null, true);

        $response = $this->actingAs($this->user)
            ->postJson(route('home-dashboard.questions.bulk-zip'), [
                'zip_file' => $uploadedFile,
                'module_id' => $this->module->id,
                'start_position' => 1,
            ]);

        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        // The traversal attempt is now refused outright rather than silently
        // ignored, so nothing is written at all. The security property under
        // test is unchanged and stricter: secret.txt is never copied out.
        $response->assertStatus(422);
        $this->assertStringContainsString(
            'is not a supported image type',
            collect($response->json('errors'))->flatten()->implode(' ')
        );

        $this->assertDatabaseCount('questions', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles('media'));
        $this->assertSame('SUPER SECRET CONTENT', file_get_contents($secretPath));

        if (file_exists($secretPath)) {
            unlink($secretPath);
        }
    }
}
