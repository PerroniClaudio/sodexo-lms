<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('welcome page shows the public homepage to guests', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSeeText(config('app.name', 'Laravel'));
    $response->assertSeeText('Accedi');
    $response->assertSeeText('Lavoriamo per la');
    $response->assertSeeText('SALUTE');
    $response->assertSeeText('I nostri servizi');
    $response->assertSeeText('Corsi FAD');
    $response->assertSeeText('Corsi RES');
    $response->assertSeeText('Corsi FSC');
    $response->assertSeeText('Chi siamo');
    $response->assertSeeText('Cookie');
    $response->assertSeeText('Privacy policy');
    $response->assertDontSeeText('Area riservata');
    $response->assertSee('href="'.route('login').'"', false);
});
