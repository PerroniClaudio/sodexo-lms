<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests away from protected role routes', function () {
    $this->get(route('admin.courses.index'))->assertRedirect(route('login'));
    $this->get(route('teacher.live-stream.player'))->assertRedirect(route('login'));
    $this->get(route('tutor.live-stream.player'))->assertRedirect(route('login'));
    $this->get(route('user.live-stream.player'))->assertRedirect(route('login'));
});

it('forbids access to admin routes for non admin roles', function () {
    actingAsRole('user');

    $this->get(route('admin.courses.index'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('allows admins to access admin routes', function () {
    actingAsRole('admin');

    $this->get(route('admin.courses.index'))->assertOk();
});

it('redirects admins to dashboard with an error when they access another role area', function () {
    actingAsRole('admin');

    $this->get(route('teacher.live-stream.player'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('forbids access to teacher routes for non docente roles', function () {
    actingAsRole('user');

    $this->get(route('teacher.live-stream.player'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('allows docenti to access teacher routes', function () {
    actingAsRole('docente');

    $this->get(route('teacher.live-stream.player'))->assertOk();
});

it('forbids access to tutor routes for non tutor roles', function () {
    actingAsRole('user');

    $this->get(route('tutor.live-stream.player'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('allows tutors to access tutor routes', function () {
    actingAsRole('tutor');

    $this->get(route('tutor.live-stream.player'))->assertOk();
});

it('forbids access to user routes for non user roles', function () {
    actingAsRole('tutor');

    $this->get(route('user.live-stream.player'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('allows users to access user routes', function () {
    actingAsRole('user');

    $this->get(route('user.live-stream.player'))->assertOk();
});
