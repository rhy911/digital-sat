<?php

use App\Http\Controllers\Auth\LoginWebController;
use App\Http\Controllers\Auth\RegisterWebController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\VerifyEmailWebController;
use App\Http\Controllers\Auth\ResendVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TestDashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

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

Route::get('/forget', function () {
    return view('auth.forgot');
})->name('forget');

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


Route::get('choose-test', function (){
    return view('tests.choose');
})->name('choose-test');

Route::get('/take-test', function () {
    $test = \App\Models\Test::with([
        'sections.modules.questions.passage',
        'sections.modules.questions.answerChoices' => fn($q) => $q->orderBy('order'),
    ])->whereIn('status', ['active', 'draft'])->first();

    if (!$test) {
        abort(404, 'No test available. Please create a test first.');
    }

    if ($test->sections->isEmpty()) {
        abort(404, 'Test has no sections. Please add sections and modules first.');
    }

    // Get the first section (Reading & Writing) and its first module
    $section = $test->sections->firstWhere('type', 'reading_writing') ?? $test->sections->first();
    $module = $section->modules->first() ?? null;

    if (!$module) {
        abort(404, 'No module found for this section. Please add modules first.');
    }

    // Get questions ordered by position (defined in Module::questions relationship)
    $questions = $module->questions;

    if ($questions->isEmpty()) {
        abort(404, 'Module has no questions. Please link questions to this module via the module_questions table.');
    }

    // For now, take the first question (you can add pagination/navigation later)
    $currentQuestion = $questions->first()?->question_number ?? 1;
    $totalQuestions = $questions->count();

    $testData = (object) [
        'page_title' => "Section {$section->order}: {$section->name}",
        'section_title' => "Section {$section->order}: {$section->name}",
        'section_directions' => '<p>Read the passage and answer the questions that follow.</p>',
        'username' => auth()->user()?->username ?? 'Guest',
    ];

    return view('tests.take.test-reading', compact(
        'testData',
        'questions',
        'currentQuestion',
        'totalQuestions'
    ));
})->name('take-test');

// Test Dashboard Routes
Route::middleware(['auth'])->prefix('test-dashboard')->name('test-dashboard.')->group(function () {
    Route::get('/', [TestDashboardController::class, 'index'])->name('index');
    
    // API endpoints for creating data
    Route::post('/tests', [TestDashboardController::class, 'storeTest'])->name('tests.store');
    Route::post('/sections', [TestDashboardController::class, 'storeSection'])->name('sections.store');
    Route::post('/modules', [TestDashboardController::class, 'storeModule'])->name('modules.store');
    Route::post('/passages', [TestDashboardController::class, 'storePassage'])->name('passages.store');
    Route::post('/questions', [TestDashboardController::class, 'storeQuestion'])->name('questions.store');
    Route::post('/answer-choices', [TestDashboardController::class, 'storeAnswerChoices'])->name('answer-choices.store');
    Route::post('/explanations', [TestDashboardController::class, 'storeExplanation'])->name('explanations.store');
    
    // Delete endpoints
    Route::delete('/tests/{id}', [TestDashboardController::class, 'deleteTest'])->name('tests.delete');
    Route::delete('/sections/{id}', [TestDashboardController::class, 'deleteSection'])->name('sections.delete');
    Route::delete('/questions/{id}', [TestDashboardController::class, 'deleteQuestion'])->name('questions.delete');
});