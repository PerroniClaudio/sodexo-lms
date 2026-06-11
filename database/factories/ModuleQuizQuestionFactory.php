<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\ModuleQuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleQuizQuestion>
 */
class ModuleQuizQuestionFactory extends Factory
{
    protected $model = ModuleQuizQuestion::class;

    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'text' => fake()->sentence(),
            'points' => fake()->numberBetween(1, 10),
            'correct_answer_id' => null,
        ];
    }
}
