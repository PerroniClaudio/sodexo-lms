<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            WorldDataSeeder::class,
            NaceAtecoSeeder::class,
            JobDataSeeder::class,
            DocumentTypeSeeder::class,
            RiskBasedRequirementSeeder::class,
            RoleAndPermissionSeeder::class,
            SatisfactionSurveySeeder::class,
            CourseSeeder::class,
            TestUserSeeder::class,
        ]);
    }
}
