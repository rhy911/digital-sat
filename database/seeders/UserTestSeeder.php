<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = \App\Models\User::first();
        $test = \App\Models\Test::first();

        if ($user && $test) {
            \App\Models\UserTest::create([
                'user_id' => $user->id,
                'test_id' => $test->id,
                'score_reading_writing' => 700,
                'score_math' => 750,
                'total_score' => 1450,
                'status' => 'completed',
                'completed_at' => now()->subDays(5),
            ]);

            \App\Models\UserTest::create([
                'user_id' => $user->id,
                'test_id' => $test->id,
                'score_reading_writing' => 680,
                'score_math' => 720,
                'total_score' => 1400,
                'status' => 'completed',
                'completed_at' => now()->subDays(12),
            ]);
        }
    }
}
