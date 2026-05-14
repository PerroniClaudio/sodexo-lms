<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use App\Models\ModuleTutorEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function liveStreamModule(): Module
{
    $course = Course::factory()->create();

    return Module::factory()->create([
        'type' => 'live',
        'title' => 'Modulo live di test',
        'description' => 'Descrizione live di test',
        'belongsTo' => (string) $course->getKey(),
    ]);
}

it('redirects guests away from protected role routes', function () {
    $module = liveStreamModule();

    $this->get(route('admin.courses.index'))->assertRedirect(route('login'));
    $this->get(route('teacher.live-stream.player', $module))->assertRedirect(route('login'));
    $this->get(route('tutor.live-stream.player', $module))->assertRedirect(route('login'));
    $this->get(route('user.live-stream.player', $module))->assertRedirect(route('login'));
});

it('forbids access to admin routes for non admin roles', function () {
    actingAsRole('user');

    $this->get(route('admin.courses.index'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('allows admins to access admin routes', function () {
    actingAsRole('admin');

    $this->get(route('admin.courses.index'))->assertOk();
});

it('redirects admins to dashboard with an error when they access another role area', function () {
    actingAsRole('admin');
    $module = liveStreamModule();

    $this->get(route('teacher.live-stream.player', $module))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('forbids access to teacher routes for non teacher roles', function () {
    actingAsRole('user');
    $module = liveStreamModule();

    $this->get(route('teacher.live-stream.player', $module))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('allows teachers to access teacher routes', function () {
    $teacher = actingAsRole('teacher');
    $module = liveStreamModule();
    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $module->getKey(),
    ]);

    $this->actingAs($teacher)->get(route('teacher.live-stream.player', $module))->assertOk();
});

it('forbids teacher routes for teachers not assigned to the course', function () {
    $teacher = actingAsRole('teacher');
    $module = liveStreamModule();

    $this->actingAs($teacher)
        ->get(route('teacher.live-stream.player', $module))
        ->assertForbidden();
});

it('forbids access to tutor routes for non tutor roles', function () {
    actingAsRole('user');
    $module = liveStreamModule();

    $this->get(route('tutor.live-stream.player', $module))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('allows tutors to access tutor routes', function () {
    $tutor = actingAsRole('tutor');
    $module = liveStreamModule();
    ModuleTutorEnrollment::factory()->create([
        'user_id' => $tutor->getKey(),
        'module_id' => $module->getKey(),
    ]);

    $this->actingAs($tutor)->get(route('tutor.live-stream.player', $module))->assertOk();
});

it('forbids tutor routes for tutors not assigned to the course', function () {
    $tutor = actingAsRole('tutor');
    $module = liveStreamModule();

    $this->actingAs($tutor)
        ->get(route('tutor.live-stream.player', $module))
        ->assertForbidden();
});

it('forbids access to user routes for non user roles', function () {
    actingAsRole('tutor');
    $module = liveStreamModule();

    $this->get(route('user.live-stream.player', $module))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('allows users to access user routes', function () {
    $user = actingAsRole('user');
    $module = liveStreamModule();

    CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => (int) $module->belongsTo,
    ]);

    $this->actingAs($user)->get(route('user.live-stream.player', $module))->assertOk();
});
