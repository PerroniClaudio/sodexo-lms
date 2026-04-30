<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('welcome page shows the public homepage to guests', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSeeText(config('app.name', 'Laravel'));
    $response->assertSeeText('Accedi');
    $response->assertSeeText('Hero');
    $response->assertSeeText('pagina principale');
    $response->assertSeeText('Sezione servizi homepage');
    $response->assertSeeText('Contenuto generico uno');
    $response->assertSeeText('Contenuto generico due');
    $response->assertSeeText('Contenuto generico tre');
    $response->assertSeeText('Sezione contenuti');
    $response->assertSeeText('Cookie');
    $response->assertSeeText('Privacy policy');
    $response->assertDontSeeText('Area riservata');
    $response->assertSee('href="'.route('login').'"', false);
});
