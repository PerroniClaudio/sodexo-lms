<?php

namespace Database\Factories;

use App\Models\LiveStreamParticipant;
use App\Models\LiveStreamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveStreamParticipant>
 */
class LiveStreamParticipantFactory extends Factory
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
            'app_role' => LiveStreamParticipant::ROLE_USER,
            'twilio_identity' => sprintf('sodexo:user:%s', fake()->numberBetween(1, 999)),
            'twilio_participant_sid' => 'PA'.fake()->regexify('[A-Za-z0-9]{32}'),
            'is_hidden' => false,
            'audio_enabled' => false,
            'video_enabled' => true,
            'joined_at' => now(),
            'last_seen_at' => now(),
            'left_at' => null,
        ];
    }
}
