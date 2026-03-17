<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::get('/signin', function () {
    return view('auth.signin');
});

Route::get('/signup', function () {
    return view('auth.signup');
});

Route::get('/forget', function () {
    return view('auth.forgot');
});

Route::get('/reset-password', function () {
    return view('auth.reset-password');
});

Route::get('/email-verify', function () {
    return view('auth.email-verify');
});