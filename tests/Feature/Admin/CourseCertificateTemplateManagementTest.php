<?php

use App\Models\Course;
use App\Models\CustomCertificate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows the certificate templates section on the course edit page', function () {
    $course = Course::factory()->create();

    $this->get(route('admin.courses.edit', ['course' => $course, 'section' => 'certificate-templates']))
        ->assertOk()
        ->assertSeeText('Template attestati del corso')
        ->assertSeeText('Partecipazione')
        ->assertSeeText('Superamento');
});

it('stores a course-specific certificate template from the course edit page', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $course = Course::factory()->create();

    $response = $this->put(route('admin.courses.certificate-templates.update', $course), [
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'template' => docxUpload([
            'word/document.xml' => '<w:t>${TITOLO}</w:t>',
        ]),
    ]);

    $response
        ->assertRedirect(route('admin.courses.edit', ['course' => $course, 'section' => 'certificate-templates']))
        ->assertSessionHas('status', 'Template attestato aggiornato con successo.');

    $certificate = CustomCertificate::query()->sole();

    expect($certificate->type)->toBe(CustomCertificate::TYPE_PARTICIPATION)
        ->and($certificate->is_active)->toBeTrue()
        ->and($certificate->course_ids)->toBe([$course->getKey()])
        ->and($certificate->template_path)->toStartWith('custom-certificates/participation/');

    Storage::disk('s3')->assertExists($certificate->template_path);
});

it('validates that course certificate uploads are docx files', function () {
    $course = Course::factory()->create();

    $this->from(route('admin.courses.edit', ['course' => $course, 'section' => 'certificate-templates']))
        ->put(route('admin.courses.certificate-templates.update', $course), [
            'type' => CustomCertificate::TYPE_COMPLETION,
            'template' => UploadedFile::fake()->create('template.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect(route('admin.courses.edit', ['course' => $course, 'section' => 'certificate-templates']))
        ->assertSessionHasErrors(['template']);
});
