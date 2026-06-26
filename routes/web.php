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
    Route::post('/signin', LoginController::class)->middleware('throttle:5,1');

    Route::get('/signup', [PageController::class, 'showSignup'])->name('signup');
    Route::post('/signup', RegisterController::class)->middleware('throttle:5,1');

    Route::get('/forgot', [PageController::class, 'showForgot'])->name('forgot');
    Route::post('/forgot', ForgotPasswordController::class)->middleware('throttle:5,1');

    Route::get('/reset-password/{token}', [PageController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:5,1')->name('password.update');
});

// Verification notice
Route::get('/email-verify', [PageController::class, 'showEmailVerifyNotice'])->name('verify.email.notice');

// Email verification action
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::post('/email/verification-notification', ResendVerificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::post('/logout', LogoutController::class)->name('logout');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/class-documents/{document}/open', [\App\Http\Controllers\ClassroomDocumentAccessController::class, 'open'])->name('class-documents.open');
    Route::get('/class-documents/{document}/download', [\App\Http\Controllers\ClassroomDocumentAccessController::class, 'download'])->name('class-documents.download');
});

Route::middleware(['auth', 'verified'])->get('/dashboard', function () {
    $user = auth()->user();
    return match ($user->role) {
        'teacher' => redirect()->route($user->isApprovedTeacher() ? 'home' : 'teacher.application.status'),
        'admin' => redirect()->route('admin.teacher-applications.index'),
        default => redirect()->route('home'),
    };
})->name('dashboard');

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
    Route::middleware('role:student')->group(function () {
        Route::get('/classes', [\App\Http\Controllers\Student\ClassroomController::class, 'index'])->name('student.classes.index');
        Route::get('/classes/{classroom}', [\App\Http\Controllers\Student\ClassroomController::class, 'show'])->name('student.classes.show');
        Route::post('/classes/join', [\App\Http\Controllers\Student\ClassroomController::class, 'join'])->middleware('throttle:10,1')->name('student.classes.join');
        Route::post('/classes/memberships/{membership}/leave', [\App\Http\Controllers\Student\ClassroomController::class, 'leave'])->name('student.classes.leave');
        Route::get('/assignments', [\App\Http\Controllers\Student\AssignmentController::class, 'index'])->name('student.assignments.index');
        Route::get('/assignments/{assignment}', [\App\Http\Controllers\Student\AssignmentController::class, 'show'])->name('student.assignments.show');
        Route::post('/assignments/{assignment}/start', [\App\Http\Controllers\Student\AssignmentController::class, 'start'])->name('student.assignments.start');
    });
});

Route::middleware(['auth', 'verified', 'role:student'])->get('/join/{code}', function (string $code) {
    return redirect()->route('student.classes.index', ['code' => strtoupper($code)]);
})->name('student.classes.join-link');

Route::middleware(['auth', 'verified', 'role:admin,teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/application', \App\Http\Controllers\Teacher\ApplicationStatusController::class)->name('application.status');

    Route::middleware('teacher.approved')->group(function () {
        Route::get('/workspace', [\App\Http\Controllers\Teacher\ClassroomController::class, 'workspace'])->name('workspace');
        Route::get('/progress', [\App\Http\Controllers\Teacher\ClassroomController::class, 'progress'])->name('progress');
        Route::get('/classes', [\App\Http\Controllers\Teacher\ClassroomController::class, 'index'])->name('classes.index');
        Route::get('/assignments', [\App\Http\Controllers\Teacher\AssignmentController::class, 'index'])->name('assignments.index');
        Route::post('/classes', [\App\Http\Controllers\Teacher\ClassroomController::class, 'store'])->name('classes.store');
        Route::get('/classes/{classroom}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'show'])->name('classes.show');
        Route::put('/classes/{classroom}', [\App\Http\Controllers\Teacher\ClassroomController::class, 'update'])->name('classes.update');
        Route::post('/classes/{classroom}/rotate-code', [\App\Http\Controllers\Teacher\ClassroomController::class, 'rotateCode'])->name('classes.rotate-code');
        Route::post('/classes/{classroom}/archive', [\App\Http\Controllers\Teacher\ClassroomController::class, 'archive'])->name('classes.archive');
        Route::post('/classes/{classroom}/restore', [\App\Http\Controllers\Teacher\ClassroomController::class, 'restore'])->name('classes.restore');
        Route::post('/classes/{classroom}/co-teachers', [\App\Http\Controllers\Teacher\CoTeacherController::class, 'store'])->name('classes.co-teachers.store');
        Route::delete('/classes/{classroom}/co-teachers/{teacher}', [\App\Http\Controllers\Teacher\CoTeacherController::class, 'destroy'])->name('classes.co-teachers.destroy');
        Route::post('/classes/{classroom}/documents', [\App\Http\Controllers\Teacher\ClassroomDocumentController::class, 'store'])->name('classes.documents.store');
        Route::delete('/classes/{classroom}/documents/{document}', [\App\Http\Controllers\Teacher\ClassroomDocumentController::class, 'destroy'])->name('classes.documents.destroy');
        Route::post('/memberships/{membership}/approve', [\App\Http\Controllers\Teacher\MembershipController::class, 'approve'])->name('memberships.approve');
        Route::post('/memberships/{membership}/reject', [\App\Http\Controllers\Teacher\MembershipController::class, 'reject'])->name('memberships.reject');
        Route::post('/memberships/{membership}/remove', [\App\Http\Controllers\Teacher\MembershipController::class, 'remove'])->name('memberships.remove');
        Route::post('/classes/{classroom}/assignments', [\App\Http\Controllers\Teacher\AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('/assignments/{assignment}/students/{student}/attempts', [\App\Http\Controllers\Teacher\AssignmentController::class, 'attemptMonitor'])->name('assignments.attempt-monitor');
        Route::get('/assignments/{assignment}', [\App\Http\Controllers\Teacher\AssignmentController::class, 'show'])->name('assignments.show');
        Route::put('/assignments/{assignment}', [\App\Http\Controllers\Teacher\AssignmentController::class, 'update'])->name('assignments.update');
        Route::post('/assignments/{assignment}/publish', [\App\Http\Controllers\Teacher\AssignmentController::class, 'publish'])->name('assignments.publish');
        Route::post('/assignments/{assignment}/close', [\App\Http\Controllers\Teacher\AssignmentController::class, 'close'])->name('assignments.close');
        Route::post('/assignments/{assignment}/reopen', [\App\Http\Controllers\Teacher\AssignmentController::class, 'reopen'])->name('assignments.reopen');
        Route::delete('/assignments/{assignment}', [\App\Http\Controllers\Teacher\AssignmentController::class, 'destroy'])->name('assignments.destroy');
    });
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/teacher-applications', [\App\Http\Controllers\Admin\TeacherApplicationController::class, 'index'])->name('teacher-applications.index');
    Route::post('/teacher-applications/{teacher}', [\App\Http\Controllers\Admin\TeacherApplicationController::class, 'decide'])->name('teacher-applications.decide');
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
Route::middleware(['auth', 'verified', 'role:admin,teacher', 'teacher.approved'])->prefix('admin')->name('home-dashboard.')->group(function () {
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
    Route::get('/teachers/search', [\App\Http\Controllers\Admin\TestShareController::class, 'searchTeachers'])->name('teachers.search');
    Route::get('/tests/{test}/shares', [\App\Http\Controllers\Admin\TestShareController::class, 'index'])->name('tests.shares.index');
    Route::post('/tests/{test}/shares', [\App\Http\Controllers\Admin\TestShareController::class, 'store'])->name('tests.shares.store');
    Route::delete('/tests/{test}/shares/{teacher}', [\App\Http\Controllers\Admin\TestShareController::class, 'destroy'])->name('tests.shares.destroy');
    Route::post('/tests/generate-full', [\App\Http\Controllers\Admin\TestStructureController::class, 'generateFullSat'])->name('tests.generate-full');
    Route::post('/tests/generate-configured', [\App\Http\Controllers\Admin\TestStructureController::class, 'generateConfigured'])->name('tests.generate-configured');
    Route::post('/tests/{id}/clone', [\App\Http\Controllers\Admin\TestStructureController::class, 'cloneTest'])->name('tests.clone');
    Route::post('/tests/{test}/convert-to-normal', [\App\Http\Controllers\Admin\TestStructureController::class, 'convertToNormal'])->name('tests.convert-to-normal');
    Route::post('/tests/{test}/score-conversions', [\App\Http\Controllers\Admin\ScoreConversionController::class, 'store'])->name('tests.score-conversions.store');
    Route::post('/score-conversions/{scoreConversionSet}/approve', [\App\Http\Controllers\Admin\ScoreConversionController::class, 'approve'])->name('score-conversions.approve');
    Route::get('/tests/reusable-content', [\App\Http\Controllers\Admin\ReusableContentController::class, 'catalog'])->name('tests.reusable-content');
    Route::post('/sections/{section}/derive-test', [\App\Http\Controllers\Admin\ReusableContentController::class, 'deriveSection'])->name('sections.derive-test');
    Route::post('/modules/{module}/derive-test', [\App\Http\Controllers\Admin\ReusableContentController::class, 'deriveModule'])->name('modules.derive-test');
    Route::post('/sections/{section}/reuse', [\App\Http\Controllers\Admin\ReusableContentController::class, 'reuseSection'])->name('sections.reuse');
    Route::post('/modules/{module}/reuse', [\App\Http\Controllers\Admin\ReusableContentController::class, 'reuseModule'])->name('modules.reuse');

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
