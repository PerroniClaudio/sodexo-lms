<?php

namespace Database\Factories;

use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleQuizAnswer>
 */
class ModuleQuizAnswerFactory extends Factory
{
    protected $model = ModuleQuizAnswer::class;

    public function definition(): array
    {
        return [
            'question_id' => ModuleQuizQuestion::factory(),
            'text' => fake()->sentence(3),
        ];
    }
}
