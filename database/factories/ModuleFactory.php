<?php

namespace Database\Factories;

use App\Models\Module;
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
            'type' => fake()->randomElement(Module::availableTypes()),
            'order' => fake()->numberBetween(1, 10),
            'is_live_teacher' => fake()->boolean(),
            'appointment_date' => fake()->dateTime(),
            'appointment_start_time' => fake()->dateTime(),
            'appointment_end_time' => fake()->dateTime(),
            'status' => 'draft',
            'belongsTo' => (string) fake()->numberBetween(1, 100),
        ];
    }
}
