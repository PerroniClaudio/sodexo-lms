<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'role:admin|superadmin'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::middleware('auth')->get('/area-riservata', function () {
    return view('reserved-area');
})->name('reserved-area');

include 'admin.php';
include 'auth.php';
include 'teacher.php';
include 'tutor.php';
include 'user.php';
