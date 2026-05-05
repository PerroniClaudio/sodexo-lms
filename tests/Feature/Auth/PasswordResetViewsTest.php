<?php

test('forgot password view renders correctly', function () {
    $response = $this->get('/forgot-password');

    $response
        ->assertOk()
        ->assertSee('Recupera password')
        ->assertSee('name="email"', false)
        ->assertSee(route('password.email'), false);
});

test('forgot password status message is translated', function () {
    $response = $this
        ->withSession(['status' => 'passwords.sent'])
        ->get('/forgot-password');

    $response
        ->assertOk()
        ->assertSee('Inviata mail di recupero password.')
        ->assertDontSee('passwords.sent');
});

test('reset password status message is translated', function () {
    $response = $this
        ->withSession(['status' => 'passwords.reset'])
        ->get('/login');

    $response
        ->assertOk()
        ->assertSee('Password reimpostata con successo.')
        ->assertDontSee('passwords.reset');
});

test('reset password view renders correctly', function () {
    $response = $this->get('/reset-password/test-token?email=test@example.com');

    $response
        ->assertOk()
        ->assertSee('Imposta una nuova password')
        ->assertSee('name="token" value="test-token"', false)
        ->assertSee('name="email"', false)
        ->assertSee('value="test@example.com"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="password_confirmation"', false);
});
