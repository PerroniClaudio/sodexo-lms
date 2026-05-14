<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTitle;
use App\Models\JobUnit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        'teacher',
        'tutor',
    ];

    private const DEFAULT_PASSWORD = 'Sodexo@Test.26';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $verifiedAt = Carbon::now();
        $userRoleAttributes = $this->userRoleAttributes();

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
                ...($role === 'user' ? $userRoleAttributes : []),
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
            'teacher' => 'DOCENT80A01H501D',
            'tutor' => 'TUTORX80A01H501E',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function userRoleAttributes(): array
    {
        $jobUnit = JobUnit::query()->inRandomOrder()->first();
        $jobTitle = JobTitle::query()->inRandomOrder()->first();
        $jobRole = JobRole::query()->inRandomOrder()->first();
        $jobSector = JobSector::query()->inRandomOrder()->first();

        if ($jobUnit === null || $jobTitle === null || $jobRole === null || $jobSector === null) {
            throw new ModelNotFoundException('Missing required job data for seeding the test user with role user.');
        }

        return [
            'job_unit_id' => $jobUnit->id,
            'job_title_id' => $jobTitle->id,
            'job_role_id' => $jobRole->id,
            'job_sector_id' => $jobSector->id,
            'is_foreigner_or_immigrant' => false,
            'home_country_id' => $jobUnit->country_id,
            'home_region_id' => $jobUnit->region_id,
            'home_province_id' => $jobUnit->province_id,
            'home_city_id' => $jobUnit->city_id,
        ];
    }
}
