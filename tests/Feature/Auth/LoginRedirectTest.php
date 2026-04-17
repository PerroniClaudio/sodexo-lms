<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('admin users are redirected to dashboard after login', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('admin');

    $response = $this->post('/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('non admin users are redirected to reserved area after login', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('user');

    $response = $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('reserved-area'));
    $this->assertAuthenticatedAs($user);
});

test('authenticated admin users visiting login are redirected to dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    $response = $this->actingAs($user)->get('/login');

    $response->assertRedirect(route('dashboard'));
});

test('authenticated non admin users visiting login are redirected to reserved area', function () {
    $user = User::factory()->create();
    $user->assignRole('tutor');

    $response = $this->actingAs($user)->get('/login');

    $response->assertRedirect(route('reserved-area'));
});
