<?php

use App\Http\Controllers\LiveStreamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:tutor|superadmin'])->group(function () {
    Route::group(['prefix' => 'tutor', 'as' => 'tutor.'], function () {
        Route::get('/live-stream/{module}/player', [LiveStreamController::class, 'tutorPlayer'])->name('live-stream.player');
    });
});
