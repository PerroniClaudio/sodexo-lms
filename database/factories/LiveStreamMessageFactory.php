<?php

namespace Database\Factories;

use App\Models\LiveStreamMessage;
use App\Models\LiveStreamParticipant;
use App\Models\LiveStreamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStreamMessage>
 */
class LiveStreamMessageFactory extends Factory
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
            'app_role' => fake()->randomElement([
                LiveStreamParticipant::ROLE_TEACHER,
                LiveStreamParticipant::ROLE_USER,
                LiveStreamParticipant::ROLE_TUTOR,
            ]),
            'body' => fake()->sentence(),
            'sent_at' => now(),
        ];
    }
}
