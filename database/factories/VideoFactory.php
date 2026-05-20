<?php

namespace Database\Factories;

use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'mux_asset_id' => fake()->optional()->uuid(),
            'mux_playback_id' => fake()->optional()->uuid(),
            'mux_upload_id' => fake()->optional()->uuid(),
            'mux_video_status' => 'ready',
            'duration_seconds' => fake()->numberBetween(60, 600),
        ];
    }
}
