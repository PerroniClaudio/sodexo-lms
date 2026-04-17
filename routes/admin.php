<?php

use App\Http\Controllers\Admin\CourseModuleController;
use App\Http\Controllers\Admin\JobCategoryController;
use App\Http\Controllers\Admin\JobLevelController;
use App\Http\Controllers\Admin\JobRoleController;
use App\Http\Controllers\Admin\JobSectorController;
use App\Http\Controllers\Admin\JobTitleController;
use App\Http\Controllers\CourseController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');
    Route::post('/courses/{course}/modules', [CourseModuleController::class, 'store'])->name('courses.modules.store');
    Route::patch('/courses/{course}/modules/reorder', [CourseModuleController::class, 'reorder'])->name('courses.modules.reorder');
    Route::get('/courses/{course}/modules/{module}/edit', [CourseModuleController::class, 'edit'])->name('courses.modules.edit');
    Route::put('/courses/{course}/modules/{module}', [CourseModuleController::class, 'update'])->name('courses.modules.update');
    Route::delete('/courses/{course}/modules/{module}', [CourseModuleController::class, 'destroy'])->name('courses.modules.destroy');
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');

    // Job Management Routes (require 'manage job data' permission)
    Route::middleware('permission:manage job data')->group(function () {
        Route::resource('job-categories', JobCategoryController::class)->except(['show']);
        Route::resource('job-levels', JobLevelController::class)->except(['show']);
        Route::resource('job-titles', JobTitleController::class)->except(['show']);
        Route::resource('job-roles', JobRoleController::class)->except(['show']);
        Route::resource('job-sectors', JobSectorController::class)->except(['show']);
    });
});
