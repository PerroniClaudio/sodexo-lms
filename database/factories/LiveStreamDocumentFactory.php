<?php

namespace Database\Factories;

use App\Models\LiveStreamDocument;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStreamDocument>
 */
class LiveStreamDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'live-stream-documents/'.fake()->uuid().'.pdf',
            'original_name' => 'materiale-live.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(50_000, 2_000_000),
            'uploaded_at' => now(),
        ];
    }
}
