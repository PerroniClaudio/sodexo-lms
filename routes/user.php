<?php

use App\Http\Controllers\LiveStreamController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
    Route::get('/live-stream/player', [LiveStreamController::class, 'userPlayer'])->name('live-stream.player');
});
