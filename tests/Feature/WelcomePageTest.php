<?php

test('welcome page shows a navbar with login button', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSeeText(config('app.name', 'Laravel'));
    $response->assertSeeText('Login');
    $response->assertSee('href="'.route('login').'"', false);
});
