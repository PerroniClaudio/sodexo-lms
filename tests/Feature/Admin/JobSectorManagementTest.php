<?php

use App\Models\CustomCertificate;
use App\Models\JobSector;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows the manual fallback risk field on the job sector create page', function () {
    $response = $this->get(route('admin.job-sectors.create'));

    $response->assertOk()
        ->assertSeeText('Rischio manuale di fallback')
        ->assertSee('name="manual_risk_level"', escape: false);
});

it('assigns only a user from the same sector as employer', function () {
    $sector = JobSector::factory()->create();
    $otherSector = JobSector::factory()->create();
    $employer = User::factory()->create(['job_sector_id' => $sector->getKey()]);
    $outsider = User::factory()->create(['job_sector_id' => $otherSector->getKey()]);

    $this->get(route('admin.job-sectors.edit', $sector))
        ->assertOk()
        ->assertSeeText('Datore di lavoro')
        ->assertSeeText('Template attestati')
        ->assertSeeText($employer->email);

    $this->put(route('admin.job-sectors.update', $sector), [
        'name' => $sector->name,
        'description' => $sector->description,
        'employer_user_id' => $employer->getKey(),
    ])->assertRedirect(route('admin.job-sectors.edit', $sector));

    expect($sector->fresh()->employer?->is($employer))->toBeTrue();

    $this->put(route('admin.job-sectors.update', $sector), [
        'name' => $sector->name,
        'description' => $sector->description,
        'employer_user_id' => $outsider->getKey(),
    ])->assertSessionHasErrors('employer_user_id');

    $this->put(route('admin.job-sectors.update', $sector), [
        'name' => $sector->name,
        'description' => $sector->description,
        'employer_user_id' => null,
    ])->assertSessionHasNoErrors();
});

it('stores and replaces certificate templates only within the sector and type', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $sector = JobSector::factory()->create();
    $otherSector = JobSector::factory()->create();
    $otherTemplate = CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'job_sector_id' => $otherSector->getKey(),
    ]);

    $upload = fn () => docxUpload(['word/document.xml' => '<w:t>${TITOLO}</w:t>']);

    $this->put(route('admin.job-sectors.certificate-templates.update', $sector), [
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'template' => $upload(),
    ])->assertRedirect(route('admin.job-sectors.edit', $sector));

    $first = CustomCertificate::query()
        ->where('job_sector_id', $sector->getKey())
        ->ofType(CustomCertificate::TYPE_PARTICIPATION)
        ->sole();

    $this->put(route('admin.job-sectors.certificate-templates.update', $sector), [
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'template' => $upload(),
    ])->assertRedirect(route('admin.job-sectors.edit', $sector));

    expect($first->fresh()->is_active)->toBeFalse()
        ->and($otherTemplate->fresh()->is_active)->toBeTrue()
        ->and(CustomCertificate::query()
            ->active()
            ->where('job_sector_id', $sector->getKey())
            ->ofType(CustomCertificate::TYPE_PARTICIPATION)
            ->count())->toBe(1);
});
