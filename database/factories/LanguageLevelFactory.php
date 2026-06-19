<?php

namespace Database\Factories;

use App\Models\LanguageLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LanguageLevel>
 */
class LanguageLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('??'),
            'sort_order' => fake()->unique()->numberBetween(1, 50),
            'is_default' => false,
        ];
    }
}
