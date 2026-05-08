<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $enrollments = $user->courseEnrollments()->with('course')->get();

        return view('user.courses.index', compact('enrollments'));
    }

    public function show(Course $course): View
    {
        $user = Auth::user();
        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();
        abort_unless($enrollment !== null, 403);
        $modules = $course->modules()->with(['progressRecords' => function ($q) use ($enrollment) {
            $q->where('course_user_id', $enrollment->id);
        }])->get();

        foreach ($modules as $module) {
            $progress = $module->progressRecords->first();
            $module->pivot = (object) [
                'status' => $progress?->status ?? 'locked',
                'quiz_attempts' => $progress?->quiz_attempts ?? 0,
            ];
        }

        return view('user.courses.show', compact('course', 'enrollment', 'modules'));
    }

    public function showModule(Course $course, Module $module): View
    {
        $user = Auth::user();

        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);

        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();
        abort_unless($enrollment !== null, 403);

        $module->loadMissing('video');

        $progress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->first();
        abort_unless($progress !== null, 404);

        // Permetti accesso solo a moduli in corso o completati (non locked)
        abort_if($progress->status === 'locked', 403);

        // Ottieni il modulo successivo
        $nextModule = $course->modules()
            ->where('order', '>', $module->order)
            ->orderBy('order')
            ->first();

        return view('user.courses.module', compact('course', 'module', 'enrollment', 'progress', 'nextModule'));
    }
}
