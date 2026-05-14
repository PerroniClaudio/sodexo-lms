<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('reserved area shows logged user card and logout action for users', function () {
    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
    ]);
    $user->assignRole('user');

    $response = $this
        ->actingAs($user)
        ->get(route('reserved-area'));

    $response->assertSuccessful();
    $response->assertSeeText('Mario Rossi');
    $response->assertSeeText('Ruolo: User');
    $response->assertSeeText('Logout');
    $response->assertDontSeeText('Profilo attivo');
    $response->assertSee('action="'.route('logout').'"', false);
});

test('reserved area shows logged user card and logout action for teachers', function () {
    $user = User::factory()->create([
        'name' => 'Laura',
        'surname' => 'Bianchi',
    ]);
    $user->assignRole('teacher');

    $response = $this
        ->actingAs($user)
        ->get(route('reserved-area'));

    $response->assertSuccessful();
    $response->assertSeeText('Laura Bianchi');
    $response->assertSeeText('Ruolo: Docente');
    $response->assertSeeText('Logout');
    $response->assertDontSeeText('Profilo attivo');
    $response->assertSee('action="'.route('logout').'"', false);
});
