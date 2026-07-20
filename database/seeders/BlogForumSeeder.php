<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogForumSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = User::where('role', 'teacher')->inRandomOrder()->limit(3)->get();
        if ($teachers->isEmpty()) {
            $teachers = User::factory(3)->teacher()->create();
        }

        $posts = [
            ['title' => '5 Warm-Up Drills Before Every Practice Test', 'excerpt' => 'A short routine that gets your brain into test mode before the timer starts.'],
            ['title' => 'Why "Process of Elimination" Beats Guessing', 'excerpt' => 'A simple habit that raises your Reading & Writing accuracy fast.'],
            ['title' => 'Math Section: Calculator Tricks Students Miss', 'excerpt' => 'Three built-in calculator shortcuts most students never use.'],
            ['title' => 'How to Read a Score Report Like a Coach', 'excerpt' => 'Turn your last practice test into a concrete study plan.'],
            ['title' => 'Pacing 101: Don\'t Let One Question Sink Your Module', 'excerpt' => 'A timing framework for adaptive modules that keeps you on track.'],
            ['title' => 'Grammar Rules That Show Up Again and Again', 'excerpt' => 'The handful of grammar patterns the SAT tests most often.'],
            ['title' => 'Building a Two-Week Study Sprint', 'excerpt' => 'What to do when test day is close and time is short.'],
            ['title' => 'Reading Passages: Skim First or Read First?', 'excerpt' => 'The honest answer, with a method for each passage type.'],
        ];

        foreach ($posts as $i => $post) {
            BlogPost::create([
                'teacher_id' => $teachers->random()->id,
                'title' => $post['title'],
                'excerpt' => $post['excerpt'],
                'body' => fake()->paragraphs(rand(4, 7), true),
                'published_at' => now()->subDays(count($posts) - $i),
            ]);
        }

        $authors = User::whereIn('role', ['student', 'teacher'])->inRandomOrder()->limit(12)->get();
        if ($authors->count() < 4) {
            $authors = $authors->merge(User::factory(6)->create());
        }

        $categories = ['Math', 'Reading & Writing', 'General', 'Off-topic'];
        $threadTitles = [
            'How many practice tests before test day?',
            'Best way to review missed math questions?',
            'Anyone else run out of time on the Reading module?',
            'Study group for next month\'s test?',
            'Calculator recommendations for the digital SAT',
            'How strict is the adaptive module really?',
            'Tips for staying calm during the test',
            'Is Khan Academy enough on its own?',
            'Grammar rule I keep forgetting',
            'What score do average state schools want?',
        ];

        foreach ($threadTitles as $title) {
            $thread = ForumThread::create([
                'user_id' => $authors->random()->id,
                'title' => $title,
                'body' => fake()->paragraphs(rand(1, 3), true),
                'category' => fake()->randomElement($categories),
            ]);

            for ($r = 0, $replyCount = rand(0, 5); $r < $replyCount; $r++) {
                ForumReply::create([
                    'thread_id' => $thread->id,
                    'user_id' => $authors->random()->id,
                    'body' => fake()->paragraph(),
                ]);
            }
        }
    }
}
