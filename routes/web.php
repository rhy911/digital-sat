<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResendVerificationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\PageController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Engine\AttemptController;
use App\Http\Controllers\Engine\SessionController;
use App\Http\Controllers\Engine\AnswerController;
use App\Http\Controllers\Engine\SubmissionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', LandingController::class)->name('landing');
Route::get('/landing-new', function () {
    return view('public.landing');
});
Route::redirect('/home', '/student/progress');

// Guest auth routes
Route::middleware('guest')->group(function () {
    Route::get('/signin', [PageController::class, 'showSignin'])->name('signin');
    Route::get('/signin/form', [PageController::class, 'showSigninForm'])->name('signin.form');
    Route::post('/signin', LoginController::class);

    Route::get('/signup', [PageController::class, 'showSignup'])->name('signup');
    Route::post('/signup', RegisterController::class);

    Route::get('/forgot', [PageController::class, 'showForgot'])->name('forgot');
    Route::post('/forgot', ForgotPasswordController::class);

    Route::get('/reset-password/{token}', [PageController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', ResetPasswordController::class)->name('password.update');
});

// Verification notice
Route::get('/email-verify', [PageController::class, 'showEmailVerifyNotice'])->name('verify.email.notice');

// Email verification action
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::post('/email/verification-notification', ResendVerificationController::class)->name('verification.send');
    Route::post('/logout', LogoutController::class)->name('logout');
});

// Verified Student routes
Route::middleware(['auth', 'verified'])->prefix('student')->group(function () {
    Route::get('/dashboard', \App\Http\Controllers\Student\DashboardController::class)->name('home.legacy');
    Route::get('/progress', \App\Http\Controllers\Student\AnalyticsController::class)->name('home');
    Route::get('/progress-detail', \App\Http\Controllers\Student\AnalyticsController::class)->name('home.progress');
    
    Route::get('/practice', [\App\Http\Controllers\Student\PracticeController::class, 'index'])->name('home.practice');
    Route::get('/practice/preview', [\App\Http\Controllers\Student\PracticeController::class, 'preview'])->name('test.preview');
    Route::get('/practice/{userTest:ulid}', [\App\Http\Controllers\Student\PracticeController::class, 'show'])->name('my-practice');
    Route::delete('/practice/{userTest:ulid}', [\App\Http\Controllers\Student\PracticeController::class, 'destroy'])->name('my-practice.destroy');
    Route::get('/scores/{userTest:ulid}', [\App\Http\Controllers\Student\ScoreController::class, 'show'])->name('my-practice.score');
});

// Verified Engine routes
Route::middleware(['auth', 'verified'])->prefix('engine')->group(function () {
    Route::get('/session/{ulid?}', [SessionController::class, 'show'])->name('engine.session');
    Route::get('/submit-status/{userTest:ulid}', [SubmissionController::class, 'checkStatus'])->name('engine.submit-status');
    
    Route::get('/test/{test_id}/attempt-options', [AttemptController::class, 'attemptOptions'])->name('engine.test.attempt-options');
    Route::post('/test/start/{test_id}', [AttemptController::class, 'startTest'])->name('engine.test.start');
    Route::post('/test/autosave-module', [AnswerController::class, 'autosave'])
        ->middleware('throttle:60,1')
        ->name('engine.test.autosave-module');
    Route::post('/test/submit-module', [SubmissionController::class, 'submit'])->name('engine.test.submit-module');
});

// Verified Admin/Teacher routes
Route::middleware(['auth', 'verified', 'role:admin,teacher'])->prefix('admin')->name('home-dashboard.')->group(function () {
    Route::get('/teacher/test-builder', [\App\Http\Controllers\Admin\TestBuilderController::class, 'index'])->name('index');
    Route::get('/teacher/test-builder/snapshot', [\App\Http\Controllers\Admin\TestBuilderController::class, 'snapshot'])->name('snapshot');
    
    // Questions list and search
    Route::get('/questions/list', [\App\Http\Controllers\Admin\QuestionController::class, 'index'])->name('questions.list');
    Route::get('/questions/search', [\App\Http\Controllers\Admin\QuestionController::class, 'search'])->name('questions.search');
    Route::get('/questions/{id}', [\App\Http\Controllers\Admin\QuestionController::class, 'show'])->name('questions.show');
    Route::put('/questions/{id}', [\App\Http\Controllers\Admin\QuestionController::class, 'update'])->name('questions.update');
    Route::delete('/questions/{id}', [\App\Http\Controllers\Admin\QuestionController::class, 'destroy'])->name('questions.delete');
    
    Route::post('/questions/bulk', [\App\Http\Controllers\Admin\QuestionController::class, 'bulkStore'])->name('questions.bulk-store');
    Route::post('/questions/bulk-preview', [\App\Http\Controllers\Admin\QuestionController::class, 'bulkPreview'])->name('questions.bulk-preview');
    Route::post('/questions/bulk-csv', [\App\Http\Controllers\Admin\QuestionController::class, 'bulkStoreCsv'])->name('questions.bulk-csv-store');
    Route::post('/questions/bulk-csv-preview', [\App\Http\Controllers\Admin\QuestionController::class, 'bulkPreviewCsv'])->name('questions.bulk-csv-preview');
    Route::post('/questions/bulk-zip', [\App\Http\Controllers\Admin\QuestionController::class, 'bulkStoreZip'])->name('questions.bulk-zip');
    Route::post('/questions/attach', [\App\Http\Controllers\Admin\QuestionController::class, 'attach'])->name('questions.attach');

    // Tests
    Route::post('/tests', [\App\Http\Controllers\Admin\TestController::class, 'store'])->name('tests.store');
    Route::put('/tests/{id}', [\App\Http\Controllers\Admin\TestController::class, 'update'])->name('tests.update');
    Route::delete('/tests/{id}', [\App\Http\Controllers\Admin\TestController::class, 'destroy'])->name('tests.delete');
    Route::post('/tests/generate-full', [\App\Http\Controllers\Admin\TestStructureController::class, 'generateFullSat'])->name('tests.generate-full');
    Route::post('/tests/generate-configured', [\App\Http\Controllers\Admin\TestStructureController::class, 'generateConfigured'])->name('tests.generate-configured');
    Route::post('/tests/{id}/clone', [\App\Http\Controllers\Admin\TestStructureController::class, 'cloneTest'])->name('tests.clone');

    // Sections
    Route::post('/sections', [\App\Http\Controllers\Admin\SectionController::class, 'store'])->name('sections.store');
    Route::put('/sections/{id}', [\App\Http\Controllers\Admin\SectionController::class, 'update'])->name('sections.update');
    Route::delete('/sections/{id}', [\App\Http\Controllers\Admin\SectionController::class, 'destroy'])->name('sections.delete');

    // Modules
    Route::post('/modules', [\App\Http\Controllers\Admin\ModuleController::class, 'store'])->name('modules.store');
    Route::put('/modules/{id}', [\App\Http\Controllers\Admin\ModuleController::class, 'update'])->name('modules.update');
    Route::delete('/modules/{id}', [\App\Http\Controllers\Admin\ModuleController::class, 'destroy'])->name('modules.delete');
    Route::post('/modules/{id}/clone', [\App\Http\Controllers\Admin\TestStructureController::class, 'cloneModule'])->name('modules.clone');

    // Media
    Route::post('/media/upload', [\App\Http\Controllers\Admin\MediaController::class, 'upload'])->name('media.upload');
});
