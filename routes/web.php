<?php

use App\Http\Controllers\Auth\LoginWebController;
use App\Http\Controllers\Auth\RegisterWebController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\VerifyEmailWebController;
use App\Http\Controllers\Auth\ResendVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('index');
});

Route::middleware('guest')->group(function () {
    Route::get('/signin', function () {
        return view('auth.signin');
    })->name('signin');

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

    Route::post('/logout', LogoutController::class)->name('logout');
});

Route::middleware('guest')->group(function () {
    Route::post('/forgot', ForgotPasswordController::class)->name('forgot');

    Route::get('/reset-password/{token}', function (Request $request, $token) {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    })->name('password.reset');

    Route::post('/reset-password', ResetPasswordController::class)->name('password.update');
});

Route::get('/home', function () {
    return view('home');
});
