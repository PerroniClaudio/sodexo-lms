<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingPath;
use App\Models\TrainingPathDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingPathDocumentController extends Controller
{
    private const DISK = 's3';

    public function store(Request $request, TrainingPath $trainingPath): RedirectResponse
    {
        $validated = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'file_type' => ['required', 'string', Rule::in(TrainingPathDocument::FILE_TYPES)],
            'category' => ['required', 'string', Rule::in(TrainingPathDocument::CATEGORIES)],
            'file' => ['required', 'file', File::types(['pdf'])->max(1024 * 20)],
        ]);

        $file = $request->file('file');
        $storedPath = $file->storeAs(
            'training-paths/'.$trainingPath->getKey().'/documents',
            Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'pdf'),
            self::DISK,
        );

        $trainingPath->documents()->create([
            'file_name' => $validated['file_name'],
            'file_type' => $validated['file_type'],
            'category' => $validated['category'],
            'disk' => self::DISK,
            'path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        return $this->redirectToDocuments($trainingPath, __('Documento caricato con successo.'));
    }

    public function download(TrainingPath $trainingPath, TrainingPathDocument $document): StreamedResponse
    {
        $this->ensureDocumentBelongsToTrainingPath($trainingPath, $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), Response::HTTP_NOT_FOUND);

        return $disk->download(
            $document->path,
            $document->file_name,
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream'],
        );
    }

    public function destroy(TrainingPath $trainingPath, TrainingPathDocument $document): RedirectResponse
    {
        $this->ensureDocumentBelongsToTrainingPath($trainingPath, $document);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return $this->redirectToDocuments($trainingPath, __('Documento eliminato con successo.'));
    }

    private function ensureDocumentBelongsToTrainingPath(TrainingPath $trainingPath, TrainingPathDocument $document): void
    {
        abort_unless($document->training_path_id === $trainingPath->getKey(), Response::HTTP_NOT_FOUND);
    }

    private function redirectToDocuments(TrainingPath $trainingPath, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.training-paths.edit', [
                'trainingPath' => $trainingPath,
                'section' => 'documents',
            ])
            ->with('status', $message);
    }
}
