<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Questionario firmato',
                'Scheda risposte',
                'Documento identita',
                'Verbale',
                'Altro allegato',
            ]),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
