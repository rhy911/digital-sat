<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Đăng ký 2 routes như theo yêu cầu
Route::get('/students', [UserController::class, 'get_data']);
Route::post('/students', [UserController::class, 'insert_data']);
