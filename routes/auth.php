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

Route::middleware('guest')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/lookup', [OnboardingController::class, 'lookup'])->name('onboarding.lookup');
    Route::get('/onboarding/email', [OnboardingController::class, 'showEmailForm'])->name('onboarding.email.show');
    Route::post('/onboarding/email', [OnboardingController::class, 'storeEmail'])->name('onboarding.email.store');
    Route::post('/onboarding/email/resend', [OnboardingController::class, 'resendVerification'])->name('onboarding.email.resend');
    Route::post('/onboarding/password-reset', [OnboardingController::class, 'sendPasswordReset'])->name('onboarding.password-reset');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'show'])
        ->name('verification.verify');
    Route::post('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'store'])
        ->name('verification.setup');
    Route::get('/email/resend', [EmailVerificationController::class, 'resendForm'])
        ->name('verification.resend.form');
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
        ->name('verification.resend');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/onboarding/complete-profile', [OnboardingController::class, 'show'])
        ->name('onboarding.profile.show');

    Route::post('/onboarding/complete-profile', [OnboardingController::class, 'store'])
        ->name('onboarding.profile.store');
});
