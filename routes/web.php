<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('homepage.index');
});

Route::middleware(['auth', 'role:admin|superadmin'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::middleware('auth')->get('/area-riservata', function () {
    return view('reserved-area');
})->name('reserved-area');

include 'admin.php';
include 'auth.php';
include 'teacher.php';
include 'tutor.php';
include 'user.php';

Route::get('/debug-mail-env', function () {
    return [
        'MAIL_MAILER' => config('mail.default'),
        'MAIL_HOST' => config('mail.mailers.smtp.host'),
        'MAIL_PORT' => config('mail.mailers.smtp.port'),
        'MAIL_FROM' => config('mail.from.address'),
        'env_file' => env('MAIL_FROM_ADDRESS'),
    ];
});

Route::get('/test-mailpit', function () {
    Mail::raw('Test invio mail tramite Mailpit', function ($message) {
        $message->to('test@example.com')->subject('Mailpit funziona!');
    });

    return 'Mail inviata (se Mailpit è attivo, la vedi su http://localhost:8025)';
});
