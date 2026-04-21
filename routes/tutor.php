<?php

use App\Http\Controllers\LiveStreamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:tutor|superadmin'])->group(function () {
    Route::group(['prefix' => 'tutor', 'as' => 'tutor.'], function () {
        Route::get('/live-stream/{module}/player', [LiveStreamController::class, 'tutorPlayer'])->name('live-stream.player');
        Route::post('/live-stream/{module}/join', [LiveStreamController::class, 'tutorJoin'])->name('live-stream.join');
        Route::get('/live-stream/{module}/state', [LiveStreamController::class, 'tutorState'])->name('live-stream.state');
        Route::post('/live-stream/{module}/presence', [LiveStreamController::class, 'tutorPresence'])->name('live-stream.presence');
        Route::post('/live-stream/{module}/messages', [LiveStreamController::class, 'storeTutorMessage'])->name('live-stream.messages.store');
    });
});
