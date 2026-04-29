<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\MuxService;
use Illuminate\Console\Command;

class SyncMuxVideosStatus extends Command
{
    protected $signature = 'videos:sync-mux-status';
    protected $description = 'Sincronizza lo stato dei video con Mux per quelli non ancora terminati';

    public function handle(MuxService $muxService)
    {
        // 1. Aggiorna mux_asset_id per i video che hanno solo mux_upload_id
        $updatedAssetId = 0;
        $videosMissingAsset = Video::whereNull('mux_asset_id')
            ->whereNotNull('mux_upload_id')
            ->get();
        foreach ($videosMissingAsset as $video) {
            $this->info("[DEBUG] Chiedo asset_id per upload_id: {$video->mux_upload_id}");
            $muxUploadData = $muxService->getUploadData($video->mux_upload_id);
            $assetId = $muxUploadData['asset_id'] ?? null;
            $playbackId = $muxUploadData['playback_id'] ?? null;
            if (!$assetId) {
                $this->warn("[DEBUG] Nessun asset_id trovato per upload_id: {$video->mux_upload_id}");
            } else {
                $this->info("[DEBUG] Trovato asset_id: $assetId per upload_id: {$video->mux_upload_id}");
                $video->mux_asset_id = $assetId;
                if ($playbackId) {
                    $this->info("[DEBUG] Trovato playback_id: $playbackId per asset_id: $assetId");
                    $video->mux_playback_id = $playbackId;
                }
                $video->save();
                $updatedAssetId++;
            }
        }

        // 2. Aggiorna lo stato dei video che hanno asset_id
        $videos = Video::whereNotIn('mux_video_status', ['ready', 'errored'])
            ->whereNotNull('mux_asset_id')
            ->get();

        $count = 0;
        foreach ($videos as $video) {
            $muxStatus = $muxService->getAssetStatus($video->mux_asset_id);
            if ($muxStatus && $muxStatus !== $video->mux_video_status) {
                $video->mux_video_status = $muxStatus;
                $video->save();
                $count++;
            }
        }

        $this->info("Aggiornati $count video. AssetId aggiornati: $updatedAssetId");
        return 0;
    }
}
