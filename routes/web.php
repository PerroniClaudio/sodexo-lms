<?php

use App\Models\CourseClassSchedule;
use App\Models\CourseClassTeacher;
use App\Models\Module;
use App\Models\User;
use App\Services\CourseClassScheduleResolver;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('homepage.index');
});

Route::middleware(['auth', 'role:admin|superadmin'])->get('/dashboard', function () {
    return redirect()->route('admin.dashboard');
})->name('dashboard');

Route::middleware('auth')->get('/area-riservata', function (CourseClassScheduleResolver $scheduleResolver) {
    $user = request()->user();

    abort_unless($user instanceof User, 403);

    $teacherLiveAssignments = collect();

    if ($user->hasAnyRole(['teacher', 'docente'])) {
        $teacherLiveAssignments = $user->teachingCourseClassAssignments()
            ->with([
                'courseClass.schedules',
                'courseClass.module.course:id,title',
            ])
            ->whereHas('courseClass.module', function ($query): void {
                $query
                    ->where('type', Module::TYPE_LIVE)
                    ->whereNull('modules.deleted_at');
            })
            ->get()
            ->map(function (CourseClassTeacher $assignment) use ($scheduleResolver, $user): ?object {
                $courseClass = $assignment->courseClass;
                $module = $courseClass?->module;

                if (! $module instanceof Module || $courseClass === null) {
                    return null;
                }

                $schedule = $courseClass->orderedSchedules()
                    ->first(fn (CourseClassSchedule $schedule): bool => $schedule->ends_at === null || $schedule->ends_at->isFuture())
                    ?? $courseClass->resolvedSchedule();

                $startsAt = $schedule?->starts_at ?? $scheduleResolver->effectiveStartsAt($module, $user) ?? $module->appointment_start_time;
                $endsAt = $schedule?->ends_at ?? $scheduleResolver->effectiveEndsAt($module, $user) ?? $module->appointment_end_time;

                if ($endsAt !== null && $endsAt->isPast()) {
                    return null;
                }

                return (object) [
                    'course_title' => $module->course?->title ?? __('Corso non disponibile'),
                    'module_title' => $module->title,
                    'class_name' => $courseClass->name,
                    'debug_live_date' => $startsAt?->format('d/m/Y') ?? __('Non disponibile'),
                    'debug_live_time' => match (true) {
                        $startsAt !== null && $endsAt !== null => $startsAt->format('H:i').' - '.$endsAt->format('H:i'),
                        $startsAt !== null => $startsAt->format('H:i'),
                        default => __('Orario non disponibile'),
                    },
                    'debug_sort_timestamp' => $startsAt?->timestamp ?? PHP_INT_MAX,
                    'access_url' => route('teacher.live-stream.player', $module),
                ];
            })
            ->filter()
            ->sortBy('debug_sort_timestamp')
            ->values();
    }

    return view('reserved-area', [
        'teacherLiveAssignments' => $teacherLiveAssignments,
    ]);
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
