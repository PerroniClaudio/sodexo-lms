<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function makeStaffUser(array $attributes = []): User
{
    return User::query()->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => false,
    ], $attributes));
}

it('shows the current spatie role in the account type field', function () {
    actingAsRole('superadmin');

    $user = makeStaffUser([
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
    actingAsRole('admin');

    $user = makeStaffUser([
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

it('allows a superadmin to change the role from the edit page', function () {
    actingAsRole('superadmin');

    $user = makeStaffUser([
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

it('does not show an editable account type selector to admin users', function () {
    actingAsRole('admin');

    $user = makeStaffUser([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->get(route('admin.users.edit', $user));

    $response->assertOk();
    $response->assertDontSee('select name="account_type"', escape: false);
    $response->assertSee('type="hidden" name="account_type" value="teacher"', escape: false);
});

it('prevents an admin from changing the role via a crafted request', function () {
    actingAsRole('admin');

    $user = makeStaffUser([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->from(route('admin.users.edit', $user))->put(route('admin.users.update', $user), [
        'account_type' => 'tutor',
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);

    $response->assertRedirect(route('admin.users.edit', $user));
    $response->assertSessionHasErrors('account_type');

    expect($user->fresh()->getRoleNames()->all())->toBe(['teacher']);
});

it('maps teacher selection to docente when the database only has the legacy role name', function () {
    actingAsRole('superadmin');

    Role::findByName('teacher')->delete();
    Role::findOrCreate('docente');

    $user = makeStaffUser([
        'email' => 'docente@example.test',
        'name' => 'Giulia',
        'surname' => 'Bianchi',
        'fiscal_code' => 'BNCGLI80A01H501Z',
    ]);
    $user->assignRole('user');

    $response = $this->put(route('admin.users.update', $user), [
        'account_type' => 'teacher',
        'email' => 'docente@example.test',
        'name' => 'Giulia',
        'surname' => 'Bianchi',
        'fiscal_code' => 'BNCGLI80A01H501Z',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    expect($user->fresh()->getRoleNames()->all())->toBe(['docente']);
});
