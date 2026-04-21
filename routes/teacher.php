<?php

use App\Http\Controllers\LiveStreamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:docente|superadmin'])->group(function () {
    Route::group(['prefix' => 'teacher', 'as' => 'teacher.'], function () {
        Route::get('/live-stream/{module}/player', [LiveStreamController::class, 'teacherPlayer'])->name('live-stream.player');
        Route::post('/live-stream/{module}/session/start', [LiveStreamController::class, 'startSession'])->name('live-stream.session.start');
        Route::post('/live-stream/{module}/session/end', [LiveStreamController::class, 'endSession'])->name('live-stream.session.end');
        Route::post('/live-stream/{module}/join', [LiveStreamController::class, 'teacherJoin'])->name('live-stream.join');
        Route::get('/live-stream/{module}/state', [LiveStreamController::class, 'teacherState'])->name('live-stream.state');
        Route::post('/live-stream/{module}/presence', [LiveStreamController::class, 'teacherPresence'])->name('live-stream.presence');
        Route::post('/live-stream/{module}/messages', [LiveStreamController::class, 'storeTeacherMessage'])->name('live-stream.messages.store');
        Route::patch('/live-stream/{module}/participants/{participant}/speaker', [LiveStreamController::class, 'updateSpeaker'])->name('live-stream.participants.speaker');
    });
});
