<?php

use App\Models\Course;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows attachments section on course edit page', function () {
    $course = Course::factory()->create();

    $this->get(route('admin.courses.edit', [$course, 'section' => 'attachments']))
        ->assertOk()
        ->assertSeeText('Allegati')
        ->assertSeeText('Immagine di copertina')
        ->assertSeeText('Locandina PDF');
});

it('stores course attachments on s3', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $course = Course::factory()->create([
        'title' => 'Corso allegati',
        'description' => 'Descrizione',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
    ]);

    $response = $this->put(route('admin.courses.attachments.update', $course), [
        'title' => 'Corso allegati',
        'code' => $course->code,
        'description' => 'Descrizione',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
        'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        'poster_pdf' => UploadedFile::fake()->create('poster.pdf', 200, 'application/pdf'),
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'attachments']));

    $course->refresh();

    expect($course->cover_image_path)->toStartWith('courses/'.$course->getKey().'/attachments/')
        ->and($course->poster_pdf_path)->toStartWith('courses/'.$course->getKey().'/attachments/');

    Storage::disk('s3')->assertExists($course->cover_image_path);
    Storage::disk('s3')->assertExists($course->poster_pdf_path);
});

it('replaces old course attachments and deletes previous files', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $course = Course::factory()->create([
        'title' => 'Corso allegati',
        'description' => 'Descrizione',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
        'cover_image_path' => 'courses/1/attachments/old-cover.jpg',
        'poster_pdf_path' => 'courses/1/attachments/old-poster.pdf',
    ]);

    Storage::disk('s3')->put($course->cover_image_path, 'old-cover');
    Storage::disk('s3')->put($course->poster_pdf_path, 'old-poster');

    $oldCoverPath = $course->cover_image_path;
    $oldPosterPath = $course->poster_pdf_path;

    $this->put(route('admin.courses.attachments.update', $course), [
        'title' => 'Corso allegati',
        'code' => $course->code,
        'description' => 'Descrizione',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
        'cover_image' => UploadedFile::fake()->image('cover-new.jpg'),
        'poster_pdf' => UploadedFile::fake()->create('poster-new.pdf', 220, 'application/pdf'),
    ])->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'attachments']));

    $course->refresh();

    Storage::disk('s3')->assertMissing($oldCoverPath);
    Storage::disk('s3')->assertMissing($oldPosterPath);
    Storage::disk('s3')->assertExists($course->cover_image_path);
    Storage::disk('s3')->assertExists($course->poster_pdf_path);
});

it('validates course attachment types', function () {
    $course = Course::factory()->create([
        'title' => 'Corso allegati',
        'description' => 'Descrizione',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
    ]);

    $this->from(route('admin.courses.edit', [$course, 'section' => 'attachments']))
        ->put(route('admin.courses.attachments.update', $course), [
            'title' => 'Corso allegati',
            'code' => $course->code,
            'description' => 'Descrizione',
            'year' => 2026,
            'expiry_date' => '2026-12-31',
            'status' => 'draft',
            'cover_image' => UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf'),
            'poster_pdf' => UploadedFile::fake()->image('poster.jpg'),
        ])
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'attachments']))
        ->assertSessionHasErrors(['cover_image', 'poster_pdf']);
});

it('previews stored attachments for admins', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $course = Course::factory()->create([
        'cover_image_path' => 'courses/10/attachments/cover-image.jpg',
        'poster_pdf_path' => 'courses/10/attachments/poster.pdf',
    ]);

    Storage::disk('s3')->put($course->cover_image_path, 'cover-bytes');
    Storage::disk('s3')->put($course->poster_pdf_path, 'pdf-bytes');

    $this->get(route('admin.courses.attachments.cover-image.preview', $course))
        ->assertOk();

    $this->get(route('admin.courses.attachments.poster-pdf.preview', $course))
        ->assertOk();
});
