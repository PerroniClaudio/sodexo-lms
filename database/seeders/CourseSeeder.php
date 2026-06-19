<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\LanguageLevel;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = Course::availableTypes();
        $statuses = Course::availableStatuses();
        $defaultLanguageLevelId = LanguageLevel::defaultOrFirst()?->getKey();

        Course::factory()
            ->count(40)
            ->sequence(fn ($sequence) => [
                'title' => fake()->unique()->sentence(3),
                'description' => fake()->paragraph(),
                'type' => $types[$sequence->index % count($types)],
                'year' => now()->subYears($sequence->index % 5)->year,
                'expiry_date' => now()->addDays(fake()->numberBetween(30, 365)),
                'status' => $statuses[$sequence->index % count($statuses)],
                'required_language_level_id' => $defaultLanguageLevelId,
                'hasMany' => (string) fake()->numberBetween(1, 8),
            ])
            ->create();
    }
}
