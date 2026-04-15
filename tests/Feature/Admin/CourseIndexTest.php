<?php

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the courses index page with paginated results', function () {
    Course::factory()->count(25)->create();

    $response = $this->get(route('admin.courses.index'));

    $response->assertSuccessful();
    $response->assertSeeText('Corsi');
    $response->assertSeeText('Titolo del corso');
    $response->assertSeeText('Stato');
    $response->assertSeeText('Anno del corso');
    $response->assertSeeText('Crea nuovo');
    $response->assertSeeText('Cerca');
    $response->assertSee(route('admin.courses.create'), escape: false);
    $response->assertViewHas('courses', fn ($courses) => $courses->count() === 20 && $courses->total() === 25);
});

it('sorts courses using the allowed query string parameters', function () {
    Course::factory()->create(['title' => 'Zulu course', 'status' => 'draft', 'year' => 2024]);
    Course::factory()->create(['title' => 'Alpha course', 'status' => 'published', 'year' => 2026]);
    Course::factory()->create(['title' => 'Beta course', 'status' => 'archived', 'year' => 2025]);

    $response = $this->get(route('admin.courses.index', [
        'sort' => 'title',
        'direction' => 'asc',
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('tableSort', 'title');
    $response->assertViewHas('tableDirection', 'asc');
    $response->assertViewHas('courses', fn ($courses) => $courses->pluck('title')->take(3)->values()->all() === [
        'Alpha course',
        'Beta course',
        'Zulu course',
    ]);
});

it('falls back to the default ordering when sort params are invalid', function () {
    $oldestCourse = Course::factory()->create();
    $latestCourse = Course::factory()->create();

    $response = $this->get(route('admin.courses.index', [
        'sort' => 'invalid',
        'direction' => 'sideways',
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('tableSort', 'id');
    $response->assertViewHas('tableDirection', 'desc');
    $response->assertViewHas('courses', fn ($courses) => $courses->first()->is($latestCourse) && $courses->last()->is($oldestCourse));
});

it('searches across the visible course columns and keeps query strings in pagination', function () {
    Course::factory()->create([
        'title' => 'Corso sicurezza',
        'status' => 'draft',
        'year' => 2024,
    ]);
    Course::factory()->create([
        'title' => 'Corso compliance',
        'status' => 'published',
        'year' => 2025,
    ]);
    Course::factory()->create([
        'title' => 'Corso onboarding',
        'status' => 'archived',
        'year' => 2026,
    ]);

    $response = $this->get(route('admin.courses.index', [
        'search' => 'published',
        'sort' => 'status',
        'direction' => 'asc',
    ]));

    $response->assertSuccessful();
    $response->assertViewHas('tableSearch', 'published');
    $response->assertSee('value="published"', escape: false);
    $response->assertSee('sort=status', escape: false);
    $response->assertSee('direction=asc', escape: false);
    $response->assertViewHas('courses', fn ($courses) => $courses->count() === 1
        && $courses->first()->title === 'Corso compliance');
});
