<?php

use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\User\CourseController;
use App\Http\Controllers\User\CourseEnrollmentController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active.role:tutor|superadmin'])->group(function () {
    Route::group(['prefix' => 'tutor', 'as' => 'tutor.'], function () {
        Route::get('dashboard', [UserController::class, 'tutorDashboard'])->name('dashboard');
        Route::get('dashboard/calendar-events', [UserController::class, 'tutorCalendarEvents'])->name('dashboard.calendar-events');
        Route::get('dashboard/calendar-events/fake', [UserController::class, 'fakeCalendarEvents'])->name('dashboard.calendar-events.fake');

        // Corsi tutor
        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('courses/{course}/cover-image', [CourseController::class, 'showCoverImage'])->name('courses.cover-image.show');
        Route::get('courses/{course}/poster-pdf', [CourseController::class, 'downloadPosterPdf'])->name('courses.poster-pdf.download');
        Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
        Route::get('courses/{course}/attendance', [CourseController::class, 'tutorAttendance'])->name('courses.attendance.index');
        Route::post('courses/{course}/attendance/scan', [CourseController::class, 'scanTutorAttendanceQr'])->name('courses.attendance.scan');
        Route::post('courses/{course}/attendance/{enrollment}', [CourseController::class, 'storeTutorAttendance'])->name('courses.attendance.store');
        Route::get('api/courses/{course}/enrollments', [CourseEnrollmentController::class, 'indexApi'])->name('api.courses.enrollments.index');

        Route::get('/live-stream/{module}/player', [LiveStreamController::class, 'tutorPlayer'])->name('live-stream.player');
        Route::post('/live-stream/{module}/join', [LiveStreamController::class, 'tutorJoin'])->name('live-stream.join');
        Route::get('/live-stream/{module}/state', [LiveStreamController::class, 'tutorState'])->name('live-stream.state');
        Route::post('/live-stream/{module}/presence', [LiveStreamController::class, 'tutorPresence'])->name('live-stream.presence');
        Route::post('/live-stream/{module}/messages', [LiveStreamController::class, 'storeTutorMessage'])->name('live-stream.messages.store');
        Route::get('/live-stream/{module}/documents/{document}', [LiveStreamController::class, 'downloadTutorDocument'])->name('live-stream.documents.download');
        Route::delete('/live-stream/{module}/messages/{message}', [LiveStreamController::class, 'destroyTutorMessage'])->name('live-stream.messages.destroy');
        Route::patch('/live-stream/{module}/participants/{participant}/speaker', [LiveStreamController::class, 'updateSpeaker'])->name('live-stream.participants.speaker');

        // Profilo utente
        Route::get('profile', [UserController::class, 'editOwnProfile'])->name('profile.edit');
        Route::put('profile', [UserController::class, 'updateOwnProfile'])->name('profile.update');
    });
});
