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

        return view('admin.scorm.index', [
            'course' => $course,
            'module' => $module->loadMissing('scormPackages'),
            'packages' => $module->scormPackages()->latest()->get(),
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
