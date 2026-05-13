<?php

use App\Models\Course;
use App\Models\CustomCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('allows admins to access the certificates index', function () {
    actingAsRole('admin');

    $this->get(route('admin.certificates.index'))
        ->assertOk()
        ->assertSeeText('Attestati');
});

it('does not allow regular users to access the certificates section', function () {
    actingAsRole('user');

    $this->get(route('admin.certificates.index'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('stores a certificate template upload', function () {
    Storage::fake('s3');
    actingAsRole('admin');

    $response = $this->post(route('admin.certificates.store'), [
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'template' => docxUpload([
            'word/document.xml' => '<w:t>${TITOLO}</w:t>',
        ]),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Template attestato creato con successo.');

    $certificate = CustomCertificate::query()->sole();

    expect($certificate->type)->toBe(CustomCertificate::TYPE_PARTICIPATION)
        ->and($certificate->is_active)->toBeTrue()
        ->and($certificate->name)->toStartWith('Partecipazione ')
        ->and($certificate->template_path)->toStartWith('custom-certificates/participation/');

    expect($certificate->storage_disk)->toBe('s3');

    Storage::disk('s3')->assertExists($certificate->template_path);
});

it('validates that uploaded templates are docx files', function () {
    actingAsRole('admin');

    $this->from(route('admin.certificates.create'))
        ->post(route('admin.certificates.store'), [
            'type' => CustomCertificate::TYPE_PARTICIPATION,
            'template' => UploadedFile::fake()->create('template.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect(route('admin.certificates.create'))
        ->assertSessionHasErrors(['template']);
});

it('archives the previous active version when uploading a new template for the same type', function () {
    Storage::fake('s3');
    actingAsRole('admin');

    $firstCertificate = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'is_active' => true,
        'template_path' => 'custom-certificates/participation/first.docx',
    ]);

    Storage::disk('s3')->put($firstCertificate->template_path, 'first');

    $this->post(route('admin.certificates.store'), [
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'template' => docxUpload([
            'word/document.xml' => '<w:t>${TITOLO}</w:t>',
        ]),
    ])->assertRedirect();

    $firstCertificate->refresh();
    $newCertificate = CustomCertificate::query()
        ->where('id', '!=', $firstCertificate->getKey())
        ->sole();

    expect($firstCertificate->is_active)->toBeFalse()
        ->and($firstCertificate->replaced_by_id)->toBe($newCertificate->getKey())
        ->and($newCertificate->is_active)->toBeTrue();
});

it('restores a previous version', function () {
    actingAsRole('admin');

    $previousCertificate = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_COMPLETION,
        'is_active' => false,
        'archived_at' => now(),
    ]);

    $activeCertificate = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_COMPLETION,
        'is_active' => true,
    ]);

    $this->post(route('admin.certificates.restore-version', $previousCertificate))
        ->assertRedirect(route('admin.certificates.edit', $previousCertificate))
        ->assertSessionHas('status', 'Versione del template ripristinata con successo.');

    expect($previousCertificate->fresh()->is_active)->toBeTrue()
        ->and($activeCertificate->fresh()->is_active)->toBeFalse();
});

it('shows active templates and history on the certificates index page', function () {
    actingAsRole('admin');

    $activeCertificate = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'name' => 'Versione attiva',
        'original_filename' => 'active.docx',
    ]);

    $historyCertificate = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'name' => 'Versione storica',
        'original_filename' => 'history.docx',
        'is_active' => false,
        'archived_at' => now(),
    ]);

    $this->get(route('admin.certificates.index'))
        ->assertOk()
        ->assertSeeText('Versione attiva')
        ->assertSeeText('active.docx')
        ->assertSeeText('Versione storica')
        ->assertSeeText('history.docx')
        ->assertSee(route('admin.certificates.edit', $activeCertificate), escape: false)
        ->assertSee(route('admin.certificates.edit', $historyCertificate), escape: false);
});

it('requires course and user for preview download', function () {
    actingAsRole('admin');

    $certificate = CustomCertificate::factory()->create();

    $this->from(route('admin.certificates.preview', $certificate))
        ->post(route('admin.certificates.preview-download', $certificate), [])
        ->assertRedirect(route('admin.certificates.preview', $certificate))
        ->assertSessionHasErrors(['course_id', 'user_id']);
});

it('downloads a preview docx using fallback values when enrollment data is missing', function () {
    Storage::fake('s3');
    actingAsRole('admin');

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);

    $user = User::query()->create([
        'email' => 'mario.rossi@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);

    $templateUpload = docxUpload([
        'word/document.xml' => '<w:t>${TITOLO}|${DATA_COMPLETAMENTO_CORSO}|${NOME_UTENTE}</w:t>',
        'word/header1.xml' => '<w:t>${CODICE_FISCALE_UTENTE}</w:t>',
        'word/footer1.xml' => '<w:t>${DATA_CORSO}</w:t>',
    ]);

    $storedPath = $templateUpload->store('custom-certificates/participation', 's3');

    $certificate = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'storage_disk' => 's3',
        'template_path' => $storedPath,
        'original_filename' => 'template.docx',
    ]);

    $response = $this->post(route('admin.certificates.preview-download', $certificate), [
        'course_id' => $course->getKey(),
        'user_id' => $user->getKey(),
    ]);

    $response->assertDownload();

    $downloadedFile = $response->getFile()->getPathname();
    $archive = new ZipArchive;
    $archive->open($downloadedFile);

    $documentContent = $archive->getFromName('word/document.xml');
    $headerContent = $archive->getFromName('word/header1.xml');
    $footerContent = $archive->getFromName('word/footer1.xml');
    $archive->close();

    expect($documentContent)->toContain('Corso sicurezza')
        ->and($documentContent)->toContain(today()->format('d/m/Y'))
        ->and($documentContent)->toContain('Mario')
        ->and($headerContent)->toContain('RSSMRA80A01H501Z')
        ->and($footerContent)->toContain(today()->format('d/m/Y'));
});

it('shows the certificates menu item to admins', function () {
    actingAsRole('admin');

    $course = Course::factory()->create();

    $this->get(route('admin.courses.edit', $course))
        ->assertOk()
        ->assertSeeText('Attestati')
        ->assertSee(route('admin.certificates.index'), escape: false);
});

it('shows an explicit choice between generic and course-specific certificate templates', function () {
    actingAsRole('admin');

    $course = Course::factory()->create([
        'title' => 'Corso dedicato',
    ]);

    $this->get(route('admin.certificates.create'))
        ->assertOk()
        ->assertSeeText('Template generico')
        ->assertSeeText('Corsi specifici')
        ->assertSeeText('Corso dedicato')
        ->assertDontSeeText('Nome versione')
        ->assertSee('name="association_mode"', escape: false)
        ->assertSee('value="generic"', escape: false)
        ->assertSee('value="specific"', escape: false);
});
