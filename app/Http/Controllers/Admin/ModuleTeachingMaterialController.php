<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTeachingMaterial;
use App\Support\CloudStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModuleTeachingMaterialController extends Controller
{
    public function store(Request $request, Course $course, Module $module): RedirectResponse
    {
        $this->ensureVideoModule($course, $module);

        $validated = $request->validate([
            'materials' => ['required', 'array', 'min:1'],
            'materials.*' => [
                'required',
                'file',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'pptx'])->max(1024 * 20),
            ],
        ]);

        foreach ($validated['materials'] as $file) {
            $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
            $storedPath = $file->storeAs(
                'modules/'.$module->getKey().'/teaching-materials',
                Str::uuid().'.'.$extension,
                CloudStorage::disk(),
            );

            $module->teachingMaterials()->create([
                'uploaded_by' => $request->user()?->getKey(),
                'disk' => CloudStorage::disk(),
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'uploaded_at' => now(),
            ]);
        }

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('Materiale didattico caricato con successo.'));
    }

    public function download(Course $course, Module $module, ModuleTeachingMaterial $moduleTeachingMaterial): StreamedResponse
    {
        $this->ensureVideoModule($course, $module);
        $this->ensureMaterialBelongsToModule($module, $moduleTeachingMaterial);

        $disk = Storage::disk($moduleTeachingMaterial->disk);
        abort_unless($disk->exists($moduleTeachingMaterial->path), Response::HTTP_NOT_FOUND);

        return $disk->download(
            $moduleTeachingMaterial->path,
            $moduleTeachingMaterial->original_name,
            ['Content-Type' => $moduleTeachingMaterial->mime_type ?: 'application/octet-stream'],
        );
    }

    public function destroy(Course $course, Module $module, ModuleTeachingMaterial $moduleTeachingMaterial): RedirectResponse
    {
        $this->ensureVideoModule($course, $module);
        $this->ensureMaterialBelongsToModule($module, $moduleTeachingMaterial);

        Storage::disk($moduleTeachingMaterial->disk)->delete($moduleTeachingMaterial->path);
        $moduleTeachingMaterial->delete();

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('Materiale didattico eliminato con successo.'));
    }

    private function ensureVideoModule(Course $course, Module $module): void
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($module->isVideo(), Response::HTTP_NOT_FOUND);
    }

    private function ensureMaterialBelongsToModule(Module $module, ModuleTeachingMaterial $material): void
    {
        abort_unless($material->module_id === $module->getKey(), Response::HTTP_NOT_FOUND);
    }
}
