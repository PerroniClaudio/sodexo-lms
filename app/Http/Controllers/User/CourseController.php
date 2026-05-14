<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(): View
    {
        $user = $this->authUser();

        return match ($this->routeArea()) {
            'teacher' => $this->teacherIndex($user),
            'tutor' => $this->tutorIndex($user),
            default => $this->userIndex($user),
        };
    }

    public function show(Course $course): View
    {
        $user = $this->authUser();

        return match ($this->routeArea()) {
            'teacher' => $this->teacherShow($user, $course),
            'tutor' => $this->tutorShow($user, $course),
            default => $this->userShow($user, $course),
        };
    }

    public function showModule(Course $course, Module $module): View
    {
        $user = $this->authUser();

        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);

        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();
        abort_unless($enrollment !== null, 403);

        $module->loadMissing('video');

        $progress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->first();
        abort_unless($progress !== null, 404);

        abort_if($progress->status === 'locked', 403);

        $nextModule = $course->modules()
            ->where('order', '>', $module->order)
            ->orderBy('order')
            ->first();

        return view('user.courses.module', compact('course', 'module', 'enrollment', 'progress', 'nextModule'));
    }

    private function userIndex(User $user): View
    {
        $enrollments = $user->courseEnrollments()->with('course')->get();

        return view('user.courses.index', compact('enrollments'));
    }

    private function teacherIndex(User $user): View
    {
        $courses = $user->getTeachingCourses();

        return view('teacher.courses.index', compact('courses'));
    }

    private function tutorIndex(User $user): View
    {
        $courses = $user->getTutoringCourses();

        return view('tutor.courses.index', compact('courses'));
    }

    private function userShow(User $user, Course $course): View
    {
        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();
        abort_unless($enrollment !== null, 403);

        $modules = $course->modules()->with(['progressRecords' => function ($query) use ($enrollment) {
            $query->where('course_user_id', $enrollment->id);
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

    private function teacherShow(User $user, Course $course): View
    {
        $assignedCourse = $user->getTeachingCoursesQuery()
            ->whereKey($course->getKey())
            ->first();

        abort_unless($assignedCourse !== null, 403);

        $assignedModules = $user->teachingModules()
            ->where('belongsTo', (string) $course->getKey())
            ->whereNull('modules.deleted_at')
            ->orderBy('order')
            ->get();
        $modules = $course->modules()->get();

        $this->normalizeAssignedModuleDates($assignedModules);
        $this->decorateStaffCourseModules($modules, $assignedModules);

        return view('teacher.courses.show', compact('course', 'assignedModules', 'modules'));
    }

    private function tutorShow(User $user, Course $course): View
    {
        $assignedCourse = $user->getTutoringCoursesQuery()
            ->whereKey($course->getKey())
            ->first();

        abort_unless($assignedCourse !== null, 403);

        $assignedModules = $user->tutoringModules()
            ->where('belongsTo', (string) $course->getKey())
            ->whereNull('modules.deleted_at')
            ->orderBy('order')
            ->get();
        $modules = $course->modules()->get();

        $this->normalizeAssignedModuleDates($assignedModules);
        $this->decorateStaffCourseModules($modules, $assignedModules);

        return view('tutor.courses.show', compact('course', 'assignedModules', 'modules'));
    }

    private function normalizeAssignedModuleDates($assignedModules): void
    {
        $assignedModules->each(function (Module $module): void {
            $assignedAt = $module->pivot->assigned_at ?? null;
            $module->assigned_at_display = filled($assignedAt)
                ? CarbonImmutable::parse($assignedAt)->format('d/m/Y H:i')
                : null;
        });
    }

    private function decorateStaffCourseModules(EloquentCollection $modules, EloquentCollection $assignedModules): void
    {
        $assignedModulesById = $assignedModules->keyBy(fn (Module $module): int => (int) $module->getKey());

        $modules->each(function (Module $module) use ($assignedModulesById): void {
            /** @var Module|null $assignedModule */
            $assignedModule = $assignedModulesById->get((int) $module->getKey());

            $module->is_assigned_to_staff = $assignedModule !== null;
            $module->assigned_at_display = $assignedModule?->assigned_at_display;
        });
    }

    private function authUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function routeArea(): string
    {
        $routeName = request()->route()?->getName() ?? '';

        if (str_starts_with($routeName, 'teacher.')) {
            return 'teacher';
        }

        if (str_starts_with($routeName, 'tutor.')) {
            return 'tutor';
        }

        return 'user';
    }
}
