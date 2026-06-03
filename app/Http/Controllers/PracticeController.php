<?php

namespace App\Http\Controllers;

use App\Models\UserTest;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PracticeController extends Controller
{
    public function show($userTestId)
    {
        $user = Auth::user();
        $userTest = UserTest::with(['test', 'user'])
            ->where('user_id', $user->id)
            ->findOrFail($userTestId);

        $allCompletedTests = UserTest::with('test')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->get();

        return view('tests.practice', [
            'user' => $user,
            'userTest' => $userTest, // Focused test
            'completedTests' => $allCompletedTests, // For the grid
        ]);
    }

    public function scoreDetails($userTestId)
    {
        $user = Auth::user();
        $userTest = UserTest::with(['test', 'user', 'userAnswers.question.explanation', 'userAnswers.question.answerChoices', 'userAnswers.question.sprCorrectAnswers'])
            ->where('user_id', $user->id)
            ->findOrFail($userTestId);

        // Group stats
        $stats = [
            'total' => ['questions' => 0, 'correct' => 0, 'incorrect' => 0, 'omitted' => 0],
            'sections' => [
                'reading_and_writing' => [
                    'total' => 0, 'correct' => 0,
                    'domains' => []
                ],
                'math' => [
                    'total' => 0, 'correct' => 0,
                    'domains' => []
                ]
            ]
        ];

        foreach ($userTest->userAnswers as $answer) {
            $q = $answer->question;
            if (!$q || $q->is_pretest) continue;

            $section = $q->section_type === 'math' ? 'math' : 'reading_and_writing';
            $domain  = $q->skill_domain ?? 'Other';

            if (!isset($stats['sections'][$section]['domains'][$domain])) {
                $stats['sections'][$section]['domains'][$domain] = ['total' => 0, 'correct' => 0];
            }

            $stats['total']['questions']++;
            $stats['sections'][$section]['total']++;
            $stats['sections'][$section]['domains'][$domain]['total']++;

            if ($answer->selected_answer === null || $answer->selected_answer === '') {
                $stats['total']['omitted']++;
            } elseif ($answer->is_correct) {
                $stats['total']['correct']++;
                $stats['sections'][$section]['correct']++;
                $stats['sections'][$section]['domains'][$domain]['correct']++;
            } else {
                $stats['total']['incorrect']++;
            }
        }

        return view('tests.score-details', [
            'user' => $user,
            'userTest' => $userTest,
            'stats' => $stats,
        ]);
    }

    public function testPreview()
    {
        return view('tests.preview');
    }

    public function chooseTest()
    {
        $user = Auth::user();
        $tests = Test::visibleTo($user)
            ->with([
                'sections.modules' => fn ($query) => $query->visibleTo($user),
            ])
            ->where('status', 'active')
            ->where('title', '!=', 'Test Preview')
            ->limit(100)
            ->get();

        return view('tests.choose', compact('tests'));
    }
}
