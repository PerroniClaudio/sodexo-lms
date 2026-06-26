<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('admin users are redirected to dashboard after login', function () {
    $user = User::query()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Admin',
        'surname' => 'User',
        'fiscal_code' => 'DMNUSR80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('admin');

    $response = $this->post('/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('users are redirected to courses area after login', function () {
    $user = User::query()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('user');

    $response = $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('user.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('successful login stores access log entry', function () {
    $user = User::query()->create([
        'email' => 'access-log@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Access',
        'surname' => 'Logger',
        'fiscal_code' => 'LGRCSS80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('user');

    $response = $this
        ->withHeaders(['User-Agent' => 'Pest Test Agent'])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->post('/login', [
            'email' => 'access-log@example.com',
            'password' => 'password',
        ]);

    $response->assertRedirect(route('user.dashboard'));

    $this->assertDatabaseHas('users_access_log', [
        'user_id' => $user->id,
        'ip_address' => '203.0.113.10',
        'user_agent' => 'Pest Test Agent',
    ]);
});

test('authenticated admin users visiting login are redirected to dashboard', function () {
    $user = User::query()->create([
        'email' => 'superadmin@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Super',
        'surname' => 'Admin',
        'fiscal_code' => 'SPRDMN80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('superadmin');

    $response = $this->actingAs($user)->get('/login');

    $response->assertRedirect(route('dashboard'));
});

test('authenticated non admin users visiting login are redirected to reserved area', function () {
    $user = User::query()->create([
        'email' => 'tutor@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Tutor',
        'surname' => 'User',
        'fiscal_code' => 'TTRUSR80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('tutor');

    $response = $this->actingAs($user)->get('/login');

    $response->assertRedirect(route('reserved-area'));
});

test('multi role users choose active role before dashboard', function () {
    $user = User::query()->create([
        'email' => 'multi@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => 'Multi',
        'surname' => 'Role',
        'fiscal_code' => 'MLTRLE80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole(['user', 'teacher']);

    $this->post('/login', [
        'email' => 'multi@example.com',
        'password' => 'password',
    ])->assertRedirect(route('role.select'));

    $this->post(route('role.select.update'), [
        'role' => 'teacher',
    ])->assertRedirect(route('teacher.dashboard'));

    expect(session('active_role'))->toBe('teacher');
});
