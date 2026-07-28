<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Test;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TestBuilderController extends Controller
{
    private const QUESTIONS_TABLE_PER_PAGE = 30;

    /**
     * Display the test data input dashboard.
     */
    public function index()
    {
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

        $tests = $this->testsQuery();
        $passages = $this->passagesQuery();
        $allModules = $this->modulesQuery();
        $questionsPerPage = self::QUESTIONS_TABLE_PER_PAGE;

        return view('admin.test-builder.index', compact('tests', 'passages', 'questions', 'questionsTotal', 'questionsPerPage', 'allModules'));
    }

    /**
     * JSON bundle of dashboard data for client-side refresh without a full page reload.
     */
    public function snapshot()
    {
        return response()->json([
            'tests' => $this->testsQuery(),
            'passages' => $this->passagesQuery(),
            'allModules' => $this->modulesQuery(),
        ]);
    }

    private function testsQuery(): LengthAwarePaginator
    {
        return Test::visibleTo(auth()->user())
            ->where('title', '!=', 'Test Preview')
            ->with(['creator', 'shares.teacher', 'sections.creator', 'sections.modules.creator'])
            ->withCount(['userTests', 'shares'])
            ->latest()
            ->paginate(30);
    }

    private function passagesQuery(): LengthAwarePaginator
    {
        return Passage::latest()->paginate(30);
    }

    private function modulesQuery(): LengthAwarePaginator
    {
        return Module::visibleTo(auth()->user())
            ->whereHas('sections.test', fn ($q) => $q->where('title', '!=', 'Test Preview'))
            ->with(['creator', 'sections.test'])
            ->withCount('questions')
            ->latest()
            ->paginate(30);
    }
}
