<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

include 'admin.php';
include 'auth.php';
include 'teacher.php';
include 'tutor.php';
include 'user.php';
