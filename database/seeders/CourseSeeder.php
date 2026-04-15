<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            'Bozza',
            'Pubblicato',
            'Archiviato',
        ];

        Course::factory()
            ->count(40)
            ->sequence(fn ($sequence) => [
                'title' => fake()->unique()->sentence(3),
                'description' => fake()->paragraph(),
                'type' => Course::availableTypes()[$sequence->index % count(Course::availableTypes())],
                'year' => now()->subYears($sequence->index % 5)->year,
                'expiry_date' => now()->addDays(fake()->numberBetween(30, 365)),
                'status' => $statuses[$sequence->index % count($statuses)],
                'hasMany' => (string) fake()->numberBetween(1, 8),
            ])
            ->create();
    }
}
