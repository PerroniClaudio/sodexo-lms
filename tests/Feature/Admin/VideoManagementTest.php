<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\Video;
use App\Services\MuxService;

use function Pest\Laravel\mock;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('creates a direct upload from metadata without sending the video through Laravel', function () {
    $title = 'Direct upload '.fake()->uuid();

    mock(MuxService::class)
        ->shouldReceive('createDirectUpload')
        ->once()
        ->with('large-video.mp4')
        ->andReturn([
            'url' => 'https://storage.mux.test/direct-upload',
            'upload_id' => 'mux-upload-'.fake()->uuid(),
        ]);

    $this->postJson(route('admin.videos.store'), [
        'title' => $title,
        'description' => 'Video grande',
        'video_filename' => 'large-video.mp4',
    ])->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('upload_url', 'https://storage.mux.test/direct-upload');

    expect(Video::query()->where('title', $title)->exists())->toBeTrue();
});

it('shows the admin video edit page', function () {
    $video = Video::factory()->create([
        'title' => 'Video sicurezza',
        'description' => 'Descrizione video',
        'mux_playback_id' => 'playback-id-test',
        'mux_video_status' => 'ready',
    ]);

    $response = $this->get(route('admin.videos.edit', $video));

    $response->assertOk()
        ->assertSeeText('Modifica video')
        ->assertSee('value="Video sicurezza"', false)
        ->assertSeeText('Descrizione video')
        ->assertSeeText('Guarda anteprima')
        ->assertSee('https://stream.mux.com/${data.playback_id}.m3u8?token=${data.token}', false)
        ->assertSeeText('Salva video');
});

it('shows the admin video library page with mux preview source urls', function () {
    $video = Video::factory()->create([
        'title' => 'Video libreria',
        'mux_playback_id' => 'playback-id-library',
        'mux_video_status' => 'ready',
    ]);

    $response = $this->get(route('admin.videos.index'));

    $response->assertOk()
        ->assertSeeText('Libreria Video')
        ->assertSeeText('Video libreria')
        ->assertSee('https://stream.mux.com/${playbackId}.m3u8?token=${token}', false);
});

it('shows the admin video edit page as locked when the video is already used by a module', function () {
    $course = Course::factory()->create();
    $video = Video::factory()->create([
        'title' => 'Video assegnato',
    ]);

    Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'belongsTo' => (string) $course->getKey(),
        'video_id' => $video->getKey(),
    ]);

    $response = $this->get(route('admin.videos.edit', $video));

    $response->assertOk()
        ->assertSeeText('Questo video è già utilizzato in uno o più moduli. Per evitare incoerenze non può essere modificato o sostituito.')
        ->assertSee('disabled', false);
});

it('prevents updating a video that is already used by a module', function () {
    $course = Course::factory()->create();
    $video = Video::factory()->create([
        'title' => 'Video bloccato',
        'description' => 'Prima descrizione',
    ]);

    Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'belongsTo' => (string) $course->getKey(),
        'video_id' => $video->getKey(),
    ]);

    $response = $this->put(route('admin.videos.update', $video), [
        'title' => 'Titolo cambiato',
        'description' => 'Descrizione cambiata',
    ]);

    $response
        ->assertRedirect(route('admin.videos.edit', $video))
        ->assertSessionHas('error', 'Non è possibile modificare un video già utilizzato in uno o più moduli.');

    expect($video->fresh()->title)->toBe('Video bloccato')
        ->and($video->fresh()->description)->toBe('Prima descrizione');
});
