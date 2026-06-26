<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

beforeEach(function () {
    $this->withoutVite();
});

it('shows training path program download buttons on the user page', function () {
    $user = actingAsRole('user');
    $trainingPath = TrainingPath::factory()->create([
        'title' => 'Percorso Sicurezza',
    ]);
    $course = Course::factory()->create([
        'title' => 'Corso Antincendio',
        'status' => 'published',
    ]);

    $trainingPath->courses()->attach($course->getKey(), ['sort_order' => 1]);
    $trainingPathEnrollment = TrainingPathEnrollment::enroll($user, $trainingPath);
    CourseEnrollment::enroll($user, $course);

    $this->actingAs($user)
        ->get(route('user.training-paths.show', $trainingPathEnrollment))
        ->assertOk()
        ->assertSee(route('user.training-paths.program.download', $trainingPathEnrollment), escape: false)
        ->assertSee(route('user.training-paths.program-with-progress.download', $trainingPathEnrollment), escape: false)
        ->assertSeeText('Scarica programma')
        ->assertSeeText('Scarica con avanzamento');
});

it('downloads the user training path program pdf', function () {
    Pdf::fake();

    $user = actingAsRole('user');
    $trainingPath = TrainingPath::factory()->create([
        'title' => 'Percorso Sicurezza',
        'description' => 'Percorso per la sicurezza sul lavoro',
    ]);
    $course = Course::factory()->create([
        'title' => 'Corso Antincendio',
        'status' => 'published',
        'type' => 'res',
    ]);

    $trainingPath->courses()->attach($course->getKey(), ['sort_order' => 1]);
    $trainingPathEnrollment = TrainingPathEnrollment::enroll($user, $trainingPath);
    CourseEnrollment::enroll($user, $course);

    $this->actingAs($user)
        ->get(route('user.training-paths.program.download', $trainingPathEnrollment))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($trainingPath): bool {
        $html = $pdf->getHtml();

        expect($pdf->viewName)->toBe('pdf.training-path-program');
        expect($pdf->viewData['trainingPath']->is($trainingPath))->toBeTrue();
        expect($pdf->downloadName)->toBe('percorso-sicurezza-programma-formativo.pdf');
        expect($html)->toContain('Percorso Sicurezza');
        expect($html)->toContain('Corso Antincendio');
        expect($html)->not->toContain('Avanzamento utente');

        return true;
    });
});

it('downloads the user training path progress pdf', function () {
    Pdf::fake();

    $user = actingAsRole('user');
    $trainingPath = TrainingPath::factory()->create([
        'title' => 'Percorso Sicurezza',
    ]);
    $course = Course::factory()->create([
        'title' => 'Corso Antincendio',
        'status' => 'published',
        'type' => 'res',
    ]);

    $trainingPath->courses()->attach($course->getKey(), ['sort_order' => 1]);
    $trainingPathEnrollment = TrainingPathEnrollment::enroll($user, $trainingPath);

    $courseEnrollment = CourseEnrollment::enroll($user, $course);
    $courseEnrollment->forceFill([
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
        'completion_percentage' => 45,
    ])->save();

    $this->actingAs($user)
        ->get(route('user.training-paths.program-with-progress.download', $trainingPathEnrollment))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        $html = $pdf->getHtml();

        expect($pdf->viewName)->toBe('pdf.training-path-program');
        expect($pdf->downloadName)->toBe('percorso-sicurezza-programma-formativo-avanzamento.pdf');
        expect($html)->toContain('Avanzamento utente');
        expect($html)->toContain('0/1');
        expect($html)->toContain('0%');
        expect($html)->toContain('In corso');
        expect($html)->toContain('45%');

        return true;
    });
});
