<?php

test('login view uses daisyui fieldset markup for credentials', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSee('class="fieldset"', false)
        ->assertSee('class="fieldset-legend">Email<', false)
        ->assertSee('class="fieldset-legend">Password<', false)
        ->assertSee('class="input w-full', false)
        ->assertDontSee('Usa l&#039;email con cui accedi all&#039;area riservata', false)
        ->assertDontSee('La password del tuo account', false)
        ->assertSee('data-password-input', false)
        ->assertSee('data-password-toggle', false)
        ->assertSee('class="swap swap-rotate cursor-pointer text-base-content/60"', false)
        ->assertSee('data-password-icon="show"', false)
        ->assertSee('data-password-icon="hide"', false);
});
