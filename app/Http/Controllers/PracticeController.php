<?php

namespace App\Http\Controllers;

use App\Models\UserTest;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PracticeController extends Controller
{
    public function show(UserTest $userTest)
    {
        $user = Auth::user();
        $this->authorize('view', $userTest);
        $userTest->load(['test', 'user']);

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

    public function scoreDetails(UserTest $userTest)
    {
        $user = Auth::user();
        $this->authorize('view', $userTest);
        $userTest->load(['test', 'user', 'userAnswers.module', 'userAnswers.question.explanation', 'userAnswers.question.answerChoices', 'userAnswers.question.sprCorrectAnswers']);
        $moduleIds = $userTest->userAnswers->pluck('module_id')->filter()->unique()->values();
        $questionIds = $userTest->userAnswers->pluck('question_id')->filter()->unique()->values();
        $questionPositions = collect();

        if ($moduleIds->isNotEmpty() && $questionIds->isNotEmpty()) {
            $questionPositions = DB::table('module_questions')
                ->whereIn('module_id', $moduleIds)
                ->whereIn('question_id', $questionIds)
                ->get(['module_id', 'question_id', 'position'])
                ->mapWithKeys(fn ($row) => ["{$row->module_id}:{$row->question_id}" => $row->position]);
        }

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
            'questionPositions' => $questionPositions,
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
    public function destroy(UserTest $userTest)
    {
        $this->authorize('delete', $userTest);

        $userTest->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Attempt deleted successfully.']);
        }

        return redirect()->route('home')->with('success', 'Practice attempt deleted.');
    }
}
