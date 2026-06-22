<?php

use App\Models\Course;
use App\Models\JobRole;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\TrainingPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows the training paths index page', function () {
    TrainingPath::factory()->count(3)->create();

    $this->get(route('admin.training-paths.index'))
        ->assertOk()
        ->assertSeeText('Percorsi formativi')
        ->assertSeeText('Titolo del percorso')
        ->assertSeeText('Codice')
        ->assertSeeText('Stato')
        ->assertSee(route('admin.training-paths.create'), escape: false);
});

it('renders the main training path edit sections', function () {
    $trainingPath = TrainingPath::factory()->create();

    $this->get(route('admin.training-paths.edit', [$trainingPath, 'section' => 'details']))
        ->assertOk()
        ->assertSeeText('Dati anagrafici percorso');

    $this->get(route('admin.training-paths.edit', [$trainingPath, 'section' => 'documents']))
        ->assertOk()
        ->assertSeeText('Documenti');

    $this->get(route('admin.training-paths.edit', [$trainingPath, 'section' => 'courses']))
        ->assertOk()
        ->assertSeeText('Corsi associati');

    $this->get(route('admin.training-paths.edit', [$trainingPath, 'section' => 'recipients']))
        ->assertOk()
        ->assertSeeText('Destinatari');

    $this->get(route('admin.training-paths.edit', [$trainingPath, 'section' => 'enrollments']))
        ->assertOk()
        ->assertSeeText('Iscritti');
});

it('stores a training path and generates code when omitted', function () {
    $response = $this->post(route('admin.training-paths.store'), [
        'title' => 'Percorso sicurezza',
        'code' => '',
    ]);

    $trainingPath = TrainingPath::query()->firstOrFail();

    $response->assertRedirect(route('admin.training-paths.edit', $trainingPath));

    expect($trainingPath->title)->toBe('Percorso sicurezza')
        ->and($trainingPath->status)->toBe('draft')
        ->and($trainingPath->code)->toBe('PATH-'.$trainingPath->getKey());
});

it('updates training path details, recipients and associated courses', function () {
    $trainingPath = TrainingPath::factory()->create([
        'status' => 'draft',
    ]);
    $courseA = Course::factory()->create(['title' => 'Corso A']);
    $courseB = Course::factory()->create(['title' => 'Corso B']);
    $jobRole = JobRole::factory()->create(['name' => 'Cuoco']);
    $jobTask = JobTask::factory()->create(['name' => 'Preparazione']);
    $jobUnit = JobUnit::factory()->create(['name' => 'Milano']);

    $this->put(route('admin.training-paths.details.update', $trainingPath), [
        'title' => 'Percorso aggiornato',
        'code' => 'PATH-001',
        'description' => 'Descrizione aggiornata',
        'status' => 'published',
    ])->assertRedirect(route('admin.training-paths.edit', [$trainingPath, 'section' => 'details']));

    $this->put(route('admin.training-paths.courses.update', $trainingPath), [
        'course_ids' => [$courseA->getKey(), $courseB->getKey()],
        'course_orders' => [
            $courseA->getKey() => 2,
            $courseB->getKey() => 1,
        ],
    ])->assertRedirect(route('admin.training-paths.edit', [$trainingPath, 'section' => 'courses']));

    $this->put(route('admin.training-paths.recipients.update', $trainingPath), [
        'job_role_ids' => [$jobRole->getKey()],
        'job_task_ids' => [$jobTask->getKey()],
        'job_unit_ids' => [$jobUnit->getKey()],
    ])->assertRedirect(route('admin.training-paths.edit', [$trainingPath, 'section' => 'recipients']));

    $trainingPath->refresh();

    expect($trainingPath->title)->toBe('Percorso aggiornato')
        ->and($trainingPath->code)->toBe('PATH-001')
        ->and($trainingPath->description)->toBe('Descrizione aggiornata')
        ->and($trainingPath->status)->toBe('published')
        ->and($trainingPath->visible_to_all)->toBeFalse()
        ->and($trainingPath->courses->pluck('id')->all())->toBe([$courseB->getKey(), $courseA->getKey()])
        ->and($trainingPath->jobRoles()->whereKey($jobRole->getKey())->exists())->toBeTrue()
        ->and($trainingPath->jobTasks()->whereKey($jobTask->getKey())->exists())->toBeTrue()
        ->and($trainingPath->jobUnits()->whereKey($jobUnit->getKey())->exists())->toBeTrue();
});

it('stores downloads and deletes training path documents', function () {
    Storage::fake('s3');

    $trainingPath = TrainingPath::factory()->create();

    $this->post(route('admin.training-paths.documents.store', $trainingPath), [
        'file_name' => 'Programma.pdf',
        'file_type' => 'document',
        'category' => 'program',
        'file' => UploadedFile::fake()->create('programma.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('admin.training-paths.edit', [$trainingPath, 'section' => 'documents']));

    $document = $trainingPath->documents()->firstOrFail();

    Storage::disk('s3')->assertExists($document->path);

    $this->get(route('admin.training-paths.documents.download', [$trainingPath, $document]))
        ->assertOk()
        ->assertDownload('Programma.pdf');

    $this->delete(route('admin.training-paths.documents.destroy', [$trainingPath, $document]))
        ->assertRedirect(route('admin.training-paths.edit', [$trainingPath, 'section' => 'documents']));

    expect($document->fresh())->toBeNull();
    Storage::disk('s3')->assertMissing($document->path);
});
