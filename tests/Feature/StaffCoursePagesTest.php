<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use App\Models\ModuleTutorEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows teacher courses derived from assigned modules', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $this->actingAs($teacher);

    $visibleCourse = Course::factory()->create([
        'title' => 'Corso visibile docente',
    ]);
    $hiddenCourse = Course::factory()->create([
        'title' => 'Corso nascosto docente',
    ]);

    $visibleLiveModule = Module::factory()->create([
        'belongsTo' => (string) $visibleCourse->getKey(),
        'title' => 'Live assegnata',
        'type' => 'live',
    ]);
    $visibleVideoModule = Module::factory()->create([
        'belongsTo' => (string) $visibleCourse->getKey(),
        'title' => 'Video assegnato',
        'type' => 'video',
    ]);
    $hiddenModule = Module::factory()->create([
        'belongsTo' => (string) $hiddenCourse->getKey(),
        'title' => 'Modulo non assegnato',
        'type' => 'video',
    ]);

    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $visibleLiveModule->getKey(),
    ]);
    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $visibleVideoModule->getKey(),
    ]);

    $response = $this->get(route('teacher.courses.index'));

    $response->assertOk();
    $response->assertSeeText('Corso visibile docente');
    $response->assertDontSeeText('Corso nascosto docente');
    $response->assertSeeText('2');

    $detailResponse = $this->get(route('teacher.courses.show', $visibleCourse));

    $detailResponse->assertOk();
    $detailResponse->assertSeeText('Live assegnata');
    $detailResponse->assertSeeText('Video assegnato');
    $detailResponse->assertSeeText('Assegnato il');
    $detailResponse->assertDontSeeText($hiddenModule->title);
});

it('shows tutor courses derived from assigned modules', function () {
    $tutor = User::factory()->create();
    $tutor->assignRole('tutor');
    $this->actingAs($tutor);

    $visibleCourse = Course::factory()->create([
        'title' => 'Corso visibile tutor',
    ]);
    $hiddenCourse = Course::factory()->create([
        'title' => 'Corso nascosto tutor',
    ]);

    $visibleModule = Module::factory()->create([
        'belongsTo' => (string) $visibleCourse->getKey(),
        'title' => 'Modulo tutor assegnato',
        'type' => 'scorm',
    ]);
    $hiddenModule = Module::factory()->create([
        'belongsTo' => (string) $hiddenCourse->getKey(),
        'title' => 'Modulo tutor non assegnato',
        'type' => 'live',
    ]);

    ModuleTutorEnrollment::factory()->create([
        'user_id' => $tutor->getKey(),
        'module_id' => $visibleModule->getKey(),
    ]);

    $response = $this->get(route('tutor.courses.index'));

    $response->assertOk();
    $response->assertSeeText('Corso visibile tutor');
    $response->assertDontSeeText('Corso nascosto tutor');

    $detailResponse = $this->get(route('tutor.courses.show', $visibleCourse));

    $detailResponse->assertOk();
    $detailResponse->assertSeeText('Modulo tutor assegnato');
    $detailResponse->assertSeeText('Assegnato il');
    $detailResponse->assertDontSeeText($hiddenModule->title);
});
