<?php

namespace Database\Seeders;

use App\Models\Test;
use App\Models\Section;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\AnswerChoice;
use App\Models\ScoreConversion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DigitalSatMockSeeder extends Seeder
{
    public function run(): void
    {
        $test = Test::create([
            'title' => 'Test Preview',
            'description' => 'Comprehensive mock test with realistic passages and adaptive routing.',
            'test_type' => 'full_length',
            'total_duration_minutes' => 134,
            'break_duration_minutes' => 10,
            'status' => 'active',
        ]);

        // --- SECTIONS ---
        $rwSection = Section::create(['test_id' => $test->id, 'name' => 'Reading and Writing', 'type' => 'reading_writing', 'order' => 1]);
        $mathSection = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 2]);

        // --- MODULES ---
        $rwM1 = Module::create(['section_id' => $rwSection->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 32, 'total_questions' => 27, 'order' => 1]);
        $rwM2H = Module::create(['section_id' => $rwSection->id, 'module_number' => 2, 'difficulty_level' => 'hard', 'duration_minutes' => 32, 'total_questions' => 27, 'order' => 2]);
        $rwM2E = Module::create(['section_id' => $rwSection->id, 'module_number' => 2, 'difficulty_level' => 'easy', 'duration_minutes' => 32, 'total_questions' => 27, 'order' => 3]);

        $mathM1 = Module::create(['section_id' => $mathSection->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 22, 'order' => 1]);
        $mathM2H = Module::create(['section_id' => $mathSection->id, 'module_number' => 2, 'difficulty_level' => 'hard', 'duration_minutes' => 35, 'total_questions' => 22, 'order' => 2]);

        // --- ROUTING ---
        DB::table('module_routing')->insert([
            ['from_module_id' => $rwM1->id, 'to_module_id' => $rwM2H->id, 'condition' => 'score_above', 'threshold_score' => 18, 'created_at' => now()],
            ['from_module_id' => $mathM1->id, 'to_module_id' => $mathM2H->id, 'condition' => 'score_above', 'threshold_score' => 15, 'created_at' => now()],
        ]);

        // --- R&W QUESTIONS (MODULE 1) ---
        $rw_data = [
            [
                'Craft and Structure', 
                'Vocabulary', 
                'The spacecraft OSIRIS-REx briefly made contact with the asteroid 101955 Bennu in 2020. NASA scientist Daniella DellaGiustina reports that despite facing the unexpected obstacle of a surface mostly covered in boulders, OSIRIS-REx successfully ______ a sample of the surface, gathering pieces of it to bring back to Earth.',
                'Which choice completes the text with the most logical and precise word or phrase?',
                ['attached', 'collected', 'followed', 'replaced'], 2
            ],
            [
                'Information and Ideas', 
                'Central Ideas', 
                "Research conducted by planetary scientist Katarina Miljkovic suggests that the Moon's surface may not accurately ______ early impact events. When the Moon was still forming, its surface was softer, and asteroid or meteoroid impacts would have left less of an impression; thus, evidence of early impacts may no longer be present.",
                'Which choice completes the text with the most logical and precise word or phrase?',
                ['reflect', 'receive', 'evaluate', 'mimic'], 1
            ],
            [
                'Craft and Structure', 
                'Text Structure', 
                'Early twentieth-century architect Julia Morgan was known for her meticulous attention to detail and her ability to blend diverse architectural styles seamlessly. This versatility allowed her to design over 700 buildings, ranging from modest bungalows to the opulence of Hearst Castle, throughout her prolific career.',
                'Which choice best describes the function of the second sentence in the overall structure of the text?',
                ['It provides a specific example of the diverse architectural styles mentioned in the first sentence.', 'It explains how Morgan\'s reputation for meticulousness led to her receiving so many commissions.', 'It illustrates the practical result of the versatility attributed to Morgan in the first sentence.', 'It contrasts the modest designs of Morgan\'s early career with her later, more grand projects.'], 2
            ],
            [
                'Standard English Conventions', 
                'Boundaries', 
                'The team of archaeologists discovered a cache of ancient pottery shards during their excavation of the site; these fragments provided crucial evidence regarding the trade routes utilized by the civilization during its peak.',
                'Which choice completes the text so that it conforms to the conventions of Standard English?',
                ['site; these', 'site, these', 'site. These', 'site; These'], 0 // Using the semicolon version
            ],
            [
                'Expression of Ideas', 
                'Transitions', 
                'Many critics initially dismissed the composer\'s latest symphony as being too experimental and lacking a clear melodic structure. ______ subsequent performances have revealed a complex layering of themes that many now consider to be a masterpiece of modern orchestration.',
                'Which choice completes the text with the most logical transition?',
                ['Furthermore,', 'Consequently,', 'However,', 'Similarly,'], 2
            ],
            [
                'Information and Ideas', 
                'Evidence', 
                'A researcher claims that the introduction of a new irrigation system in a drought-prone region significantly increased crop yields. The researcher points to data showing a 40% increase in wheat production in the three years following the system\'s installation compared to the previous decade\'s average.',
                'Which choice best describes the data that would most strongly support the researcher\'s claim?',
                ['A report showing that wheat prices remained stable during the installation period.', 'Data showing that other regions without the new system saw no increase in wheat production.', 'Evidence that the region experienced unusually high rainfall during the three-year period.', 'A survey of local farmers expressing their satisfaction with the new irrigation technology.'], 1
            ]
        ];

        foreach ($rw_data as $i => $data) {
            $p = Passage::create(['content' => $data[2], 'passage_type' => 'single', 'genre' => 'humanities']);
            $q = Question::create([
                'passage_id' => $p->id,
                'stem' => $data[3],
                'question_type' => 'multiple_choice',
                'difficulty' => 'medium',
                'is_pretest' => ($i === 5),
                'section_type' => 'reading_writing',
                'skill_domain' => str_replace(' ', '_', strtolower($data[0]))
            ]);
            $this->createChoices($q->id, [
                ['A', $data[4][0], $data[5] === 0],
                ['B', $data[4][1], $data[5] === 1],
                ['C', $data[4][2], $data[5] === 2],
                ['D', $data[4][3], $data[5] === 3],
            ]);
            $rwM1->questions()->attach($q->id, ['position' => $i + 1]);
        }

        // --- R&W QUESTIONS (MODULE 2) ---
        foreach ($rw_data as $i => $data) {
            $p = Passage::create(['content' => $data[2] . " (Module 2 Version)", 'passage_type' => 'single', 'genre' => 'humanities']);
            $q = Question::create([
                'passage_id' => $p->id,
                'stem' => $data[3],
                'question_type' => 'multiple_choice',
                'difficulty' => 'medium',
                'is_pretest' => false,
                'section_type' => 'reading_writing',
                'skill_domain' => str_replace(' ', '_', strtolower($data[0]))
            ]);
            $this->createChoices($q->id, [
                ['A', $data[4][0], $data[5] === 0],
                ['B', $data[4][1], $data[5] === 1],
                ['C', $data[4][2], $data[5] === 2],
                ['D', $data[4][3], $data[5] === 3],
            ]);
            $rwM2H->questions()->attach($q->id, ['position' => $i + 1]);
            $rwM2E->questions()->attach($q->id, ['position' => $i + 1]);
        }

        // --- MATH QUESTIONS (MODULE 1) ---
        $math_data = [
            ['Algebra', 'MCQ', 'If $2x + 10 = 20$, what is the value of $4x$?', '20'],
            ['Advanced Math', 'MCQ', 'What is the sum of the roots of the quadratic equation $$x^2 - 5x + 6 = 0$$?', '5'],
            ['Problem Solving', 'MCQ', 'A bag contains 3 red marbles and 2 blue marbles. If one marble is selected at random, what is the probability that the marble is red?', '3/5'],
            ['Geometry', 'MCQ', 'A circle has a radius of $r = 3$ units. What is the area of the circle in square units?', '$$9\pi$$'],
            ['Algebra', 'SPR', 'Solve for $x$: $5x - 2 = 13$', '3'],
            ['Advanced Math', 'SPR', 'If $f(x) = x^2 + 4x$, what is the value of $f(2)$?', '12']
        ];

        foreach ($math_data as $i => $data) {
            $q = Question::create([
                'stem' => $data[2],
                'question_type' => ($data[1] === 'MCQ' ? 'multiple_choice' : 'student_produced_response'),
                'difficulty' => 'medium',
                'is_pretest' => ($i === 5),
                'section_type' => 'math',
                'skill_domain' => str_replace(' ', '_', strtolower($data[0]))
            ]);
            if ($data[1] === 'MCQ') {
                $this->createChoices($q->id, [['A', $data[3], true], ['B', '10', false], ['C', '15', false], ['D', '30', false]]);
            } else {
                DB::table('spr_correct_answers')->insert(['question_id' => $q->id, 'answer' => $data[3], 'answer_type' => 'exact', 'created_at' => now()]);
            }
            $mathM1->questions()->attach($q->id, ['position' => $i + 1]);
        }

        // --- MATH QUESTIONS (MODULE 2) ---
        foreach ($math_data as $i => $data) {
            $q = Question::create([
                'stem' => $data[2] . " (Advanced)",
                'question_type' => ($data[1] === 'MCQ' ? 'multiple_choice' : 'student_produced_response'),
                'difficulty' => 'hard',
                'is_pretest' => false,
                'section_type' => 'math',
                'skill_domain' => str_replace(' ', '_', strtolower($data[0]))
            ]);
            if ($data[1] === 'MCQ') {
                $this->createChoices($q->id, [['A', $data[3], true], ['B', '20', false], ['C', '25', false], ['D', '40', false]]);
            } else {
                DB::table('spr_correct_answers')->insert(['question_id' => $q->id, 'answer' => $data[3], 'answer_type' => 'exact', 'created_at' => now()]);
            }
            $mathM2H->questions()->attach($q->id, ['position' => $i + 1]);
        }

        $this->createScoreConversions($test->id);
        $this->command->info('Realistic mock data seeded successfully!');
    }

    private function createChoices($qId, $choices) {
        foreach ($choices as $index => $c) {
            AnswerChoice::create([
                'question_id' => $qId,
                'label' => $c[0],
                'content' => $c[1],
                'is_correct' => $c[2],
                'order' => $index + 1,
            ]);
        }
    }

    private function createScoreConversions($testId) {
        for ($i = 0; $i <= 54; $i++) {
            ScoreConversion::create(['test_id' => $testId, 'section_type' => 'reading_writing', 'm2_difficulty' => 'hard', 'raw_score' => $i, 'scaled_score' => 200 + round(($i / 54) * 600)]);
            ScoreConversion::create(['test_id' => $testId, 'section_type' => 'reading_writing', 'm2_difficulty' => 'easy', 'raw_score' => $i, 'scaled_score' => 200 + round(($i / 54) * 400)]);
        }
        for ($i = 0; $i <= 44; $i++) {
            ScoreConversion::create(['test_id' => $testId, 'section_type' => 'math', 'm2_difficulty' => 'hard', 'raw_score' => $i, 'scaled_score' => 200 + round(($i / 44) * 600)]);
            ScoreConversion::create(['test_id' => $testId, 'section_type' => 'math', 'm2_difficulty' => 'easy', 'raw_score' => $i, 'scaled_score' => 200 + round(($i / 44) * 450)]);
        }
    }
}
