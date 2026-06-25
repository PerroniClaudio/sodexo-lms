<?php

use App\Enums\UserStatus;
use App\Jobs\SuspendExpiredUsersJob;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Carbon;

it('suspends only expired users with role user', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    Carbon::setTestNow('2026-06-25 10:00:00');

    $makeUser = function (array $attributes, string $role): User {
        $user = User::factory()->create($attributes);
        $user->syncRoles([$role]);

        return $user;
    };

    $expiredUser = $makeUser([
        'account_state' => UserStatus::ACTIVE,
        'employment_end_date' => '2026-06-24',
    ], 'user');
    $todayUser = $makeUser([
        'account_state' => UserStatus::ACTIVE,
        'employment_end_date' => '2026-06-25',
    ], 'user');
    $noEndDateUser = $makeUser([
        'account_state' => UserStatus::ACTIVE,
        'employment_end_date' => null,
    ], 'user');
    $suspendedUser = $makeUser([
        'account_state' => UserStatus::SUSPENDED,
        'employment_end_date' => '2026-06-20',
    ], 'user');
    $expiredTeacher = $makeUser([
        'account_state' => UserStatus::ACTIVE,
        'employment_end_date' => '2026-06-24',
    ], 'teacher');

    (new SuspendExpiredUsersJob)->handle();

    expect($expiredUser->fresh()->account_state)->toBe(UserStatus::SUSPENDED)
        ->and($todayUser->fresh()->account_state)->toBe(UserStatus::ACTIVE)
        ->and($noEndDateUser->fresh()->account_state)->toBe(UserStatus::ACTIVE)
        ->and($suspendedUser->fresh()->account_state)->toBe(UserStatus::SUSPENDED)
        ->and($expiredTeacher->fresh()->account_state)->toBe(UserStatus::ACTIVE);

    Carbon::setTestNow();
});
