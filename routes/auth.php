<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'store'])
    ->middleware(array_filter([
        'guest:'.config('fortify.guard'),
        config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null,
    ]))
    ->name('login.store');

// Email verification + password setup (combined)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'show'])
    ->middleware('guest')
    ->name('verification.verify');

Route::post('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'store'])
    ->middleware('guest')
    ->name('verification.setup');

// Resend verification email
Route::get('/email/resend', [EmailVerificationController::class, 'resendForm'])
    ->middleware('guest')
    ->name('verification.resend.form');

Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
    ->middleware('guest')
    ->name('verification.resend');

// Onboarding profile completion
Route::middleware(['auth'])->group(function () {
    Route::get('/onboarding/complete-profile', [OnboardingController::class, 'show'])
        ->name('onboarding.show');

    Route::post('/onboarding/complete-profile', [OnboardingController::class, 'store'])
        ->name('onboarding.store');
});
