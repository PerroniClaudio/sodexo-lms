<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoTrackingEventRequest;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleTeachingMaterial;
use App\Models\VideoTrackingEvent;
use App\Services\MuxService;
use App\Services\VideoTrackingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoModuleController extends Controller
{
    /**
     * Return a signed playback URL for the current user's video module.
     */
    public function signedPlayback(Request $request, Course $course, Module $module, MuxService $muxService, VideoTrackingService $videoTrackingService): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);
        $video = $module->video;

        abort_if($video === null || $video->mux_playback_id === null || $video->mux_video_status !== 'ready', 404);

        $token = $muxService->generateJwtToken($video->mux_playback_id, time() + 3600, 'v');

        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);

        if ($video->duration_seconds === null && $video->mux_asset_id !== null) {
            $durationRaw = $muxService->getAssetDuration($video->mux_asset_id);

            if (is_numeric($durationRaw)) {
                $video->forceFill([
                    'duration_seconds' => (int) round((float) $durationRaw),
                ])->save();
                $video->refresh();
            }
        }

        $state = $videoTrackingService->state($progress);

        return response()->json([
            'playback_id' => $video->mux_playback_id,
            'token' => $token,
            ...$state,
        ]);
    }

    public function trackingState(Course $course, Module $module, VideoTrackingService $videoTrackingService): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);
        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);

        return response()->json($videoTrackingService->state($progress));
    }

    public function trackingEvent(
        StoreVideoTrackingEventRequest $request,
        Course $course,
        Module $module,
        VideoTrackingService $videoTrackingService,
    ): JsonResponse {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);
        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);

        try {
            return response()->json($videoTrackingService->recordEvent($progress, $request->validated()));
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Record video progress for the current module.
     */
    public function progress(Request $request, Course $course, Module $module, VideoTrackingService $videoTrackingService): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);

        $validated = $request->validate([
            'current_second' => ['required', 'integer', 'min:0'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);
        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);

        try {
            $result = $videoTrackingService->recordEvent($progress, [
                'session_uuid' => (string) Str::uuid(),
                'event_uuid' => (string) Str::uuid(),
                'event_type' => 'heartbeat',
                'occurred_at' => now()->toIso8601String(),
                'position_second' => $validated['current_second'],
                'max_second_client' => $validated['current_second'],
                'delta_watched_seconds' => $validated['time_spent_seconds'] ?? 0,
                'player_ended' => false,
            ]);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'resume_second' => $result['resume_second'],
            'max_allowed_second' => $result['max_allowed_second'],
            'is_completed' => $result['is_completed'],
        ]);
    }

    /**
     * Mark the video module as completed.
     */
    public function complete(Request $request, Course $course, Module $module, VideoTrackingService $videoTrackingService): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);
        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);

        try {
            $result = $videoTrackingService->recordEvent($progress, [
                'session_uuid' => (string) Str::uuid(),
                'event_uuid' => (string) Str::uuid(),
                'event_type' => 'ended',
                'occurred_at' => now()->toIso8601String(),
                'position_second' => (int) $request->integer('current_second', $progress->video_current_second ?? 0),
                'max_second_client' => (int) $request->integer('current_second', $progress->video_max_second ?? 0),
                'delta_watched_seconds' => 0,
                'player_ended' => true,
            ]);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => $result['is_completed'],
            'is_completed' => $result['is_completed'],
            'resume_second' => $result['resume_second'],
            'max_allowed_second' => $result['max_allowed_second'],
        ]);
    }

    public function downloadTeachingMaterial(Course $course, Module $module, ModuleTeachingMaterial $moduleTeachingMaterial): StreamedResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);
        abort_unless($moduleTeachingMaterial->module_id === $module->getKey(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, Response::HTTP_FORBIDDEN);

        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, Response::HTTP_NOT_FOUND);
        abort_if($progress->status === ModuleProgress::STATUS_LOCKED, Response::HTTP_FORBIDDEN);

        $disk = Storage::disk($moduleTeachingMaterial->disk);
        abort_unless($disk->exists($moduleTeachingMaterial->path), Response::HTTP_NOT_FOUND);

        VideoTrackingEvent::query()->create([
            'module_progress_id' => $progress->getKey(),
            'course_user_id' => $enrollment->getKey(),
            'module_id' => $module->getKey(),
            'video_id' => $module->video_id,
            'user_id' => Auth::id(),
            'session_uuid' => (string) Str::uuid(),
            'event_uuid' => (string) Str::uuid(),
            'event_type' => VideoTrackingEvent::TYPE_TEACHING_MATERIAL_DOWNLOADED,
            'occurred_at' => now(),
            'client_payload' => [
                'material_id' => $moduleTeachingMaterial->getKey(),
                'original_name' => $moduleTeachingMaterial->original_name,
                'mime_type' => $moduleTeachingMaterial->mime_type,
                'size_bytes' => $moduleTeachingMaterial->size_bytes,
            ],
        ]);

        return $disk->download(
            $moduleTeachingMaterial->path,
            $moduleTeachingMaterial->original_name,
            ['Content-Type' => $moduleTeachingMaterial->mime_type ?: 'application/octet-stream'],
        );
    }

    private function resolveEnrollment(Course $course): ?CourseEnrollment
    {
        return CourseEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('course_id', $course->getKey())
            ->first();
    }

    private function resolveProgress(CourseEnrollment $enrollment, Module $module): ?ModuleProgress
    {
        return ModuleProgress::query()
            ->where('course_user_id', $enrollment->getKey())
            ->where('module_id', $module->getKey())
            ->first();
    }
}
