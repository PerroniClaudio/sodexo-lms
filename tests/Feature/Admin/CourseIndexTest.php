<?php

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the courses index page with paginated results', function () {
    Course::factory()->count(25)->create();

    $response = $this->get(route('admin.courses.index'));

    $response->assertOk();
    $response->assertSeeText('Corsi');
    $response->assertSeeText('Titolo del corso');
    $response->assertSeeText('Stato');
    $response->assertSeeText('Anno del corso');
    $response->assertSeeText('Crea nuovo');
    $response->assertSee(route('admin.courses.create'), escape: false);
    $response->assertViewHas('courses', fn ($courses) => $courses->count() === 20 && $courses->total() === 25);
});
