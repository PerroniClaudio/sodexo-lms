<?php

use App\Http\Controllers\LiveStreamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:docente|superadmin'])->group(function () {
    Route::group(['prefix' => 'teacher', 'as' => 'teacher.'], function () {
        Route::get('/live-stream/{module}/player', [LiveStreamController::class, 'teacherPlayer'])->name('live-stream.player');
    });
});
