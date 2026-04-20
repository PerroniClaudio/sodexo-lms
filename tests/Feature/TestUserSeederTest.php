<?php

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\TestUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it creates one test user for each configured role', function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        TestUserSeeder::class,
    ]);

    $roles = ['superadmin', 'admin', 'user', 'docente', 'tutor'];

    expect(User::query()->count())->toBe(count($roles));

    foreach ($roles as $role) {
        $user = User::query()->where('email', sprintf('%s@test.com', $role))->first();

        expect($user)->not->toBeNull();
        expect($user->hasRole($role))->toBeTrue();
        expect($user->account_state)->toBe(UserStatus::ACTIVE);
    }
});
