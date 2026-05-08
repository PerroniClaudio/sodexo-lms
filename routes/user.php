<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\ScormPlayerController;
use App\Http\Controllers\ScormRuntimeController;
use App\Http\Controllers\User\CourseController;
use App\Http\Controllers\User\QuizModuleController;
use App\Http\Controllers\User\VideoModuleController;
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

        Route::scopeBindings()->group(function () {
            Route::post('/courses/{course}/modules/{module}/scorm/{scormPackage}/launch', [ScormPlayerController::class, 'launch'])->name('courses.modules.scorm.launch');
            Route::get('/courses/{course}/modules/{module}/scorm/{scormPackage}/player', [ScormPlayerController::class, 'player'])->name('courses.modules.scorm.player');
            Route::get('/courses/{course}/modules/{module}/scorm/{scormPackage}/asset/{path}', [ScormPlayerController::class, 'asset'])
                ->where('path', '.*')
                ->name('courses.modules.scorm.asset');

            Route::post('/courses/{course}/modules/{module}/scorm/{scormPackage}/runtime/initialize', [ScormRuntimeController::class, 'initialize'])->name('courses.modules.scorm.runtime.initialize');
            Route::post('/courses/{course}/modules/{module}/scorm/{scormPackage}/runtime/get-value', [ScormRuntimeController::class, 'getValue'])->name('courses.modules.scorm.runtime.get-value');
            Route::post('/courses/{course}/modules/{module}/scorm/{scormPackage}/runtime/set-value', [ScormRuntimeController::class, 'setValue'])->name('courses.modules.scorm.runtime.set-value');
            Route::post('/courses/{course}/modules/{module}/scorm/{scormPackage}/runtime/commit', [ScormRuntimeController::class, 'commit'])->name('courses.modules.scorm.runtime.commit');
            Route::post('/courses/{course}/modules/{module}/scorm/{scormPackage}/runtime/terminate', [ScormRuntimeController::class, 'terminate'])->name('courses.modules.scorm.runtime.terminate');
            Route::post('/courses/{course}/modules/{module}/scorm/{scormPackage}/runtime/get-last-error', [ScormRuntimeController::class, 'getLastError'])->name('courses.modules.scorm.runtime.get-last-error');
            Route::post('/courses/{course}/modules/{module}/scorm/{scormPackage}/runtime/get-error-string', [ScormRuntimeController::class, 'getErrorString'])->name('courses.modules.scorm.runtime.get-error-string');
            Route::post('/courses/{course}/modules/{module}/scorm/{scormPackage}/runtime/get-diagnostic', [ScormRuntimeController::class, 'getDiagnostic'])->name('courses.modules.scorm.runtime.get-diagnostic');

            // Modulo corrente: player
            Route::get('/courses/{course}/modules/{module}/player', [CourseController::class, 'showModule'])->name('courses.modules.player');

            // Modulo video: signed playback URL
            Route::get('/courses/{course}/modules/{module}/video/signed-playback', [VideoModuleController::class, 'signedPlayback'])->name('courses.modules.video.signed-playback');
            // Modulo video: registra avanzamento
            Route::post('/courses/{course}/modules/{module}/video/progress', [VideoModuleController::class, 'progress'])->name('courses.modules.video.progress');
            // Modulo video: segna completato
            Route::post('/courses/{course}/modules/{module}/video/complete', [VideoModuleController::class, 'complete'])->name('courses.modules.video.complete');

            // Modulo quiz: stato
            Route::get('/courses/{course}/modules/{module}/quiz/status', [QuizModuleController::class, 'getStatus'])->name('courses.modules.quiz.status');
            // Modulo quiz: inizia tentativo
            Route::post('/courses/{course}/modules/{module}/quiz/start', [QuizModuleController::class, 'startAttempt'])->name('courses.modules.quiz.start');
            // Modulo quiz: prossima domanda
            Route::get('/courses/{course}/modules/{module}/quiz/next-question', [QuizModuleController::class, 'getNextQuestion'])->name('courses.modules.quiz.next-question');
            // Modulo quiz: invia risposta
            Route::post('/courses/{course}/modules/{module}/quiz/answer', [QuizModuleController::class, 'submitAnswer'])->name('courses.modules.quiz.answer');
            // Modulo quiz: completa tentativo
            Route::post('/courses/{course}/modules/{module}/quiz/complete', [QuizModuleController::class, 'completeAttempt'])->name('courses.modules.quiz.complete');
            // Modulo quiz: abbandona tentativo
            Route::post('/courses/{course}/modules/{module}/quiz/abandon', [QuizModuleController::class, 'abandonAttempt'])->name('courses.modules.quiz.abandon');

            // DEPRECATED: Manteniamo per retrocompatibilità quiz gradimento (se non usa il nuovo flow)
            // Modulo quiz: domande (legacy)
            Route::get('/courses/{course}/modules/{module}/quiz', [QuizModuleController::class, 'show'])->name('courses.modules.quiz.show');
            // Modulo quiz: invio risposte (legacy)
            Route::post('/courses/{course}/modules/{module}/quiz/submit', [QuizModuleController::class, 'submit'])->name('courses.modules.quiz.submit');
        });

        // Profilo utente
        Route::get('profile', [UserController::class, 'editOwnProfile'])->name('profile.edit');
        Route::put('profile', [UserController::class, 'updateOwnProfile'])->name('profile.update');

        // Corsi utente
        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    });
});
