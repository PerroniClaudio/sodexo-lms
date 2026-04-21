<?php

use App\Models\User;

test('welcome page shows a navbar with login button to guests', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSeeText(config('app.name', 'Laravel'));
    $response->assertSeeText('Login');
    $response->assertDontSeeText('Area riservata');
    $response->assertSee('href="'.route('login').'"', false);
});

test('welcome page shows the reserved area button to authenticated users', function () {
    $user = User::factory()->make([
        'id' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->get('/');

    $response->assertSuccessful();
    $response->assertSeeText(config('app.name', 'Laravel'));
    $response->assertSeeText('Area riservata');
    $response->assertDontSeeText('Login');
    $response->assertSee('href="'.route('reserved-area').'"', false);
});
