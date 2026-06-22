<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MultiRoleUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrNew(['email' => 'multiuser@test.com']);

        $user->forceFill([
            'name' => 'Multi',
            'surname' => 'User',
            'email_verified_at' => now(),
            'password' => Hash::make('Multiuser@2026'),
            'account_state' => UserStatus::ACTIVE,
            'profile_completed_at' => now(),
            'fiscal_code' => 'MLTUSR80A01H501F',
            'is_foreigner_or_immigrant' => false,
        ]);

        $user->save();
        $user->syncRoles(['user', 'teacher']);
    }
}
