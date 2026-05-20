<?php

namespace App\Services;

use App\Models\LiveStreamAuditEvent;
use App\Models\LiveStreamHandRaise;
use App\Models\LiveStreamParticipant;
use App\Models\LiveStreamSession;

class LiveStreamAuditTrailService
{
    public function recordParticipantJoined(LiveStreamSession $session, LiveStreamParticipant $participant): void
    {
        $this->recordEvent($session, [
            'user_id' => $participant->user_id,
            'live_stream_participant_id' => $participant->getKey(),
            'event_type' => LiveStreamAuditEvent::TYPE_PARTICIPANT_JOINED,
            'app_role' => $participant->app_role,
            'occurred_at' => $participant->joined_at ?? now(),
            'context' => [
                'twilio_identity' => $participant->twilio_identity,
                'is_hidden' => $participant->is_hidden,
            ],
        ]);
    }

    public function recordParticipantDisconnected(LiveStreamSession $session, LiveStreamParticipant $participant, string $reason): void
    {
        $occurredAt = $participant->left_at ?? now();

        $alreadyRecorded = LiveStreamAuditEvent::query()
            ->where('live_stream_session_id', $session->getKey())
            ->where('live_stream_participant_id', $participant->getKey())
            ->where('event_type', LiveStreamAuditEvent::TYPE_PARTICIPANT_DISCONNECTED)
            ->where('occurred_at', $occurredAt)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $this->recordEvent($session, [
            'user_id' => $participant->user_id,
            'live_stream_participant_id' => $participant->getKey(),
            'event_type' => LiveStreamAuditEvent::TYPE_PARTICIPANT_DISCONNECTED,
            'app_role' => $participant->app_role,
            'occurred_at' => $occurredAt,
            'context' => [
                'reason' => $reason,
                'last_seen_at' => $participant->last_seen_at?->toIso8601String(),
            ],
        ]);
    }

    public function recordHandRaiseRequested(
        LiveStreamSession $session,
        LiveStreamHandRaise $handRaise,
        ?LiveStreamParticipant $participant = null,
    ): void {
        $this->recordEvent($session, [
            'user_id' => $handRaise->user_id,
            'live_stream_participant_id' => $participant?->getKey(),
            'live_stream_hand_raise_id' => $handRaise->getKey(),
            'event_type' => LiveStreamAuditEvent::TYPE_HAND_RAISE_REQUESTED,
            'app_role' => $participant?->app_role,
            'occurred_at' => $handRaise->requested_at ?? now(),
            'context' => [
                'status' => $handRaise->status,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordEvent(LiveStreamSession $session, array $attributes): void
    {
        LiveStreamAuditEvent::query()->create([
            'live_stream_session_id' => $session->getKey(),
            'module_id' => $session->module_id,
            ...$attributes,
        ]);
    }
}
