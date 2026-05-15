<?php

use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CourseEnrollmentController;
use App\Http\Controllers\Admin\CourseModuleController;
use App\Http\Controllers\Admin\CustomCertificateController;
use App\Http\Controllers\Admin\DocumentConversionJobDebugController;
use App\Http\Controllers\Admin\HomepageCustomizationController;
use App\Http\Controllers\Admin\JobCategoryController;
use App\Http\Controllers\Admin\JobLevelController;
use App\Http\Controllers\Admin\JobRoleController;
use App\Http\Controllers\Admin\JobSectorController;
use App\Http\Controllers\Admin\JobTitleController;
use App\Http\Controllers\Admin\JobUnitController;
use App\Http\Controllers\Admin\ModuleQuizController;
use App\Http\Controllers\Admin\ModuleQuizDocumentUploadController;
use App\Http\Controllers\Admin\ModuleQuizSubmissionController;
use App\Http\Controllers\Admin\RegiaController;
use App\Http\Controllers\Admin\SatisfactionSurveyController;
use App\Http\Controllers\Admin\ScormPackageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\LiveStreamController;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin|superadmin'])->group(function () {
    Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
        // Libreria video Mux
        Route::post('videos', [VideoController::class, 'store'])
            ->middleware('uploadlimit')
            ->name('videos.store');
        Route::get('videos', [VideoController::class, 'index'])->name('videos.index');
        Route::get('videos/create', [VideoController::class, 'create'])->name('videos.create');
        Route::get('videos/{video}/edit', [VideoController::class, 'edit'])->name('videos.edit');
        Route::put('videos/{video}', [VideoController::class, 'update'])->name('videos.update');
        Route::delete('videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');
        Route::post('videos/{video}/restore', [VideoController::class, 'restore'])->name('videos.restore');
        Route::get('videos/{video}/signed-playback', [VideoController::class, 'signedPlayback'])->name('videos.signed-playback');
        Route::get('videos/{video}/signed-thumbnail', [VideoController::class, 'signedThumbnail'])->name('videos.signed-thumbnail');
        Route::get('videos/{video}/signed-playback-url', [VideoController::class, 'signedPlaybackApi']);
        Route::get('/homepage', [HomepageCustomizationController::class, 'index'])->name('homepage.index');
        Route::post('/homepage/navigation', [HomepageCustomizationController::class, 'updateNavigation'])->name('homepage.navigation.update');
        Route::post('/homepage/hero', [HomepageCustomizationController::class, 'updateHero'])->name('homepage.hero.update');
        Route::post('/homepage/services', [HomepageCustomizationController::class, 'updateServices'])->name('homepage.services.update');
        Route::post('/homepage/about', [HomepageCustomizationController::class, 'updateAbout'])->name('homepage.about.update');
        Route::get('/certificates', [CustomCertificateController::class, 'index'])->name('certificates.index');
        Route::get('/certificates/create', [CustomCertificateController::class, 'create'])->name('certificates.create');
        Route::post('/certificates', [CustomCertificateController::class, 'store'])->name('certificates.store');
        Route::get('/certificates/{customCertificate}/edit', [CustomCertificateController::class, 'edit'])->name('certificates.edit');
        Route::put('/certificates/{customCertificate}', [CustomCertificateController::class, 'update'])->name('certificates.update');
        Route::post('/certificates/{customCertificate}/restore-version', [CustomCertificateController::class, 'restoreVersion'])->name('certificates.restore-version');
        Route::get('/certificates/{customCertificate}/preview', [CustomCertificateController::class, 'preview'])->name('certificates.preview');
        Route::post('/certificates/{customCertificate}/preview-download', [CustomCertificateController::class, 'previewDownload'])->name('certificates.preview-download');

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
        Route::delete('/courses/{course}/modules/{module}/teachers/{teacherEnrollment}', [CourseModuleController::class, 'unassignTeacher'])->name('courses.modules.teachers.destroy');
        Route::delete('/courses/{course}/modules/{module}/tutors/{tutorEnrollment}', [CourseModuleController::class, 'unassignTutor'])->name('courses.modules.tutors.destroy');
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

        Route::middleware('role:superadmin')->group(function () {
            Route::get('/document-conversion-jobs', [DocumentConversionJobDebugController::class, 'index'])
                ->name('document-conversion-jobs.index');
            Route::post('/document-conversion-jobs/{documentConversionJob}/retry', [DocumentConversionJobDebugController::class, 'retry'])
                ->name('document-conversion-jobs.retry');
            Route::get('/document-conversion-jobs/{documentConversionJob}/download', [DocumentConversionJobDebugController::class, 'download'])
                ->name('document-conversion-jobs.download');
            Route::get('/satisfaction-survey', [SatisfactionSurveyController::class, 'edit'])
                ->name('satisfaction-survey.edit');
            Route::put('/satisfaction-survey', [SatisfactionSurveyController::class, 'update'])
                ->name('satisfaction-survey.update');
        });

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
        Route::get('/courses/{course}/modules/{module}/quiz/answer-sheet-pdf', [ModuleQuizController::class, 'downloadAnswerSheetPdf'])->name('courses.modules.quiz.answer-sheet.pdf.download');
        Route::post('/courses/{course}/modules/{module}/quiz/questions/{question}/answers', [ModuleQuizController::class, 'storeAnswer'])->name('courses.modules.quiz.answers.store');
        Route::put('/courses/{course}/modules/{module}/quiz/questions/{question}/answers/{answer}', [ModuleQuizController::class, 'updateAnswer'])->name('courses.modules.quiz.answers.update');
        Route::delete('/courses/{course}/modules/{module}/quiz/questions/{question}/answers/{answer}', [ModuleQuizController::class, 'deleteAnswer'])->name('courses.modules.quiz.answers.delete');
        Route::post('/courses/{course}/modules/{module}/quiz/questions/{question}/answers/{answer}/set-correct', [ModuleQuizController::class, 'setCorrectAnswer'])->name('courses.modules.quiz.answers.set-correct');

        Route::scopeBindings()->group(function () {
            // Document uploads
            Route::post('/courses/{course}/modules/{module}/quiz/document-uploads', [ModuleQuizDocumentUploadController::class, 'store'])->name('courses.modules.quiz.document-uploads.store');
            Route::get('/courses/{course}/modules/{module}/quiz/document-uploads', [ModuleQuizDocumentUploadController::class, 'index'])->name('courses.modules.quiz.document-uploads.index');
            Route::get('/courses/{course}/modules/{module}/quiz/document-uploads/{documentUpload}', [ModuleQuizDocumentUploadController::class, 'show'])->name('courses.modules.quiz.document-uploads.show');

            // Submissions
            Route::get('/courses/{course}/modules/{module}/quiz/submissions', [ModuleQuizSubmissionController::class, 'index'])->name('courses.modules.quiz.submissions.index');
            Route::get('/courses/{course}/modules/{module}/quiz/submissions/{submission}', [ModuleQuizSubmissionController::class, 'show'])->name('courses.modules.quiz.submissions.show');
            Route::get('/courses/{course}/modules/{module}/quiz/submissions/{submission}/review', [ModuleQuizSubmissionController::class, 'review'])->name('courses.modules.quiz.submissions.review');
            Route::post('/courses/{course}/modules/{module}/quiz/submissions/{submission}/finalize', [ModuleQuizSubmissionController::class, 'finalize'])->name('courses.modules.quiz.submissions.finalize');
        });

        // API (risposte json)
        Route::group(['prefix' => 'api', 'as' => 'api.'], function () {
            Route::get('/courses/{course}/enrollments', [CourseEnrollmentController::class, 'indexApi'])->name('courses.enrollments.index');
            Route::get('/courses/{course}/enrollments/search-users', [CourseEnrollmentController::class, 'searchUsersApi'])->name('courses.enrollments.search-users');
            Route::post('/courses/{course}/enrollments', [CourseEnrollmentController::class, 'storeApi'])->name('courses.enrollments.store');
            Route::post('/courses/{course}/enrollments/{enrollment}/restore', [CourseEnrollmentController::class, 'restoreApi'])->name('courses.enrollments.restore');
            Route::delete('/courses/{course}/enrollments/{enrollment}', [CourseEnrollmentController::class, 'destroyApi'])->name('courses.enrollments.destroy');

            // Lista domande e risposte
            Route::get('/courses/{course}/modules/{module}/quiz/questions', [ModuleQuizController::class, 'questionsWithAnswersApi'])->name('courses.modules.quiz.questions.index');
            Route::post('/courses/{course}/modules/{module}/quiz/questions', [ModuleQuizController::class, 'storeQuestionApi'])->name('courses.modules.quiz.questions.store');
            Route::put('/courses/{course}/modules/{module}/quiz/questions/{question}', [ModuleQuizController::class, 'updateQuestionApi'])->name('courses.modules.quiz.questions.update');
            Route::delete('/courses/{course}/modules/{module}/quiz/questions/{question}', [ModuleQuizController::class, 'deleteQuestionApi'])->name('courses.modules.quiz.questions.delete');
            Route::post('/courses/{course}/modules/{module}/quiz/questions/{question}/answers', [ModuleQuizController::class, 'storeAnswerApi'])->name('courses.modules.quiz.answers.store');
            Route::put('/courses/{course}/modules/{module}/quiz/questions/{question}/answers/{answer}', [ModuleQuizController::class, 'updateAnswerApi'])->name('courses.modules.quiz.answers.update');
            Route::delete('/courses/{course}/modules/{module}/quiz/questions/{question}/answers/{answer}', [ModuleQuizController::class, 'deleteAnswerApi'])->name('courses.modules.quiz.answers.delete');
            Route::post('/courses/{course}/modules/{module}/quiz/questions/{question}/answers/{answer}/set-correct', [ModuleQuizController::class, 'setCorrectAnswerApi'])->name('courses.modules.quiz.answers.set-correct');
            Route::get('/courses/{course}/modules/{module}/max-score', function (Course $course, Module $module) {
                abort_unless($module->belongsTo === (string) $course->getKey(), 404);

                return response()->json(['max_score' => $module->max_score]);
            })->name('courses.modules.max_score');

            // API per validità quiz modulo
            Route::get('/courses/{course}/modules/{module}/quiz/validity', function (Course $course, Module $module) {
                abort_unless($module->belongsTo === (string) $course->getKey(), 404);

                return response()->json([
                    'is_valid_quiz' => $module->isValidQuiz(),
                ]);
            })->name('courses.modules.quiz_validity');

            // API video per selezione/assegnazione nei moduli (usata in video-table)
            Route::get('videos', [VideoController::class, 'indexApi'])->name('videos.index');
            Route::get('videos/{video}', [VideoController::class, 'getInfoApi'])->name('videos.info');
            // Assegnazione video a modulo
            Route::post('/modules/{module}/assign-video', [CourseModuleController::class, 'assignVideoToModule']);
            Route::post('/modules/{module}/unassign-video', [CourseModuleController::class, 'unassignVideoFromModule']);
            Route::get('/modules/{module}/validity', [CourseModuleController::class, 'getModuleValidity']);
        });
    });

});
