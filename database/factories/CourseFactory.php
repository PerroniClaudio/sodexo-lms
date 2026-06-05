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
