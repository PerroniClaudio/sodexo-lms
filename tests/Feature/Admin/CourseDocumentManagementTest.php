<?php

use App\Models\Course;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows course documents section', function () {
    $course = Course::factory()->create();

    $this->get(route('admin.courses.edit', [$course, 'section' => 'documents']))
        ->assertOk()
        ->assertSeeText('Carica un documento')
        ->assertSeeText('Carica documento')
        ->assertSeeText('Nessun documento caricato.');
});

it('stores course documents on s3', function () {
    Storage::fake('s3');

    $course = Course::factory()->create();

    $this->post(route('admin.courses.documents.store', $course), [
        'file_name' => 'Registro presenze.pdf',
        'file_type' => 'document',
        'category' => 'registers',
        'file' => UploadedFile::fake()->create('registro.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'documents']));

    $document = $course->documents()->first();

    expect($document)->not->toBeNull()
        ->and($document->file_name)->toBe('Registro presenze.pdf')
        ->and($document->file_type)->toBe('document')
        ->and($document->category)->toBe('registers')
        ->and($document->path)->toStartWith('courses/'.$course->getKey().'/documents/');

    Storage::disk('s3')->assertExists($document->path);
});

it('downloads course documents', function () {
    Storage::fake('s3');

    $course = Course::factory()->create();
    $document = $course->documents()->create([
        'file_name' => 'Programma.pdf',
        'file_type' => 'document',
        'category' => 'program',
        'disk' => 's3',
        'path' => 'courses/'.$course->getKey().'/documents/programma.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 9,
    ]);

    Storage::disk('s3')->put($document->path, 'pdf-bytes');

    $this->get(route('admin.courses.documents.download', [$course, $document]))
        ->assertOk()
        ->assertDownload('Programma.pdf');
});

it('deletes course documents and stored files', function () {
    Storage::fake('s3');

    $course = Course::factory()->create();
    $document = $course->documents()->create([
        'file_name' => 'Verbale.pdf',
        'file_type' => 'document',
        'category' => 'verification_reports',
        'disk' => 's3',
        'path' => 'courses/'.$course->getKey().'/documents/verbale.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 9,
    ]);

    Storage::disk('s3')->put($document->path, 'pdf-bytes');

    $this->delete(route('admin.courses.documents.destroy', [$course, $document]))
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'documents']));

    expect($document->fresh())->toBeNull();
    Storage::disk('s3')->assertMissing($document->path);
});
