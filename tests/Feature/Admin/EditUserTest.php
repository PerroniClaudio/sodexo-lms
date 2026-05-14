<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows the current spatie role in the account type field', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->get(route('admin.users.edit', $user));

    $response->assertOk();
    $response->assertSee('option value="teacher" selected', escape: false);
    $response->assertDontSee('option value="user" selected', escape: false);
});

it('keeps the current role when updating other fields without changing account type', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->put(route('admin.users.update', $user), [
        'account_type' => 'teacher',
        'email' => 'teacher.updated@example.test',
        'name' => 'Mario Updated',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success', 'Utente aggiornato con successo');

    $user->refresh();

    expect($user->email)->toBe('teacher.updated@example.test');
    expect($user->name)->toBe('Mario Updated');
    expect($user->getRoleNames()->all())->toBe(['teacher']);
});

it('updates the role when the account type is intentionally changed', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->put(route('admin.users.update', $user), [
        'account_type' => 'tutor',
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    expect($user->fresh()->getRoleNames()->all())->toBe(['tutor']);
});
