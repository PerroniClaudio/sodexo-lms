<?php

use App\Models\Video;

it('stores upload and asset errors received from Mux webhooks', function () {
    $uploadVideo = Video::factory()->create([
        'mux_upload_id' => 'upload-'.fake()->uuid(),
        'mux_asset_id' => null,
        'mux_video_status' => 'uploading',
        'mux_error' => null,
    ]);

    $this->postJson('/api/mux/webhook', [
        'type' => 'video.upload.errored',
        'data' => [
            'id' => $uploadVideo->mux_upload_id,
            'error' => [
                'type' => 'invalid_media',
                'message' => 'The uploaded file is not valid media.',
            ],
        ],
    ])->assertSuccessful();

    expect($uploadVideo->fresh())
        ->mux_video_status->toBe('errored')
        ->mux_error->toBe('invalid_media: The uploaded file is not valid media.');

    $assetVideo = Video::factory()->create([
        'mux_asset_id' => 'asset-'.fake()->uuid(),
        'mux_video_status' => 'processing',
        'mux_error' => null,
    ]);

    $this->postJson('/api/mux/webhook', [
        'type' => 'video.asset.errored',
        'data' => [
            'id' => $assetVideo->mux_asset_id,
            'errors' => [
                'type' => 'unsupported_codec',
                'messages' => ['The video codec is unsupported.'],
            ],
        ],
    ])->assertSuccessful();

    expect($assetVideo->fresh())
        ->mux_video_status->toBe('errored')
        ->mux_error->toBe('unsupported_codec: The video codec is unsupported.');
});
