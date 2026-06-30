<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScormPackageRequest;
use App\Models\Course;
use App\Models\Module;
use App\Models\ScormPackage;
use App\Services\ScormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class ScormPackageController extends Controller
{
    public function index(Course $course, Module $module): View
    {
        $this->abortUnlessScormModule($course, $module);
        $module->loadMissing('scormPackages');

        return view('admin.scorm.index', [
            'course' => $course,
            'module' => $module,
            'packages' => $module->scormPackages()->latest()->get(),
            'hasPackageLimitReached' => $module->scormPackages->isNotEmpty(),
        ]);
    }

    public function store(
        StoreScormPackageRequest $request,
        Course $course,
        Module $module,
        ScormService $scormService,
    ): RedirectResponse {
        $this->abortUnlessScormModule($course, $module);
        try {
            $module->ensureContentIsEditable();
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.courses.modules.scorm.index', [$course, $module])
                ->with('error', $exception->getMessage());
        }

        if ($module->scormPackages()->exists()) {
            return redirect()
                ->route('admin.courses.modules.scorm.index', [$course, $module])
                ->withErrors([
                    'package' => __('Il modulo SCORM può contenere un solo pacchetto. Elimina quello esistente prima di caricarne un altro.'),
                ]);
        }

        try {
            $scormService->storeUploadedPackage(
                $module,
                $request->file('package'),
                $request->validated('title'),
                $request->validated('description'),
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'package' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('admin.courses.modules.scorm.index', [$course, $module])
            ->with('status', __('Pacchetto SCORM caricato con successo.'));
    }

    public function destroy(
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
        ScormService $scormService,
    ): RedirectResponse {
        $this->abortUnlessScormModule($course, $module);
        abort_unless($scormPackage->module_id === $module->getKey(), 404);
        try {
            $module->ensureContentIsEditable();
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.courses.modules.scorm.index', [$course, $module])
                ->with('error', $exception->getMessage());
        }

        $scormService->deletePackage($scormPackage);

        return redirect()
            ->route('admin.courses.modules.scorm.index', [$course, $module])
            ->with('status', __('Pacchetto SCORM eliminato con successo.'));
    }

    private function abortUnlessScormModule(Course $course, Module $module): void
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isScorm(), 404);
    }
}
