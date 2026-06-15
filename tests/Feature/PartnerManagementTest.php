<?php

use App\Models\Course;
use App\Models\Partner;

beforeEach(function (): void {
    $this->withoutVite();
});

it('allows admins to manage partners', function (): void {
    actingAsRole('admin');

    $this->get(route('admin.partners.index'))
        ->assertOk()
        ->assertSeeText('Partner')
        ->assertSeeText('Crea');

    $this->post(route('admin.partners.store'), [
        'ragione_sociale' => 'ACME Srl',
    ])->assertRedirect(route('admin.partners.index'));

    $partner = Partner::query()->where('ragione_sociale', 'ACME Srl')->firstOrFail();

    $this->put(route('admin.partners.update', $partner), [
        'ragione_sociale' => 'ACME Italia Srl',
    ])->assertRedirect(route('admin.partners.edit', $partner));

    expect($partner->fresh()->ragione_sociale)->toBe('ACME Italia Srl');

    $this->delete(route('admin.partners.destroy', $partner))
        ->assertRedirect(route('admin.partners.index'));

    $this->assertSoftDeleted($partner);
});

it('blocks deleting partners associated to courses', function (): void {
    actingAsRole('admin');

    $partner = Partner::factory()->create();
    $course = Course::factory()->create();
    $course->partners()->attach($partner);

    $this->delete(route('admin.partners.destroy', $partner))
        ->assertRedirect(route('admin.partners.index'))
        ->assertSessionHas('error');

    $this->assertNotSoftDeleted($partner);
});

it('removes course association from partner edit page', function (): void {
    actingAsRole('admin');

    $partner = Partner::factory()->create();
    $course = Course::factory()->create();
    $course->partners()->attach($partner);

    $this->get(route('admin.partners.edit', $partner))
        ->assertOk()
        ->assertSeeText($course->title)
        ->assertSeeText('Rimuovi');

    $this->delete(route('admin.partners.courses.destroy', [$partner, $course]))
        ->assertRedirect(route('admin.partners.edit', $partner));

    expect($partner->courses()->count())->toBe(0);
});

it('associates multiple partners to a course', function (): void {
    actingAsRole('admin');

    $course = Course::factory()->create();
    $partners = Partner::factory()->count(2)->create();

    $this->put(route('admin.courses.partners.update', $course), [
        'partner_ids' => $partners->pluck('id')->all(),
    ])->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'partners']));

    expect($course->partners()->pluck('partners.id')->all())
        ->toEqualCanonicalizing($partners->pluck('id')->all());

    $this->get(route('admin.courses.edit', [$course, 'section' => 'partners']))
        ->assertOk()
        ->assertSeeText('Partner')
        ->assertSeeText($partners[0]->ragione_sociale)
        ->assertSeeText($partners[1]->ragione_sociale);
});

it('forbids non admins from managing partners', function (): void {
    actingAsRole('user');

    $this->get(route('admin.partners.index'))->assertRedirect();
});
