<?php

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

test('homepage rich text lists keep their markers', function () {
    $services = view('components.homepage.services')->render();
    $about = view('components.homepage.about')->render();

    expect($services)
        ->toContain('[&_ul]:list-disc')
        ->toContain('[&_ol]:list-decimal')
        ->and($about)
        ->toContain('[&_ul]:list-disc')
        ->toContain('[&_ol]:list-decimal')
        ->not->toContain('[&_p]:font-bold');
});
