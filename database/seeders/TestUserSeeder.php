<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, string>
     */
    private const ROLES = [
        'superadmin',
        'admin',
        'user',
        'docente',
        'tutor',
    ];

    private const DEFAULT_PASSWORD = 'Sodexo@Test.26';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $verifiedAt = Carbon::now();

        foreach (self::ROLES as $role) {
            $user = User::firstOrNew(['email' => sprintf('%s@test.com', $role)]);

            $user->forceFill([
                'name' => ucfirst($role),
                'surname' => 'Test',
                'email_verified_at' => $verifiedAt,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'account_state' => UserStatus::ACTIVE,
                'profile_completed_at' => $verifiedAt,
                'fiscal_code' => $this->fiscalCodeForRole($role),
            ]);

            $user->save();
            $user->syncRoles([$role]);
        }
    }

    private function fiscalCodeForRole(string $role): string
    {
        return match ($role) {
            'superadmin' => 'SPRDMN80A01H501A',
            'admin' => 'ADMINN80A01H501B',
            'user' => 'USERXX80A01H501C',
            'docente' => 'DOCENT80A01H501D',
            'tutor' => 'TUTORX80A01H501E',
        };
    }
}
