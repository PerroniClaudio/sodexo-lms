<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Services\MuxService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VideoModuleController extends Controller
{
    /**
     * Return a signed playback URL for the current user's video module.
     */
    public function signedPlayback(Request $request, Course $course, Module $module, MuxService $muxService): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);

        $enrollment = $this->resolveEnrollment($course);
        $video = $module->video;

        abort_if($video === null || $video->mux_playback_id === null || $video->mux_video_status !== 'ready', 404);

        $token = $muxService->generateJwtToken($video->mux_playback_id, time() + 3600, 'v');

        $progress = $this->resolveProgress($enrollment, $module);

        return response()->json([
            'playback_id' => $video->mux_playback_id,
            'token' => $token,
            'video_current_second' => $progress->video_current_second ?? 0,
        ]);
    }

    /**
     * Record video progress for the current module.
     */
    public function progress(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);

        $validated = $request->validate([
            'current_second' => ['required', 'integer', 'min:0'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        $enrollment = $this->resolveEnrollment($course);
        $progress = $this->resolveProgress($enrollment, $module);

        try {
            $progress->recordVideoProgress(
                $validated['current_second'],
                $validated['time_spent_seconds'] ?? 0,
            );
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark the video module as completed.
     */
    public function complete(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);

        $enrollment = $this->resolveEnrollment($course);
        $progress = $this->resolveProgress($enrollment, $module);

        try {
            $progress->markCompleted();
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true]);
    }

    private function resolveEnrollment(Course $course): CourseEnrollment
    {
        return CourseEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('course_id', $course->getKey())
            ->firstOrFail();
    }

    private function resolveProgress(CourseEnrollment $enrollment, Module $module): ModuleProgress
    {
        return ModuleProgress::query()
            ->where('course_user_id', $enrollment->getKey())
            ->where('module_id', $module->getKey())
            ->firstOrFail();
    }
}
