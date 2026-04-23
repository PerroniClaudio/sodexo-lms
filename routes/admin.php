<?php

use App\Http\Controllers\Admin\CourseModuleController;
use App\Http\Controllers\Admin\JobCategoryController;
use App\Http\Controllers\Admin\JobLevelController;
use App\Http\Controllers\Admin\JobRoleController;
use App\Http\Controllers\Admin\JobSectorController;
use App\Http\Controllers\Admin\JobTitleController;
use App\Http\Controllers\Admin\JobUnitController;
use App\Http\Controllers\Admin\ModuleQuizController;
use App\Http\Controllers\Admin\RegiaController;
use App\Http\Controllers\Admin\ScormPackageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\LiveStreamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin|superadmin'])->group(function () {
    Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
        Route::get('/regia', [RegiaController::class, 'index'])->name('regia.index');
        Route::get('/regia/{module}', [RegiaController::class, 'show'])->name('regia.show');
        Route::post('/regia/{module}/session/start', [LiveStreamController::class, 'adminStartSession'])->name('regia.session.start');
        Route::post('/regia/{module}/session/end', [LiveStreamController::class, 'adminEndSession'])->name('regia.session.end');
        Route::post('/regia/{module}/join', [LiveStreamController::class, 'adminJoin'])->name('regia.join');
        Route::get('/regia/{module}/state', [LiveStreamController::class, 'adminState'])->name('regia.state');
        Route::post('/regia/{module}/presence', [LiveStreamController::class, 'adminPresence'])->name('regia.presence');
        Route::post('/regia/{module}/messages', [LiveStreamController::class, 'storeAdminMessage'])->name('regia.messages.store');
        Route::post('/regia/{module}/polls', [LiveStreamController::class, 'storeAdminPoll'])->name('regia.polls.store');
        Route::patch('/regia/{module}/polls/{poll}/close', [LiveStreamController::class, 'closeAdminPoll'])->name('regia.polls.close');
        Route::post('/regia/{module}/documents', [LiveStreamController::class, 'storeAdminDocument'])->name('regia.documents.store');
        Route::get('/regia/{module}/documents/{document}', [LiveStreamController::class, 'downloadAdminDocument'])->name('regia.documents.download');
        Route::delete('/regia/{module}/documents/{document}', [LiveStreamController::class, 'destroyAdminDocument'])->name('regia.documents.destroy');
        Route::patch('/regia/{module}/participants/{participant}/speaker', [LiveStreamController::class, 'updateAdminSpeaker'])->name('regia.participants.speaker');
        Route::get('/live-stream/{module}/player', [LiveStreamController::class, 'adminPlayer'])->name('live-stream.player');
        Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');
        Route::post('/courses/{course}/modules', [CourseModuleController::class, 'store'])->name('courses.modules.store');
        Route::patch('/courses/{course}/modules/reorder', [CourseModuleController::class, 'reorder'])->name('courses.modules.reorder');
        Route::get('/courses/{course}/modules/{module}/edit', [CourseModuleController::class, 'edit'])->name('courses.modules.edit');
        Route::post('/courses/{course}/modules/{module}/teachers', [CourseModuleController::class, 'assignTeachers'])->name('courses.modules.teachers.assign');
        Route::post('/courses/{course}/modules/{module}/tutors', [CourseModuleController::class, 'assignTutors'])->name('courses.modules.tutors.assign');
        Route::post('/courses/{course}/modules/{module}/attendance/confirm', [CourseModuleController::class, 'confirmAttendance'])->name('courses.modules.attendance.confirm');
        Route::put('/courses/{course}/modules/{module}', [CourseModuleController::class, 'update'])->name('courses.modules.update');
        Route::delete('/courses/{course}/modules/{module}', [CourseModuleController::class, 'destroy'])->name('courses.modules.destroy');
        Route::scopeBindings()->group(function () {
            Route::get('/courses/{course}/modules/{module}/scorm', [ScormPackageController::class, 'index'])->name('courses.modules.scorm.index');
            Route::post('/courses/{course}/modules/{module}/scorm', [ScormPackageController::class, 'store'])->name('courses.modules.scorm.store');
            Route::delete('/courses/{course}/modules/{module}/scorm/{scormPackage}', [ScormPackageController::class, 'destroy'])->name('courses.modules.scorm.destroy');
        });
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');

        // Job Management Routes (require 'manage job data' permission)
        Route::middleware('permission:manage job data')->group(function () {
            Route::resource('job-categories', JobCategoryController::class)->except(['show']);
            Route::resource('job-levels', JobLevelController::class)->except(['show']);
            Route::resource('job-titles', JobTitleController::class)->except(['show']);
            Route::resource('job-roles', JobRoleController::class)->except(['show']);
            Route::resource('job-sectors', JobSectorController::class)->except(['show']);
            Route::resource('job-units', JobUnitController::class)->except(['show']);

            // Restore routes for soft deleted items
            Route::post('job-categories/{id}/restore', [JobCategoryController::class, 'restore'])->name('job-categories.restore');
            Route::post('job-levels/{id}/restore', [JobLevelController::class, 'restore'])->name('job-levels.restore');
            Route::post('job-titles/{id}/restore', [JobTitleController::class, 'restore'])->name('job-titles.restore');
            Route::post('job-roles/{id}/restore', [JobRoleController::class, 'restore'])->name('job-roles.restore');
            Route::post('job-sectors/{id}/restore', [JobSectorController::class, 'restore'])->name('job-sectors.restore');
            Route::post('job-units/{id}/restore', [JobUnitController::class, 'restore'])->name('job-units.restore');
        });

        // Quiz Domande e Risposte
        Route::post('/courses/{course}/modules/{module}/quiz/questions', [ModuleQuizController::class, 'storeQuestion'])->name('courses.modules.quiz.questions.store');
        Route::put('/courses/{course}/modules/{module}/quiz/questions/{question}', [ModuleQuizController::class, 'updateQuestion'])->name('courses.modules.quiz.questions.update');
        Route::delete('/courses/{course}/modules/{module}/quiz/questions/{question}', [ModuleQuizController::class, 'deleteQuestion'])->name('courses.modules.quiz.questions.delete');
        Route::get('/courses/{course}/modules/{module}/quiz/pdf', [ModuleQuizController::class, 'downloadPdf'])->name('courses.modules.quiz.pdf.download');
        Route::post('/courses/{course}/modules/{module}/quiz/questions/{question}/answers', [ModuleQuizController::class, 'storeAnswer'])->name('courses.modules.quiz.answers.store');
        Route::put('/courses/{course}/modules/{module}/quiz/questions/{question}/answers/{answer}', [ModuleQuizController::class, 'updateAnswer'])->name('courses.modules.quiz.answers.update');
        Route::delete('/courses/{course}/modules/{module}/quiz/questions/{question}/answers/{answer}', [ModuleQuizController::class, 'deleteAnswer'])->name('courses.modules.quiz.answers.delete');
        Route::post('/courses/{course}/modules/{module}/quiz/questions/{question}/answers/{answer}/set-correct', [ModuleQuizController::class, 'setCorrectAnswer'])->name('courses.modules.quiz.answers.set-correct');
    });
});
