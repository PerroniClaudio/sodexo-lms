<?php

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\JobRole;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\Partner;
use App\Models\RiskBasedRequirement;
use App\Models\TrainingPath;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

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
        ->assertSeeText('Documenti')
        ->assertSee(route('admin.training-paths.program.download', $trainingPath), escape: false)
        ->assertSeeText('Scarica programma formativo');

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
    $courseA = Course::factory()->create(['title' => 'Corso A', 'status' => 'published']);
    $courseB = Course::factory()->create(['title' => 'Corso B', 'status' => 'published']);
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

it('downloads the training path program pdf with course details', function () {
    Pdf::fake();

    $trainingPath = TrainingPath::factory()->create([
        'title' => 'Percorso Sicurezza',
        'code' => 'PATH-001',
        'description' => 'Programma completo del percorso.',
        'status' => 'published',
    ]);
    $course = Course::factory()->create([
        'title' => 'Corso Rischio Alto',
        'code' => 'CRS-100',
        'description' => 'Dettaglio corso rischio alto',
        'type' => 'res',
        'status' => 'published',
        'year' => 2026,
        'course_duration_hours' => 8,
        'event_type' => 'formazione obbligatoria',
    ]);
    $trainingPath->courses()->attach($course->getKey(), ['sort_order' => 1]);

    $category = CourseCategory::factory()->create(['name' => 'Sicurezza']);
    $partner = Partner::factory()->create(['ragione_sociale' => 'Partner Demo']);
    $requirement = RiskBasedRequirement::factory()->create(['name' => 'Abilitazione Rischio Alto']);
    $teacher = User::factory()->create(['name' => 'Mario', 'surname' => 'Rossi']);
    $tutor = User::factory()->create(['name' => 'Giulia', 'surname' => 'Bianchi']);

    $course->categories()->attach($category->getKey());
    $course->partners()->attach($partner->getKey());
    $course->riskBasedRequirements()->attach($requirement->getKey(), [
        'course_validity_types' => json_encode(['first_achievement', 'refresh']),
        'integrative_start_risk_levels' => null,
    ]);
    CourseTeacherEnrollment::enroll($teacher, $course);
    CourseTutorEnrollment::enroll($tutor, $course);

    $response = $this->get(route('admin.training-paths.program.download', $trainingPath));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($trainingPath): bool {
        $html = $pdf->getHtml();

        expect($pdf->viewName)->toBe('pdf.training-path-program');
        expect($pdf->viewData['trainingPath']->is($trainingPath))->toBeTrue();
        expect($pdf->downloadName)->toBe('percorso-sicurezza-programma-formativo.pdf');
        expect($html)->toContain('Percorso Sicurezza');
        expect($html)->toContain('PATH-001');
        expect($html)->toContain('Corso Rischio Alto');
        expect($html)->toContain('CRS-100');
        expect($html)->toContain('8 ore');
        expect($html)->toContain('Abilitazione Rischio Alto');
        expect($html)->toContain('Sicurezza');
        expect($html)->toContain('Partner Demo');
        expect($html)->toContain('Rossi Mario');
        expect($html)->toContain('Bianchi Giulia');
        expect($html)->toContain('Primo conseguimento');
        expect($html)->toContain('Aggiornamento');

        return true;
    });
});
