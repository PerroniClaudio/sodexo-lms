<?php

namespace App\Http\Controllers\Api\Mux;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    /**
     * Gestisce i webhook Mux per aggiornare stato video
     */
    public function handle(Request $request)
    {
        $event = $request->input('type');
        $data = $request->input('data');
        if (! $event || ! $data) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        if ($event === 'video.upload.asset_created') {
            $uploadId = $data['upload_id'] ?? null;
            $assetId = $data['asset_id'] ?? null;
            if ($uploadId && $assetId) {
                $video = Video::where('mux_upload_id', $uploadId)->first();
                if ($video) {
                    $video->update([
                        'mux_asset_id' => $assetId,
                        'mux_video_status' => 'processing',
                    ]);
                }
            }
        }
        if ($event === 'video.asset.ready') {
            $assetId = $data['id'] ?? null;
            $playbackIds = $data['playback_ids'] ?? [];
            $playbackId = collect($playbackIds)->firstWhere('policy', 'signed')['id'] ?? null;
            if ($assetId && $playbackId) {
                $video = Video::where('mux_asset_id', $assetId)->first();
                if ($video) {
                    $video->update([
                        'mux_playback_id' => $playbackId,
                        'mux_video_status' => 'ready',
                    ]);
                }
            }
        }
        if ($event === 'video.upload.errored') {
            $uploadId = $data['id'] ?? null;
            if ($uploadId) {
                $video = Video::where('mux_upload_id', $uploadId)->first();
                if ($video) {
                    $video->update([
                        'mux_video_status' => 'failed',
                    ]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
