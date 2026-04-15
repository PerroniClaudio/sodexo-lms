<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderCourseModulesRequest;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Models\Course;
use App\Models\Module;
use Carbon\CarbonImmutable;
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
            ->with('status', __('Module created successfully.'));
    }

    public function edit(Course $course, Module $module): View
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);

        return view('admin.module.edit', [
            'course' => $course,
            'module' => $module,
            'moduleTypeLabels' => Module::availableTypeLabels(),
            'moduleStatusLabels' => Module::availableStatusLabels(),
            'requiresManualTitle' => Module::requiresManualTitle($module->type),
            'requiresAppointmentDetails' => Module::requiresAppointmentDetails($module->type),
        ]);
    }

    public function update(UpdateModuleRequest $request, Course $course, Module $module): RedirectResponse
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);

        $validated = $request->validated();

        $moduleAttributes = [
            'title' => Module::requiresManualTitle($module->type)
                ? $validated['title']
                : Module::defaultTitleForType($module->type),
            'description' => $validated['description'] ?? '',
            'status' => $validated['status'],
        ];

        if (Module::requiresAppointmentDetails($module->type)) {
            $appointmentDate = CarbonImmutable::createFromFormat('Y-m-d', $validated['appointment_date']);

            $moduleAttributes['appointment_date'] = $appointmentDate->startOfDay();
            $moduleAttributes['appointment_start_time'] = CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                sprintf('%s %s', $validated['appointment_date'], $validated['appointment_start_time']),
            );
            $moduleAttributes['appointment_end_time'] = CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                sprintf('%s %s', $validated['appointment_date'], $validated['appointment_end_time']),
            );
        }

        $module->update($moduleAttributes);

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('status', __('Module updated successfully.'));
    }

    public function destroy(Course $course, Module $module): RedirectResponse
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);

        $module->delete();

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('status', __('Module deleted successfully.'));
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
            'message' => __('Module order updated successfully.'),
        ]);
    }
}
