<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->text(),
            'type' => fake()->word(),
            'order' => fake()->numberBetween(-10000, 10000),
            'appointment_date' => fake()->dateTime(),
            'appointment_start_time' => fake()->dateTime(),
            'appointment_end_time' => fake()->dateTime(),
            'status' => fake()->word(),
            'belongsTo' => fake()->word(),
        ];
    }
}
