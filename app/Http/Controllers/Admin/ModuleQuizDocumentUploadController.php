<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizSubmissionRequest;
use App\Jobs\ProcessQuizSubmission;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizDocumentUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleQuizDocumentUploadController extends Controller
{
    public function index(Request $request, Course $course, Module $module): View
    {
        $this->abortUnlessLearningQuizModule($course, $module);

        $query = $module->documentUploads()
            ->with(['uploadedBy', 'submissions'])
            ->latest();

        return view('admin.module.quiz-document-uploads.index', [
            'course' => $course,
            'module' => $module,
            'documentUploads' => $query->paginate(20),
        ]);
    }

    public function show(Course $course, Module $module, ModuleQuizDocumentUpload $documentUpload): View
    {
        $this->abortUnlessValidDocumentUpload($course, $module, $documentUpload);

        return view('admin.module.quiz-document-uploads.show', [
            'course' => $course,
            'module' => $module,
            'documentUpload' => $documentUpload->load(['uploadedBy', 'submissions.user', 'submissions.answers']),
        ]);
    }

    public function store(StoreQuizSubmissionRequest $request, Course $course, Module $module): RedirectResponse
    {
        $this->abortUnlessLearningQuizModule($course, $module);

        $storedPath = $request->file('submission')->store(sprintf('quiz-submissions/%d', $module->getKey()), 'local');

        $documentUpload = $module->documentUploads()->create([
            'uploaded_by' => (int) $request->user()->getAuthIdentifier(),
            'disk' => 'local',
            'path' => $storedPath,
            'status' => ModuleQuizDocumentUpload::STATUS_UPLOADED,
            'provider' => 'google_document_ai',
        ]);

        ProcessQuizSubmission::dispatch($documentUpload);

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('PDF quiz caricato e inviato in elaborazione.'));
    }

    private function abortUnlessLearningQuizModule(Course $course, Module $module): void
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->type === 'learning_quiz', 404);
    }

    private function abortUnlessValidDocumentUpload(Course $course, Module $module, ModuleQuizDocumentUpload $documentUpload): void
    {
        $this->abortUnlessLearningQuizModule($course, $module);
        abort_unless($documentUpload->module_id === $module->getKey(), 404);
    }
}
