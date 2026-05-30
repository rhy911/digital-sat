<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Test;
use Illuminate\Http\Request;

class TestDashboardController extends Controller
{
    private const QUESTIONS_TABLE_PER_PAGE = 30;

    /**
     * Display the test data input dashboard.
     */
    public function index()
    {
        try {
            $tests = Test::visibleTo(auth()->user())->with(['creator', 'sections.creator', 'sections.modules.creator'])->latest()->paginate(30);
        } catch (\Exception $e) {
            $tests = collect();
        }

        try {
            $passages = Passage::latest()->paginate(30);
        } catch (\Exception $e) {
            $passages = collect();
        }

        try {
            $qQuery = Question::visibleTo(auth()->user());
            if (auth()->user()->role === 'teacher') {
                $qQuery->where('created_by', auth()->id());
            }
            $questionsTotal = $qQuery->count();
            $questions = $qQuery
                ->select(['id', 'section_type', 'stem', 'is_pretest', 'is_complete', 'skill_domain', 'difficulty', 'created_by'])
                ->orderByDesc('id')
                ->limit(self::QUESTIONS_TABLE_PER_PAGE)
                ->get();
        } catch (\Exception $e) {
            $questionsTotal = 0;
            $questions = collect();
        }

        try {
            $allModules = Module::visibleTo(auth()->user())->with(['creator', 'sections.test'])->latest()->paginate(30);
        } catch (\Exception $e) {
            $allModules = collect();
        }

        $questionsPerPage = self::QUESTIONS_TABLE_PER_PAGE;

        return view('test-dashboard', compact('tests', 'passages', 'questions', 'questionsTotal', 'questionsPerPage', 'allModules'));
    }

    /**
     * JSON bundle of dashboard data for client-side refresh without a full page reload.
     */
    public function snapshot()
    {
        $tests = Test::visibleTo(auth()->user())->with(['creator', 'sections.creator', 'sections.modules.creator'])->latest()->paginate(30);
        $passages = Passage::latest()->paginate(30);
        $allModules = Module::visibleTo(auth()->user())->with(['creator', 'sections.test'])->latest()->paginate(30);

        return response()->json([
            'tests' => $tests,
            'passages' => $passages,
            'allModules' => $allModules,
        ]);
    }
}
