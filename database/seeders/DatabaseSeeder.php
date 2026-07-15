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
        $seeders = [
            WorldDataSeeder::class,
            JobDataSeeder::class,
            LanguageLevelSeeder::class,
            DocumentTypeSeeder::class,
            RiskBasedRequirementSeeder::class,
            RoleAndPermissionSeeder::class,
            SatisfactionSurveySeeder::class,
            CourseSeeder::class,
            TestUserSeeder::class,
        ];

        if (config('app.use_default_sectors')) {
            array_splice($seeders, 1, 0, NaceAtecoSeeder::class);
        }

        $this->call($seeders);
    }
}
