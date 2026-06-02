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
            ->postJson(route('test-dashboard.questions.bulk-zip'), [
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
        $this->assertStringContainsString('/storage/media/', $choiceA->content);

        // Verify choice B does not have media
        $choiceB = AnswerChoice::where('question_id', $question->id)->where('label', 'B')->first();
        $this->assertNotNull($choiceB);
        $this->assertEquals('This is choice B without media', $choiceB->content);

        // Assert file was stored on public disk
        $matches = [];
        preg_match('/\/storage\/media\/([a-zA-Z0-9]+\.png)/', $choiceA->content, $matches);
        $this->assertNotEmpty($matches);
        $storedFilename = $matches[1];
        Storage::disk('public')->assertExists('media/' . $storedFilename);
    }
}
