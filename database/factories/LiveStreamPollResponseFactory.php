<?php

namespace Database\Factories;

use App\Models\LiveStreamPoll;
use App\Models\LiveStreamPollResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStreamPollResponse>
 */
class LiveStreamPollResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'live_stream_poll_id' => LiveStreamPoll::factory(),
            'user_id' => User::factory(),
            'answer_index' => 0,
            'responded_at' => now(),
        ];
    }
}
