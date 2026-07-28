<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ResendVerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', LogoutController::class);

    // Email verification routes
    Route::post('/email/verification-notification', ResendVerificationController::class);
});
