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
    if (Illuminate\Support\Facades\Auth::check()) {
        if (request()->hasCookie(Illuminate\Support\Facades\Auth::getRecallerName())) {
            return view('auth.remembered', ['user' => Illuminate\Support\Facades\Auth::user()]);
        }
        return redirect()->route('home');
    }
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

    Route::get('/home', HomeController::class)->name('home');
    Route::get('/my-practice/{user_test_id}', [\App\Http\Controllers\PracticeController::class, 'show'])->name('my-practice');
    Route::get('/my-practice/{user_test_id}/score', [\App\Http\Controllers\PracticeController::class, 'scoreDetails'])->name('my-practice.score');

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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/test-preview', function () {
        return view('tests.preview');
})->name('test.preview');

Route::get('choose-test', function () {
    $tests = \App\Models\Test::where('status', 'active')
        ->where('title', '!=', 'Test Preview')
        ->limit(100)
        ->get();
    return view('tests.choose', compact('tests'));
})->name('choose-test');

Route::get('/take-test/{module_id?}', [\App\Http\Controllers\TestTakingController::class, 'showModule'])->name('take-test');
Route::get('/submit-status/{userTestId}', [\App\Http\Controllers\TestTakingController::class, 'checkScoringStatus'])->name('submit-status');
});

// Test Dashboard Routes
Route::middleware(['auth', 'verified', 'role:admin,teacher'])->prefix('test-dashboard')->name('test-dashboard.')->group(function () {
    Route::get('/', [\App\Http\Controllers\TestDashboardController::class, 'index'])->name('index');
    Route::get('/snapshot', [\App\Http\Controllers\TestDashboardController::class, 'snapshot'])->name('snapshot');
    Route::get('/questions/list', [\App\Http\Controllers\QuestionController::class, 'questionsList'])->name('questions.list');
    Route::get('/questions/search', [\App\Http\Controllers\QuestionController::class, 'questionsSearch'])->name('questions.search');

    // API endpoints for creating and updating data
    Route::post('/tests', [\App\Http\Controllers\TestController::class, 'storeTest'])->name('tests.store');
    Route::post('/tests/generate-full', [\App\Http\Controllers\TestStructureController::class, 'generateFullSatStructure'])->name('tests.generate-full');
    Route::post('/tests/{id}/clone', [\App\Http\Controllers\TestStructureController::class, 'cloneTest'])->name('tests.clone');
    Route::put('/tests/{id}', [\App\Http\Controllers\TestController::class, 'updateTest'])->name('tests.update');
    Route::post('/sections', [\App\Http\Controllers\SectionController::class, 'storeSection'])->name('sections.store');
    Route::put('/sections/{id}', [\App\Http\Controllers\SectionController::class, 'updateSection'])->name('sections.update');
    Route::post('/sections/link-module', [\App\Http\Controllers\SectionController::class, 'linkModuleToSection'])->name('sections.link-module');
    Route::post('/modules', [\App\Http\Controllers\ModuleController::class, 'storeModule'])->name('modules.store');
    Route::put('/modules/{id}', [\App\Http\Controllers\ModuleController::class, 'updateModule'])->name('modules.update');
    Route::post('/modules/{id}/clone', [\App\Http\Controllers\TestStructureController::class, 'cloneModule'])->name('modules.clone');
    Route::get('/questions/{id}', [\App\Http\Controllers\QuestionController::class, 'showQuestion'])->name('questions.show');
    Route::put('/questions/{id}', [\App\Http\Controllers\QuestionController::class, 'updateQuestion'])->name('questions.update');
    Route::post('/questions/bulk', [\App\Http\Controllers\QuestionController::class, 'bulkStoreQuestions'])->name('questions.bulk-store');
    Route::post('/questions/bulk-preview', [\App\Http\Controllers\QuestionController::class, 'bulkPreviewQuestions'])->name('questions.bulk-preview');
    Route::post('/questions/bulk-csv', [\App\Http\Controllers\QuestionController::class, 'bulkStoreQuestionsFromCsv'])->name('questions.bulk-csv-store');
    Route::post('/questions/bulk-csv-preview', [\App\Http\Controllers\QuestionController::class, 'bulkPreviewQuestionsFromCsv'])->name('questions.bulk-csv-preview');
    Route::post('/questions/bulk-zip', [\App\Http\Controllers\QuestionController::class, 'bulkStoreQuestionsFromZip'])->name('questions.bulk-zip');
    Route::post('/questions/attach', [\App\Http\Controllers\QuestionController::class, 'attachQuestionToModule'])->name('questions.attach');

    // Delete endpoints
    Route::delete('/tests/{id}', [\App\Http\Controllers\TestController::class, 'deleteTest'])->name('tests.delete');
    Route::delete('/sections/{id}', [\App\Http\Controllers\SectionController::class, 'deleteSection'])->name('sections.delete');
    Route::delete('/modules/{id}', [\App\Http\Controllers\ModuleController::class, 'deleteModule'])->name('modules.delete');
    Route::delete('/questions/{id}', [\App\Http\Controllers\QuestionController::class, 'deleteQuestion'])->name('questions.delete');

    // Media
    Route::post('/media/upload', [\App\Http\Controllers\MediaController::class, 'upload'])->name('media.upload');
});
