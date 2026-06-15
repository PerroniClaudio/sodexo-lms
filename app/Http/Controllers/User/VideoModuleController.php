<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoTrackingEventRequest;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleTeachingMaterial;
use App\Models\VideoExercise;
use App\Models\VideoExerciseMaterial;
use App\Models\VideoExerciseSubmission;
use App\Models\VideoTrackingEvent;
use App\Services\MuxService;
use App\Services\VideoTrackingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
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

    public function exercises(Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isVideo(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, Response::HTTP_FORBIDDEN);

        $module->load([
            'videoExercises.materials',
            'videoExercises.questions',
            'videoExercises.submissions' => fn ($query) => $query
                ->where('course_user_id', $enrollment->getKey())
                ->with('answers'),
        ]);

        return response()->json([
            'exercises' => $module->videoExercises->map(fn (VideoExercise $exercise): array => $this->exercisePayload($course, $module, $exercise, $enrollment))->values(),
        ]);
    }

    public function autosaveExercise(Request $request, Course $course, Module $module, VideoExercise $videoExercise): JsonResponse
    {
        $this->ensureExerciseForUser($course, $module, $videoExercise);

        $validated = $request->validate([
            'elapsed_seconds' => ['required', 'integer', 'min:0'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string'],
            'downloaded_material_ids' => ['nullable', 'array'],
            'downloaded_material_ids.*' => ['integer'],
        ]);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, Response::HTTP_FORBIDDEN);

        $submission = $this->saveExerciseDraft($videoExercise, $enrollment, $validated);

        return response()->json([
            'submission' => $this->submissionPayload($submission),
        ]);
    }

    public function submitExercise(Request $request, Course $course, Module $module, VideoExercise $videoExercise): JsonResponse
    {
        $this->ensureExerciseForUser($course, $module, $videoExercise);

        $validated = $request->validate([
            'elapsed_seconds' => ['required', 'integer', 'min:0'],
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string'],
            'downloaded_material_ids' => ['nullable', 'array'],
            'downloaded_material_ids.*' => ['integer'],
        ]);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, Response::HTTP_FORBIDDEN);

        $submission = $this->saveExerciseDraft($videoExercise, $enrollment, $validated);
        $submission->loadMissing('answers', 'exercise.questions');

        $errors = $this->exerciseSubmissionErrors($submission);

        if ($errors !== []) {
            return response()->json([
                'message' => __('Esercitazione non ancora completabile.'),
                'errors' => $errors,
                'submission' => $this->submissionPayload($submission),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $submission->forceFill([
            'status' => VideoExerciseSubmission::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        return response()->json([
            'submission' => $this->submissionPayload($submission->fresh('answers')),
            'report_url' => route('user.courses.modules.video.exercises.report', [$course, $module, $videoExercise]),
            'self_evaluation_url' => $videoExercise->self_evaluation_path
                ? route('user.courses.modules.video.exercises.self-evaluation', [$course, $module, $videoExercise])
                : null,
        ]);
    }

    public function downloadExerciseMaterial(Course $course, Module $module, VideoExercise $videoExercise, VideoExerciseMaterial $material): StreamedResponse
    {
        $this->ensureExerciseForUser($course, $module, $videoExercise);
        abort_unless($material->video_exercise_id === $videoExercise->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($material->type === VideoExerciseMaterial::TYPE_FILE && $material->disk && $material->path, Response::HTTP_NOT_FOUND);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, Response::HTTP_FORBIDDEN);

        $submission = VideoExerciseSubmission::query()->firstOrCreate(
            [
                'video_exercise_id' => $videoExercise->getKey(),
                'course_user_id' => $enrollment->getKey(),
            ],
            [
                'user_id' => $enrollment->user_id,
                'status' => VideoExerciseSubmission::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ],
        );

        $submission->forceFill([
            'downloaded_material_ids' => collect($submission->downloaded_material_ids ?? [])
                ->push($material->getKey())
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
            'started_at' => $submission->started_at ?? now(),
        ])->save();

        $disk = Storage::disk($material->disk);
        abort_unless($disk->exists($material->path), Response::HTTP_NOT_FOUND);

        return $disk->download($material->path, $material->original_name ?? $material->title, [
            'Content-Type' => $material->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function downloadSelfEvaluation(Course $course, Module $module, VideoExercise $videoExercise): StreamedResponse
    {
        $this->ensureExerciseForUser($course, $module, $videoExercise);
        abort_unless($videoExercise->self_evaluation_disk && $videoExercise->self_evaluation_path, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk($videoExercise->self_evaluation_disk);
        abort_unless($disk->exists($videoExercise->self_evaluation_path), Response::HTTP_NOT_FOUND);

        return $disk->download($videoExercise->self_evaluation_path, $videoExercise->self_evaluation_original_name ?? 'autovalutazione.pdf', [
            'Content-Type' => $videoExercise->self_evaluation_mime_type ?: 'application/pdf',
        ]);
    }

    public function downloadExerciseReport(Course $course, Module $module, VideoExercise $videoExercise)
    {
        $this->ensureExerciseForUser($course, $module, $videoExercise);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, Response::HTTP_FORBIDDEN);

        $submission = $videoExercise->submissions()
            ->where('course_user_id', $enrollment->getKey())
            ->where('status', VideoExerciseSubmission::STATUS_COMPLETED)
            ->with(['answers.question', 'user'])
            ->first();

        abort_unless($submission !== null, Response::HTTP_NOT_FOUND);

        $videoExercise->loadMissing('questions');

        return Pdf::view('pdf.video-exercise-report', [
            'course' => $course,
            'module' => $module,
            'exercise' => $videoExercise,
            'submission' => $submission,
        ])
            ->driver('dompdf')
            ->download(Str::slug($course->title.' '.$module->title.' '.$videoExercise->title).'-resoconto.pdf');
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

    private function ensureExerciseForUser(Course $course, Module $module, VideoExercise $videoExercise): void
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($module->isVideo(), Response::HTTP_NOT_FOUND);
        abort_unless($videoExercise->module_id === $module->getKey(), Response::HTTP_NOT_FOUND);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, Response::HTTP_FORBIDDEN);

        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, Response::HTTP_NOT_FOUND);
        abort_if($progress->status === ModuleProgress::STATUS_LOCKED, Response::HTTP_FORBIDDEN);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function saveExerciseDraft(VideoExercise $exercise, CourseEnrollment $enrollment, array $validated): VideoExerciseSubmission
    {
        return DB::transaction(function () use ($exercise, $enrollment, $validated): VideoExerciseSubmission {
            $submission = VideoExerciseSubmission::query()->firstOrCreate(
                [
                    'video_exercise_id' => $exercise->getKey(),
                    'course_user_id' => $enrollment->getKey(),
                ],
                [
                    'user_id' => $enrollment->user_id,
                    'status' => VideoExerciseSubmission::STATUS_IN_PROGRESS,
                    'started_at' => now(),
                ],
            );

            if ($submission->status !== VideoExerciseSubmission::STATUS_COMPLETED) {
                $submission->forceFill([
                    'elapsed_seconds' => max($submission->elapsed_seconds ?? 0, (int) $validated['elapsed_seconds']),
                    'downloaded_material_ids' => $this->normalizedDownloadedMaterialIds($exercise, $validated['downloaded_material_ids'] ?? [], $submission),
                    'started_at' => $submission->started_at ?? now(),
                ])->save();
            }

            foreach (($validated['answers'] ?? []) as $questionId => $answerText) {
                $questionBelongsToExercise = $exercise->questions()
                    ->whereKey($questionId)
                    ->exists();

                if (! $questionBelongsToExercise) {
                    continue;
                }

                $submission->answers()->updateOrCreate(
                    ['video_exercise_question_id' => (int) $questionId],
                    ['answer_text' => (string) $answerText],
                );
            }

            return $submission->fresh(['answers', 'exercise.questions']);
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function exerciseSubmissionErrors(VideoExerciseSubmission $submission): array
    {
        $errors = [];
        $exercise = $submission->exercise;

        if ($submission->elapsed_seconds < $exercise->minimum_seconds) {
            $errors['elapsed_seconds'] = [__('Devi attendere ancora prima di inviare.')];
        }

        $requiredMaterialIds = $exercise->materials
            ->where('type', VideoExerciseMaterial::TYPE_FILE)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $downloadedMaterialIds = collect($submission->downloaded_material_ids ?? [])
            ->map(fn ($id): int => (int) $id);

        if ($requiredMaterialIds->diff($downloadedMaterialIds)->isNotEmpty()) {
            $errors['materials'] = [__('Devi scaricare tutti i file della documentazione di supporto prima di procedere.')];
        }

        $answersByQuestionId = $submission->answers->keyBy('video_exercise_question_id');

        foreach ($exercise->questions as $question) {
            $answer = trim((string) ($answersByQuestionId->get($question->getKey())?->answer_text ?? ''));

            if (mb_strlen($answer) < $question->minimum_characters) {
                $errors['answers.'.$question->getKey()] = [__('La risposta deve contenere almeno :count caratteri.', ['count' => $question->minimum_characters])];
            }
        }

        return $errors;
    }

    private function exercisePayload(Course $course, Module $module, VideoExercise $exercise, CourseEnrollment $enrollment): array
    {
        $submission = $exercise->submissions->first();
        $downloadedMaterialIds = collect($submission?->downloaded_material_ids ?? [])
            ->map(fn ($id): int => (int) $id);

        return [
            'id' => $exercise->getKey(),
            'title' => $exercise->title,
            'appears_at_seconds' => $exercise->appears_at_seconds,
            'minimum_seconds' => $exercise->minimum_seconds,
            'support_text_html' => $exercise->support_text_html,
            'youtube_embed_url' => $exercise->youtubeEmbedUrl(),
            'materials' => $exercise->materials->map(fn (VideoExerciseMaterial $material): array => [
                'id' => $material->getKey(),
                'type' => $material->type,
                'title' => $material->title,
                'name' => $material->original_name ?? $material->title,
                'downloaded' => $material->type === VideoExerciseMaterial::TYPE_FILE
                    ? $downloadedMaterialIds->contains((int) $material->getKey())
                    : true,
                'url' => $material->type === VideoExerciseMaterial::TYPE_FILE
                    ? route('user.courses.modules.video.exercises.materials.download', [$course, $module, $exercise, $material])
                    : null,
                'youtube_embed_url' => $material->type === VideoExerciseMaterial::TYPE_VIDEO ? $material->youtubeEmbedUrl() : null,
                'content_html' => $material->type === VideoExerciseMaterial::TYPE_TEXT ? $material->content_html : null,
            ])->values(),
            'questions' => $exercise->questions->map(fn ($question): array => [
                'id' => $question->getKey(),
                'text' => $question->text,
                'minimum_characters' => $question->minimum_characters,
            ])->values(),
            'submission' => $submission ? $this->submissionPayload($submission) : null,
            'autosave_url' => route('user.courses.modules.video.exercises.autosave', [$course, $module, $exercise]),
            'submit_url' => route('user.courses.modules.video.exercises.submit', [$course, $module, $exercise]),
            'report_url' => $submission?->status === VideoExerciseSubmission::STATUS_COMPLETED
                ? route('user.courses.modules.video.exercises.report', [$course, $module, $exercise])
                : null,
            'self_evaluation_url' => $exercise->self_evaluation_path
                ? route('user.courses.modules.video.exercises.self-evaluation', [$course, $module, $exercise])
                : null,
            'self_evaluation_name' => $exercise->self_evaluation_original_name,
        ];
    }

    private function submissionPayload(VideoExerciseSubmission $submission): array
    {
        return [
            'status' => $submission->status,
            'elapsed_seconds' => $submission->elapsed_seconds,
            'downloaded_material_ids' => collect($submission->downloaded_material_ids ?? [])
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
            'answers' => $submission->answers
                ->mapWithKeys(fn ($answer): array => [$answer->video_exercise_question_id => $answer->answer_text])
                ->all(),
        ];
    }

    /**
     * @param  array<int, mixed>  $downloadedMaterialIds
     * @return array<int, int>
     */
    private function normalizedDownloadedMaterialIds(VideoExercise $exercise, array $downloadedMaterialIds, VideoExerciseSubmission $submission): array
    {
        $allowedMaterialIds = $exercise->materials
            ->where('type', VideoExerciseMaterial::TYPE_FILE)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        return collect($submission->downloaded_material_ids ?? [])
            ->merge($downloadedMaterialIds)
            ->map(fn ($id): int => (int) $id)
            ->intersect($allowedMaterialIds)
            ->unique()
            ->values()
            ->all();
    }
}
