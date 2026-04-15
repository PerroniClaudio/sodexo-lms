<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderCourseModulesRequest;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourseModuleController extends Controller
{
    public function store(StoreModuleRequest $request, Course $course): RedirectResponse
    {
        $moduleType = $request->validated('type');
        $nextOrder = (int) $course->modules()->max('order') + 1;
        $moduleTitle = Module::requiresManualTitle($moduleType)
            ? $request->validated('title')
            : Module::defaultTitleForType($moduleType);

        $module = $course->modules()->create([
            'title' => $moduleTitle,
            'description' => '',
            'type' => $moduleType,
            'order' => $nextOrder,
            'appointment_date' => now(),
            'appointment_start_time' => now(),
            'appointment_end_time' => now()->addHour(),
            'status' => 'draft',
            'belongsTo' => (string) $course->getKey(),
        ]);

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('Modulo creato con successo.'));
    }

    public function edit(Course $course, Module $module): View
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);

        return view('admin.module.edit', [
            'course' => $course,
            'module' => $module,
            'moduleTypeLabels' => Module::availableTypeLabels(),
            'requiresManualTitle' => Module::requiresManualTitle($module->type),
        ]);
    }

    public function update(UpdateModuleRequest $request, Course $course, Module $module): RedirectResponse
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);

        $module->update([
            'title' => Module::requiresManualTitle($module->type)
                ? $request->validated('title')
                : Module::defaultTitleForType($module->type),
            'description' => $request->validated('description'),
        ]);

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('Modulo aggiornato con successo.'));
    }

    public function reorder(ReorderCourseModulesRequest $request, Course $course): JsonResponse
    {
        $orderedModuleIds = $request->validated('modules');

        DB::transaction(function () use ($course, $orderedModuleIds): void {
            collect($orderedModuleIds)
                ->values()
                ->each(function (int $moduleId, int $index) use ($course): void {
                    $course->modules()->whereKey($moduleId)->update([
                        'order' => $index + 1,
                    ]);
                });
        });

        return response()->json([
            'message' => __('Ordine moduli aggiornato con successo.'),
        ]);
    }
}
