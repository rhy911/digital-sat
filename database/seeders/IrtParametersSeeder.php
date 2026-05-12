<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

class IrtParametersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Question::chunk(100, function ($questions) {
            foreach ($questions as $question) {
                $irt_a = $question->question_type === 'student_produced_response' ? 1.3 : 0.9;
                $irt_c = $question->question_type === 'student_produced_response' ? 0.0 : 0.25;

                $irt_b = match ($question->difficulty) {
                    'easy' => -1.2,
                    'hard' => 1.4,
                    default => 0.0,
                };

                $question->update([
                    'irt_a' => $irt_a,
                    'irt_b' => $irt_b,
                    'irt_c' => $irt_c,
                ]);
            }
        });
    }
}
