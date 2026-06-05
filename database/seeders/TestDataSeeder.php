<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RiskBasedRequirementSeeder::class,
            SatisfactionSurveySeeder::class,
            TestCourseCatalogSeeder::class,
            TestUserRiskScenariosSeeder::class,
        ]);
    }
}
