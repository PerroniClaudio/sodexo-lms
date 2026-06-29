<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\VideoExercise;
use App\Models\VideoExerciseMaterial;
use App\Models\VideoExerciseQuestion;
use App\Services\VideoExerciseActivityExporter;
use App\Services\VideoExerciseResponsesExporter;
use App\Support\CloudStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoExerciseController extends Controller
{
    public function create(Course $course, Module $module): View
    {
        $this->ensureVideoModule($course, $module);

        return view('admin.module.video-exercises.create', compact('course', 'module'));
    }

    public function store(Request $request, Course $course, Module $module): RedirectResponse
    {
        $this->ensureVideoModule($course, $module);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $exercise = $module->videoExercises()->create([
            'title' => $validated['title'],
            'appears_at_seconds' => 0,
            'minimum_seconds' => 0,
        ]);

        return redirect()
            ->route('admin.courses.modules.video-exercises.edit', [$course, $module, $exercise])
            ->with('status', __('Esercitazione creata con successo.'));
    }

    public function edit(Course $course, Module $module, VideoExercise $videoExercise): View
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);

        $videoExercise->load(['materials', 'questions']);

        return view('admin.module.video-exercises.edit', compact('course', 'module', 'videoExercise'));
    }

    public function exportResponses(
        Course $course,
        Module $module,
        VideoExercise $videoExercise,
        VideoExerciseResponsesExporter $exporter,
    ): StreamedResponse {
        $this->ensureVideoExercise($course, $module, $videoExercise);

        $contents = $exporter->buildWorkbookContents($videoExercise);
        $fileName = Str::slug($course->title.' '.$module->title.' '.$videoExercise->title).'-risposte.xlsx';

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportActivity(
        Course $course,
        Module $module,
        VideoExercise $videoExercise,
        VideoExerciseActivityExporter $exporter,
    ): StreamedResponse {
        $this->ensureVideoExercise($course, $module, $videoExercise);

        $contents = $exporter->buildWorkbookContents($videoExercise);
        $fileName = Str::slug($course->title.' '.$module->title.' '.$videoExercise->title).'-attivita-utenti.xlsx';

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function update(Request $request, Course $course, Module $module, VideoExercise $videoExercise): RedirectResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'appears_at' => ['required', 'regex:/^\d{2,}:[0-5]\d:[0-5]\d$/'],
            'minimum_time' => ['required', 'regex:/^\d{2,}:[0-5]\d$/'],
            'self_evaluation' => ['nullable', 'file', File::types(['pdf'])->max(1024 * 20)],
        ]);

        DB::transaction(function () use ($request, $videoExercise, $validated): void {
            $videoExercise->update([
                'title' => $validated['title'],
                'appears_at_seconds' => $this->hmsToSeconds($validated['appears_at']),
                'minimum_seconds' => $this->hmToSeconds($validated['minimum_time']),
            ]);

            $this->storeSelfEvaluation($request, $videoExercise);
        });

        return redirect()
            ->route('admin.courses.modules.video-exercises.edit', [$course, $module, $videoExercise])
            ->with('status', __('Esercitazione aggiornata con successo.'));
    }

    public function destroy(Course $course, Module $module, VideoExercise $videoExercise): RedirectResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);

        foreach ($videoExercise->materials as $material) {
            if ($material->type === VideoExerciseMaterial::TYPE_FILE && $material->disk && $material->path) {
                Storage::disk($material->disk)->delete($material->path);
            }
        }

        if ($videoExercise->self_evaluation_disk && $videoExercise->self_evaluation_path) {
            Storage::disk($videoExercise->self_evaluation_disk)->delete($videoExercise->self_evaluation_path);
        }

        $videoExercise->delete();

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('Esercitazione eliminata con successo.'));
    }

    public function storeMaterial(Request $request, Course $course, Module $module, VideoExercise $videoExercise): RedirectResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:file,video,text'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required_if:type,file', 'nullable', 'file', File::types(['pdf', 'doc', 'docx'])->max(1024 * 20)],
            'youtube_url' => ['required_if:type,video', 'nullable', 'url', 'max:255'],
            'content_html' => ['required_if:type,text', 'nullable', 'string'],
        ]);

        if ($validated['type'] === VideoExerciseMaterial::TYPE_FILE) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
            $storedPath = $file->storeAs(
                'modules/'.$module->getKey().'/video-exercises/'.$videoExercise->getKey().'/materials',
                Str::uuid().'.'.$extension,
                CloudStorage::disk(),
            );

            $videoExercise->materials()->create([
                'uploaded_by' => $request->user()?->getKey(),
                'type' => VideoExerciseMaterial::TYPE_FILE,
                'title' => $validated['title'],
                'disk' => CloudStorage::disk(),
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'uploaded_at' => now(),
            ]);
        } else {
            $videoExercise->materials()->create([
                'uploaded_by' => $request->user()?->getKey(),
                'type' => $validated['type'],
                'title' => $validated['title'],
                'disk' => 'inline',
                'path' => 'inline:'.Str::uuid(),
                'original_name' => $validated['title'],
                'youtube_url' => $validated['youtube_url'] ?? null,
                'content_html' => $validated['content_html'] ?? null,
                'uploaded_at' => now(),
            ]);
        }

        return redirect()
            ->route('admin.courses.modules.video-exercises.edit', [$course, $module, $videoExercise])
            ->with('status', __('Materiale esercitazione aggiunto con successo.'));
    }

    public function downloadMaterial(Course $course, Module $module, VideoExercise $videoExercise, VideoExerciseMaterial $material): StreamedResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);
        abort_unless($material->video_exercise_id === $videoExercise->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($material->type === VideoExerciseMaterial::TYPE_FILE && $material->disk && $material->path, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk($material->disk);
        abort_unless($disk->exists($material->path), Response::HTTP_NOT_FOUND);

        return $disk->download($material->path, $material->original_name ?? $material->title, [
            'Content-Type' => $material->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function updateMaterial(Request $request, Course $course, Module $module, VideoExercise $videoExercise, VideoExerciseMaterial $material): RedirectResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);
        abort_unless($material->video_exercise_id === $videoExercise->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless(in_array($material->type, [VideoExerciseMaterial::TYPE_VIDEO, VideoExerciseMaterial::TYPE_TEXT], true), Response::HTTP_NOT_FOUND);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
        ];

        if ($material->type === VideoExerciseMaterial::TYPE_VIDEO) {
            $rules['youtube_url'] = ['required', 'url', 'max:255'];
        }

        if ($material->type === VideoExerciseMaterial::TYPE_TEXT) {
            $rules['content_html'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        $material->update([
            'title' => $validated['title'],
            'original_name' => $validated['title'],
            'youtube_url' => $material->type === VideoExerciseMaterial::TYPE_VIDEO ? ($validated['youtube_url'] ?? null) : null,
            'content_html' => $material->type === VideoExerciseMaterial::TYPE_TEXT ? ($validated['content_html'] ?? null) : null,
        ]);

        return redirect()
            ->route('admin.courses.modules.video-exercises.edit', [$course, $module, $videoExercise])
            ->with('status', __('Materiale esercitazione aggiornato con successo.'));
    }

    public function destroyMaterial(Course $course, Module $module, VideoExercise $videoExercise, VideoExerciseMaterial $material): RedirectResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);
        abort_unless($material->video_exercise_id === $videoExercise->getKey(), Response::HTTP_NOT_FOUND);

        if ($material->type === VideoExerciseMaterial::TYPE_FILE && $material->disk && $material->path) {
            Storage::disk($material->disk)->delete($material->path);
        }

        $material->delete();

        return redirect()
            ->route('admin.courses.modules.video-exercises.edit', [$course, $module, $videoExercise])
            ->with('status', __('Materiale esercitazione eliminato con successo.'));
    }

    public function storeQuestion(Request $request, Course $course, Module $module, VideoExercise $videoExercise): RedirectResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);

        $validated = $request->validate([
            'text' => ['required', 'string'],
            'minimum_characters' => ['required', 'integer', 'min:1'],
        ]);

        $videoExercise->questions()->create([
            ...$validated,
            'order' => ((int) $videoExercise->questions()->max('order')) + 1,
        ]);

        return redirect()
            ->route('admin.courses.modules.video-exercises.edit', [$course, $module, $videoExercise])
            ->with('status', __('Domanda aggiunta con successo.'));
    }

    public function updateQuestion(Request $request, Course $course, Module $module, VideoExercise $videoExercise, VideoExerciseQuestion $question): RedirectResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);
        abort_unless($question->video_exercise_id === $videoExercise->getKey(), Response::HTTP_NOT_FOUND);

        $validated = $request->validate([
            'text' => ['required', 'string'],
            'minimum_characters' => ['required', 'integer', 'min:1'],
        ]);

        $question->update($validated);

        return redirect()
            ->route('admin.courses.modules.video-exercises.edit', [$course, $module, $videoExercise])
            ->with('status', __('Domanda aggiornata con successo.'));
    }

    public function destroyQuestion(Course $course, Module $module, VideoExercise $videoExercise, VideoExerciseQuestion $question): RedirectResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);
        abort_unless($question->video_exercise_id === $videoExercise->getKey(), Response::HTTP_NOT_FOUND);

        $question->delete();

        return redirect()
            ->route('admin.courses.modules.video-exercises.edit', [$course, $module, $videoExercise])
            ->with('status', __('Domanda eliminata con successo.'));
    }

    public function destroySelfEvaluation(Course $course, Module $module, VideoExercise $videoExercise): RedirectResponse
    {
        $this->ensureVideoExercise($course, $module, $videoExercise);

        if ($videoExercise->self_evaluation_disk && $videoExercise->self_evaluation_path) {
            Storage::disk($videoExercise->self_evaluation_disk)->delete($videoExercise->self_evaluation_path);
        }

        $videoExercise->forceFill([
            'self_evaluation_disk' => null,
            'self_evaluation_path' => null,
            'self_evaluation_original_name' => null,
            'self_evaluation_mime_type' => null,
            'self_evaluation_size_bytes' => null,
        ])->save();

        return redirect()
            ->route('admin.courses.modules.video-exercises.edit', [$course, $module, $videoExercise])
            ->with('status', __('Documento di autovalutazione eliminato con successo.'));
    }

    private function storeSelfEvaluation(Request $request, VideoExercise $exercise): void
    {
        if (! $request->hasFile('self_evaluation')) {
            return;
        }

        if ($exercise->self_evaluation_disk && $exercise->self_evaluation_path) {
            Storage::disk($exercise->self_evaluation_disk)->delete($exercise->self_evaluation_path);
        }

        $file = $request->file('self_evaluation');
        $storedPath = $file->storeAs(
            'modules/'.$exercise->module_id.'/video-exercises/'.$exercise->getKey().'/self-evaluation',
            Str::uuid().'.pdf',
            self::DISK,
        );

        $exercise->forceFill([
            'self_evaluation_disk' => self::DISK,
            'self_evaluation_path' => $storedPath,
            'self_evaluation_original_name' => $file->getClientOriginalName(),
            'self_evaluation_mime_type' => $file->getClientMimeType() ?: 'application/pdf',
            'self_evaluation_size_bytes' => $file->getSize() ?: 0,
        ])->save();
    }

    private function ensureVideoModule(Course $course, Module $module): void
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($module->isVideo(), Response::HTTP_NOT_FOUND);
    }

    private function ensureVideoExercise(Course $course, Module $module, VideoExercise $videoExercise): void
    {
        $this->ensureVideoModule($course, $module);
        abort_unless($videoExercise->module_id === $module->getKey(), Response::HTTP_NOT_FOUND);
    }

    private function hmsToSeconds(string $value): int
    {
        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $value));

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    private function hmToSeconds(string $value): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $value));

        return ($hours * 3600) + ($minutes * 60);
    }
}
