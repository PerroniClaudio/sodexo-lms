<?php

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\MultiRoleUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\TestUserSeeder;
use Illuminate\Support\Facades\Hash;

test('it creates one test user for each configured role', function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        TestUserSeeder::class,
    ]);

    $roles = ['superadmin', 'admin', 'user', 'teacher', 'tutor'];

    expect(User::query()->count())->toBe(count($roles));

    foreach ($roles as $role) {
        $user = User::query()->where('email', sprintf('%s@test.com', $role))->first();

        expect($user)->not->toBeNull();
        expect($user->hasRole($role))->toBeTrue();
        expect($user->account_state)->toBe(UserStatus::ACTIVE);
    }
});

test('it creates the multi role test user', function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        MultiRoleUserSeeder::class,
    ]);

    $user = User::query()->where('email', 'multiuser@test.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('user'))->toBeTrue();
    expect($user->hasRole('teacher'))->toBeTrue();
    expect(Hash::check('Multiuser@2026', $user->password))->toBeTrue();
});
