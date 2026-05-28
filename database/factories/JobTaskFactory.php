<?php

namespace Database\Factories;

use App\Models\JobTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobTask>
 */
class JobTaskFactory extends Factory
{
    protected $model = JobTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
            'code' => fake()->optional()->bothify('TASK-###'),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
