<?php

use App\Http\Controllers\LiveStreamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:user|superadmin'])->group(function () {
    Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
        Route::get('/live-stream/{module}/player', [LiveStreamController::class, 'userPlayer'])->name('live-stream.player');
        Route::post('/live-stream/{module}/join', [LiveStreamController::class, 'userJoin'])->name('live-stream.join');
        Route::get('/live-stream/{module}/state', [LiveStreamController::class, 'userState'])->name('live-stream.state');
        Route::post('/live-stream/{module}/presence', [LiveStreamController::class, 'userPresence'])->name('live-stream.presence');
        Route::post('/live-stream/{module}/messages', [LiveStreamController::class, 'storeUserMessage'])->name('live-stream.messages.store');
        Route::post('/live-stream/{module}/polls/{poll}/responses', [LiveStreamController::class, 'storeUserPollResponse'])->name('live-stream.polls.responses.store');
        Route::get('/live-stream/{module}/documents/{document}', [LiveStreamController::class, 'downloadUserDocument'])->name('live-stream.documents.download');
        Route::post('/live-stream/{module}/hand-raises', [LiveStreamController::class, 'storeHandRaise'])->name('live-stream.hand-raises.store');
        Route::delete('/live-stream/{module}/hand-raises/current', [LiveStreamController::class, 'destroyHandRaise'])->name('live-stream.hand-raises.destroy');
    });
});
