<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->text(),
            'teaching_material' => fake()->optional()->paragraph(),
            'max_participants' => fake()->optional()->numberBetween(5, 100),
            'internal_notes' => fake()->optional()->paragraph(),
            'training_objective' => fake()->optional()->paragraph(),
            'knowledge' => fake()->optional()->paragraph(),
            'skills' => fake()->optional()->paragraph(),
            'competences' => fake()->optional()->paragraph(),
            'regulatory_reference' => fake()->optional()->paragraph(),
            'course_start_date' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'course_end_date' => fake()->optional()->dateTimeBetween('+1 month', '+2 months'),
            'access_closure_date' => fake()->optional()->dateTimeBetween('+2 months', '+3 months'),
            'course_duration_hours' => fake()->optional()->numberBetween(1, 80),
            'interaction_duration_minutes' => fake()->optional()->numberBetween(1, 480),
            'type' => fake()->randomElement(Course::availableTypes()),
            'year' => now()->year,
            'expiry_date' => now()->copy()->endOfYear(),
            'status' => fake()->randomElement(Course::availableStatuses()),
            'has_satisfaction_survey' => false,
            'satisfaction_survey_required_for_certificate' => false,
            'hasMany' => '1',
        ];
    }

    public function res(): static
    {
        return $this->state(fn (): array => [
            'type' => 'res',
        ]);
    }

    public function async(): static
    {
        return $this->state(fn (): array => [
            'type' => 'async',
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
        ]);
    }

    public function currentYear(): static
    {
        return $this->state(fn (): array => [
            'year' => now()->year,
            'expiry_date' => now()->copy()->endOfYear(),
        ]);
    }
}
