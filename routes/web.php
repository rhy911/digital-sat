<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginWebController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterWebController;
use App\Http\Controllers\Auth\ResendVerificationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailWebController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TestDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::middleware('guest')->group(function () {
    Route::get('/signin', function () {
        return view('auth.signin');
    })->name('login');

    Route::post('/signin', LoginWebController::class)->name('signin');
});

Route::get('/signup', function () {
    return view('auth.signup');
})->name('signup');

Route::post('/signup', RegisterWebController::class)->name('signup');

Route::get('/forgot', function () {
    return view('auth.forgot');
})->name('forgot');

Route::get('/email-verify', function () {
    return view('auth.email-verify');
})->name('verify.email.notice');

// Email verification route - public access (hash is the security)
Route::get('/email/verify/{id}/{hash}', VerifyEmailWebController::class)->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::post('/email/verification-notification', ResendVerificationController::class)->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/home', HomeController::class)->name('home');
    Route::get('/my-practice/{user_test_id}', [\App\Http\Controllers\PracticeController::class, 'show'])->name('my-practice');

    Route::post('/test/start/{test_id}', [\App\Http\Controllers\TestTakingController::class, 'startTest'])->name('test.start');
    Route::post('/test/submit-module', [\App\Http\Controllers\TestTakingController::class, 'submitModule'])->name('test.submit-module');

    Route::post('/logout', LogoutController::class)->name('logout');
});

Route::middleware('guest')->group(function () {
    Route::post('/forgot', ForgotPasswordController::class)->name('forgot');

    Route::get('/reset-password/{token}', function (Request $request, $token) {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    })->name('password.reset');

    Route::post('/reset-password', ResetPasswordController::class)->name('password.update');
});

Route::get('/test-preview', function () {
    return view('tests.preview');
})->name('test.preview');

Route::get('choose-test', function () {
    $tests = \App\Models\Test::where('status', 'active')
        ->where('title', '!=', 'Test Preview')
        ->get();
    return view('tests.choose', compact('tests'));
})->name('choose-test');

Route::get('/take-test/{module_id?}', function ($module_id = null) {
    $module = null;
    if ($module_id) {
        $module = \App\Models\Module::with([
            'section.test.sections.modules.questions.passage',
            'section.test.sections.modules.questions.answerChoices' => fn($q) => $q->orderBy('order'),
            'questions.passage',
            'questions.answerChoices' => fn($q) => $q->orderBy('order'),
        ])->find($module_id);

        if (!$module) {
            abort(404, 'Module not found.');
        }
        $test = $module->section->test;
        $section = $module->section;
    } else {
        $test = \App\Models\Test::with([
            'sections.modules.questions.passage',
            'sections.modules.questions.answerChoices' => fn($q) => $q->orderBy('order'),
        ])->whereIn('status', ['active', 'draft'])
          ->orderByRaw("CASE WHEN title = 'Test Preview' THEN 0 ELSE 1 END")
          ->first();

        if (! $test) {
            abort(404, 'No test available. Please create a test first.');
        }

        if ($test->sections->isEmpty()) {
            abort(404, 'Test has no sections. Please add sections and modules first.');
        }

        // Default to first module of first section
        $section = $test->sections->firstWhere('type', 'reading_writing') ?? $test->sections->first();
        $module = $section->modules->first() ?? null;
    }

    if (! $module) {
        abort(404, 'No module found. Please add modules first.');
    }

    // Get questions ordered by position (defined in Module::questions relationship)
    $questions = $module->questions;
    if ($questions->isEmpty()) {
        abort(404, 'Module has no questions.');
    }

    $currentQuestion = 1;
    $totalQuestions = $questions->count();

    $testData = (object) [
        'id' => $test->id,
        'page_title' => "Section {$section->order}, Module {$module->module_number}: {$section->name}",
        'section_title' => "{$section->name} - Module {$module->module_number}",
        'section_number' => $section->order,
        'module_number' => $module->module_number,
        'module_id' => $module->id,
        'username' => \Illuminate\Support\Facades\Auth::user()?->username ?? 'Guest',
        'is_preview' => ($test->title === 'Test Preview'),
        'duration_minutes' => $module->duration_minutes ?? ($section->type === 'math' ? 35 : 32),
    ];

    // Determine next module for navigation (simple logic for now)
    $nextModule = null;
    if ($module->module_number == 1) {
        // Find Module 2 in same section (prefer hard for mock)
        $nextModule = $section->modules->where('module_number', 2)->firstWhere('difficulty_level', 'hard')
            ?? $section->modules->where('module_number', 2)->first();
    } else {
        // Move to next section's first module
        $nextSection = $test->sections->where('order', '>', $section->order)->sortBy('order')->first();
        if ($nextSection) {
            $nextModule = $nextSection->modules->where('module_number', 1)->first();
        }
    }

    // Determine which view to use based on section type
    $viewName = $section->type === 'math' ? 'tests.take.take-math' : 'tests.take.take-reading';

    // Get user test record
    $userTest = null;
    if (Auth::check()) {
        $userTest = \App\Models\UserTest::firstOrCreate([
            'user_id' => Auth::id(),
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);
    }

    return view($viewName, [
        'testData' => $testData,
        'questions' => $questions,
        'currentQuestion' => $currentQuestion,
        'totalQuestions' => $totalQuestions,
        'sectionNumber' => $section->order,
        'moduleNumber' => $module->module_number,
        'sectionName' => $section->name,
        'sectionType' => $section->type,
        'nextModuleId' => $nextModule ? $nextModule->id : null,
        'nextModuleName' => $nextModule ? ($nextModule->module_number == 2 ? 'Module 2' : 'Section ' . $nextModule->section->order) : null,
        'userTestId' => $userTest ? $userTest->id : null,
    ]);
})->name('take-test');

// Test Dashboard Routes
Route::middleware(['auth'])->prefix('test-dashboard')->name('test-dashboard.')->group(function () {
    Route::get('/', [TestDashboardController::class, 'index'])->name('index');
    Route::get('/snapshot', [TestDashboardController::class, 'snapshot'])->name('snapshot');
    Route::get('/questions/list', [TestDashboardController::class, 'questionsList'])->name('questions.list');
    Route::get('/questions/search', [TestDashboardController::class, 'questionsSearch'])->name('questions.search');

    // API endpoints for creating and updating data
    Route::post('/tests', [TestDashboardController::class, 'storeTest'])->name('tests.store');
    Route::put('/tests/{id}', [TestDashboardController::class, 'updateTest'])->name('tests.update');
    Route::post('/sections', [TestDashboardController::class, 'storeSection'])->name('sections.store');
    Route::post('/modules', [TestDashboardController::class, 'storeModule'])->name('modules.store');
    Route::get('/questions/{id}', [TestDashboardController::class, 'showQuestion'])->name('questions.show');
    Route::put('/questions/{id}', [TestDashboardController::class, 'updateQuestion'])->name('questions.update');
    Route::post('/questions/bulk', [TestDashboardController::class, 'bulkStoreQuestions'])->name('questions.bulk-store');
    Route::post('/questions/bulk-preview', [TestDashboardController::class, 'bulkPreviewQuestions'])->name('questions.bulk-preview');
    Route::post('/questions/bulk-csv', [TestDashboardController::class, 'bulkStoreQuestionsFromCsv'])->name('questions.bulk-csv-store');
    Route::post('/questions/bulk-csv-preview', [TestDashboardController::class, 'bulkPreviewQuestionsFromCsv'])->name('questions.bulk-csv-preview');
    Route::post('/questions/bulk-zip', [TestDashboardController::class, 'bulkStoreQuestionsFromZip'])->name('questions.bulk-zip');
    Route::post('/questions/attach', [TestDashboardController::class, 'attachQuestionToModule'])->name('questions.attach');

    // Delete endpoints
    Route::delete('/tests/{id}', [TestDashboardController::class, 'deleteTest'])->name('tests.delete');
    Route::delete('/sections/{id}', [TestDashboardController::class, 'deleteSection'])->name('sections.delete');
    Route::delete('/modules/{id}', [TestDashboardController::class, 'deleteModule'])->name('modules.delete');
    Route::delete('/questions/{id}', [TestDashboardController::class, 'deleteQuestion'])->name('questions.delete');

    // Media
    Route::post('/media/upload', [\App\Http\Controllers\MediaController::class, 'upload'])->name('media.upload');
});
