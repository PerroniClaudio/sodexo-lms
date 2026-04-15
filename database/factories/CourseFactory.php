<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->text(),
            'type' => fake()->randomElement(Course::availableTypes()),
            'year' => fake()->numberBetween(-10000, 10000),
            'expiry_date' => fake()->dateTime(),
            'status' => fake()->word(),
            'hasMany' => fake()->word(),
        ];
    }
}
