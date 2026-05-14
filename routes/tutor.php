<?php

use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\User\CourseController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:tutor|superadmin'])->group(function () {
    Route::group(['prefix' => 'tutor', 'as' => 'tutor.'], function () {
        // Corsi tutor
        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');

        Route::get('/live-stream/{module}/player', [LiveStreamController::class, 'tutorPlayer'])->name('live-stream.player');
        Route::post('/live-stream/{module}/join', [LiveStreamController::class, 'tutorJoin'])->name('live-stream.join');
        Route::get('/live-stream/{module}/state', [LiveStreamController::class, 'tutorState'])->name('live-stream.state');
        Route::post('/live-stream/{module}/presence', [LiveStreamController::class, 'tutorPresence'])->name('live-stream.presence');
        Route::post('/live-stream/{module}/messages', [LiveStreamController::class, 'storeTutorMessage'])->name('live-stream.messages.store');
        Route::get('/live-stream/{module}/documents/{document}', [LiveStreamController::class, 'downloadTutorDocument'])->name('live-stream.documents.download');
        Route::delete('/live-stream/{module}/messages/{message}', [LiveStreamController::class, 'destroyTutorMessage'])->name('live-stream.messages.destroy');

        // Profilo utente
        Route::get('profile', [UserController::class, 'editOwnProfile'])->name('profile.edit');
        Route::put('profile', [UserController::class, 'updateOwnProfile'])->name('profile.update');
    });
});
