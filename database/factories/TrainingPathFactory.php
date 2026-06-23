<?php

namespace Database\Factories;

use App\Models\TrainingPath;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPath>
 */
class TrainingPathFactory extends Factory
{
    protected $model = TrainingPath::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'code' => 'PATH-'.strtoupper(fake()->bothify('??##')),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(TrainingPath::availableStatuses()),
            'visible_to_all' => true,
            'enforce_course_order' => true,
        ];
    }
}
