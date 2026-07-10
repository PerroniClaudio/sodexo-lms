<?php

namespace Database\Factories;

use App\Models\JobBasedRequirement;
use App\Models\JobRole;
use App\Models\JobTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobBasedRequirement>
 */
class JobBasedRequirementFactory extends Factory
{
    protected $model = JobBasedRequirement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jobRole = JobRole::query()->inRandomOrder()->first() ?? JobRole::factory()->create();
        $jobTask = JobTask::query()->inRandomOrder()->first() ?? JobTask::factory()->create();

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'rules' => [
                [
                    [
                        'field' => 'job_role_id',
                        'operator' => '===',
                        'value' => $jobRole->getKey(),
                    ],
                    [
                        'field' => 'job_task_id',
                        'operator' => 'IN',
                        'value' => [$jobTask->getKey()],
                    ],
                ],
            ],
        ];
    }
}
