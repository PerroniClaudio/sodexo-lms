<?php

namespace App\Services;

use App\Models\LiveStreamSession;
use App\Models\Module;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MuxLiveService
{
    private const API_BASE_URL = 'https://api.mux.com/video/v1';

    public function ensurePersistentStreamForModule(Module $module): Module
    {
        if ($module->mux_live_stream_id !== null && $module->mux_playback_id !== null && $module->mux_stream_key !== null) {
            return $module;
        }

        try {
            $response = $this->request()->post('live-streams', [
                'playback_policy' => ['public'],
                'new_asset_settings' => [
                    'playback_policy' => ['public'],
                ],
                'reconnect_window' => (int) config('services.mux.live.reconnect_window', 60),
            ])->throw();
        } catch (Throwable $exception) {
            Log::error('Mux live stream creation failed.', [
                'module_id' => $module->getKey(),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $data = $response->json('data');
        $playbackId = Arr::first(Arr::pluck($data['playback_ids'] ?? [], 'id'));

        if (! is_array($data) || $playbackId === null || empty($data['stream_key']) || empty($data['id'])) {
            throw new RuntimeException('Mux live stream response is missing required fields.');
        }

        $module->forceFill([
            'mux_live_stream_id' => $data['id'],
            'mux_playback_id' => $playbackId,
            'mux_stream_key' => $data['stream_key'],
            'mux_ingest_url' => (string) config('services.mux.live.ingest_url', 'rtmps://global-live.mux.com:443/app'),
        ])->save();

        Log::info('Mux live stream ensured for module.', [
            'module_id' => $module->getKey(),
            'mux_live_stream_id' => $module->mux_live_stream_id,
            'mux_playback_id' => $module->mux_playback_id,
        ]);

        return $module->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchLiveStream(Module $module): array
    {
        $liveStreamId = $this->requireValue($module->mux_live_stream_id, 'modules.mux_live_stream_id');

        return $this->request()
            ->get("live-streams/{$liveStreamId}")
            ->throw()
            ->json('data') ?? [];
    }

    public function refreshBroadcastStatus(Module $module, ?LiveStreamSession $session = null): ?LiveStreamSession
    {
        if ($module->mux_live_stream_id === null || $session === null) {
            return $session;
        }

        try {
            $data = $this->fetchLiveStream($module);
        } catch (Throwable $exception) {
            Log::warning('Mux live stream status refresh failed.', [
                'module_id' => $module->getKey(),
                'session_id' => $session->getKey(),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return $session;
        }

        $status = (string) ($data['status'] ?? 'idle');
        $isLive = $this->statusIsLive($status);

        $session->forceFill([
            'mux_playback_id' => $module->mux_playback_id,
            'mux_broadcast_status' => $status,
            'mux_broadcast_started_at' => $isLive
                ? ($session->mux_broadcast_started_at ?? now())
                : $session->mux_broadcast_started_at,
            'mux_broadcast_ended_at' => ! $isLive && $session->mux_broadcast_started_at !== null
                ? now()
                : $session->mux_broadcast_ended_at,
        ])->save();

        return $session->refresh();
    }

    public function markBroadcastStarted(LiveStreamSession $session, Module $module): LiveStreamSession
    {
        $session->forceFill([
            'mux_playback_id' => $module->mux_playback_id,
            'mux_broadcast_status' => 'active',
            'mux_broadcast_started_at' => $session->mux_broadcast_started_at ?? now(),
            'mux_broadcast_ended_at' => null,
        ])->save();

        return $session->refresh();
    }

    public function endBroadcast(LiveStreamSession $session, Module $module): LiveStreamSession
    {
        if ($module->mux_live_stream_id !== null) {
            try {
                $this->request()->put("live-streams/{$module->mux_live_stream_id}/disable")->throw();
            } catch (Throwable $exception) {
                Log::warning('Mux live stream disable failed.', [
                    'module_id' => $module->getKey(),
                    'session_id' => $session->getKey(),
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);
            }
        }

        $session->forceFill([
            'mux_playback_id' => $module->mux_playback_id,
            'mux_broadcast_status' => 'ended',
            'mux_broadcast_ended_at' => now(),
        ])->save();

        return $session->refresh();
    }

    public function playbackUrl(?string $playbackId): ?string
    {
        if ($playbackId === null || $playbackId === '') {
            return null;
        }

        return sprintf('https://player.mux.com/%s', $playbackId);
    }

    public function statusIsLive(?string $status): bool
    {
        return in_array($status, ['active', 'connected'], true);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::API_BASE_URL)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth(
                $this->requireValue(config('services.mux.token_id'), 'services.mux.token_id'),
                $this->requireValue(config('services.mux.token_secret'), 'services.mux.token_secret'),
            )
            ->connectTimeout((int) config('services.mux.timeout.connect', 5))
            ->timeout((int) config('services.mux.timeout.request', 10))
            ->retry([200, 500, 1000], throw: true);
    }

    private function requireValue(?string $value, string $configKey): string
    {
        if ($value === null || $value === '') {
            throw new RuntimeException(sprintf('Missing Mux configuration value [%s].', $configKey));
        }

        return $value;
    }
}
