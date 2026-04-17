<?php

use App\Http\Controllers\LiveStreamController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'teacher', 'as' => 'teacher.'], function () {
    Route::get('/live-stream/player', [LiveStreamController::class, 'teacherPlayer'])->name('live-stream.player');
});
