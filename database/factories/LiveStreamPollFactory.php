<?php

namespace Database\Factories;

use App\Models\LiveStreamPoll;
use App\Models\LiveStreamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStreamPoll>
 */
class LiveStreamPollFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'live_stream_session_id' => LiveStreamSession::factory(),
            'user_id' => User::factory(),
            'question' => fake()->sentence().'?',
            'options' => [
                fake()->words(2, true),
                fake()->words(2, true),
                fake()->words(2, true),
            ],
            'status' => LiveStreamPoll::STATUS_OPEN,
            'published_at' => now(),
            'closed_at' => null,
        ];
    }
}
