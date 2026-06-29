<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseDocumentController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'file_type' => ['required', 'string', Rule::in(CourseDocument::FILE_TYPES)],
            'category' => ['required', 'string', Rule::in(CourseDocument::CATEGORIES)],
            'file' => ['required', 'file', File::types(['pdf'])->max(1024 * 20)],
        ]);

        $file = $request->file('file');
        $storedPath = $file->storeAs(
            'courses/'.$course->getKey().'/documents',
            Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'pdf')
        );

        $course->documents()->create([
            'file_name' => $validated['file_name'],
            'file_type' => $validated['file_type'],
            'category' => $validated['category'],
            'disk' => Storage::getDefaultDriver(),
            'path' => $storedPath,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        return $this->redirectToDocuments($course, __('Documento caricato con successo.'));
    }

    public function download(Course $course, CourseDocument $document): StreamedResponse
    {
        $this->ensureDocumentBelongsToCourse($course, $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), Response::HTTP_NOT_FOUND);

        return $disk->download(
            $document->path,
            $document->file_name,
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream'],
        );
    }

    public function destroy(Course $course, CourseDocument $document): RedirectResponse
    {
        $this->ensureDocumentBelongsToCourse($course, $document);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return $this->redirectToDocuments($course, __('Documento eliminato con successo.'));
    }

    private function ensureDocumentBelongsToCourse(Course $course, CourseDocument $document): void
    {
        abort_unless($document->course_id === $course->getKey(), Response::HTTP_NOT_FOUND);
    }

    private function redirectToDocuments(Course $course, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.courses.edit', [
                'course' => $course,
                'section' => 'documents',
            ])
            ->with('status', $message);
    }
}
