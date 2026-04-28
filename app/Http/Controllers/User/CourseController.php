<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
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
        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->firstOrFail();
        $modules = $course->modules()->with(['progressRecords' => function ($q) use ($enrollment) {
            $q->where('course_user_id', $enrollment->id);
        }])->get();
        // Associa lo stato del modulo all'enrollment
        foreach ($modules as $module) {
            $progress = $module->progressRecords->first();
            $module->pivot = (object) [
                'status' => $progress?->status ?? 'locked',
            ];
        }
        return view('user.courses.show', compact('course', 'enrollment', 'modules'));
    }
}
