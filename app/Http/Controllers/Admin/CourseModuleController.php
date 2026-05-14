<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignModuleTeachersRequest;
use App\Http\Requests\AssignModuleTutorsRequest;
use App\Http\Requests\ConfirmLiveAttendanceRequest;
use App\Http\Requests\ReorderCourseModulesRequest;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Models\Course;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\Module;
use App\Models\User;
use App\Models\Video;
use App\Services\LiveModuleAttendanceService;
use App\Services\ModuleValidation\ModuleValidatorService;
use App\Services\SyncCourseSatisfactionSurvey;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class CourseModuleController extends Controller
{
    public function __construct(
        private readonly ModuleValidatorService $moduleValidator,
        private readonly SyncCourseSatisfactionSurvey $syncCourseSatisfactionSurvey,
    ) {}

    /**
     * API: Restituisce la lista video per la tabella video-table (paginata, ricerca, ordinamento)
     */
    public function getVideosApi(Request $request)
    {
        $query = Video::withCount('modules');

        // Ricerca globale
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                    ->orWhere('mux_video_status', 'like', "%$search%")
                    ->orWhereHas('modules', function ($q2) use ($search) {
                        $q2->where('title', 'like', "%$search%")
                            ->orWhere('id', 'like', "%$search%")
                            ->orWhere('status', 'like', "%$search%");
                    });
            });
        }

        // Ordinamento
        $sortable = ['title', 'mux_video_status', 'modules_count', 'status'];
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        if (! in_array($sort, $sortable)) {
            $sort = 'created_at';
        }
        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }
        $query->orderBy($sort, $direction);

        $videos = $query->paginate(10);

        // Struttura compatibile con la tabella video-table
        $videos->getCollection()->transform(function ($video) {
            return [
                'id' => $video->id,
                'title' => $video->title,
                'modules_count' => $video->modules_count,
                'mux_video_status' => $video->mux_video_status,
                'trashed_at' => $video->trashed_at,
            ];
        });

        return response()->json($videos);
    }

    public function store(StoreModuleRequest $request, Course $course): RedirectResponse
    {
        $moduleType = $request->validated('type');
        $satisfactionModule = $course->satisfactionModule();
        $nextOrder = $satisfactionModule !== null
            ? (int) $satisfactionModule->order
            : ((int) $course->modules()->max('order') + 1);
        $moduleTitle = Module::requiresManualTitle($moduleType)
            ? $request->validated('title')
            : Module::defaultTitleForType($moduleType);

        $module = DB::transaction(function () use ($course, $moduleTitle, $moduleType, $nextOrder, $satisfactionModule): Module {
            if ($satisfactionModule !== null) {
                $course->modules()
                    ->where('order', '>=', $nextOrder)
                    ->whereKeyNot($satisfactionModule->getKey())
                    ->increment('order');
            }

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

            $this->normalizeSatisfactionModuleOrder($course);

            return $module->fresh();
        });

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('Module created successfully.'));
    }

    public function edit(Course $course, Module $module, LiveModuleAttendanceService $liveModuleAttendanceService): View
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);

        $videos = Video::orderByDesc('created_at')->get();

        return view('admin.module.edit', [
            'course' => $course,
            'module' => $module,
            'moduleEditView' => $this->moduleEditView($module),
            'moduleTypeLabels' => Module::availableTypeLabels(),
            'moduleStatusLabels' => Module::availableStatusLabels(),
            'assignedTeachers' => $this->assignedTeachers($course),
            'availableTeachers' => $this->availableTeachers($course),
            'assignedTutors' => $this->assignedTutors($course),
            'availableTutors' => $this->availableTutors($course),
            'liveAttendanceRows' => $module->type === 'live'
                ? $liveModuleAttendanceService->buildReport($module)
                : collect(),
            'moduleProgressStatusLabels' => $this->moduleProgressStatusLabels(),
            'recentQuizSubmissions' => $module->type === 'learning_quiz'
                ? $module->quizSubmissions()
                    ->with(['user', 'uploadedBy'])
                    ->latest()
                    ->limit(5)
                    ->get()
                : collect(),
            'isValidQuiz' => $module->type === 'learning_quiz' ? $module->isValidQuiz() : false,
            'isValid' => $this->moduleValidator->validate($module),
            'validationErrors' => $this->moduleValidator->getValidationErrors($module),
            'moduleValidator' => $this->moduleValidator,
            'videos' => $videos,
        ]);
    }

    public function assignTeachers(AssignModuleTeachersRequest $request, Course $course, Module $module): RedirectResponse
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->type === 'live', 404);

        $teacherIds = collect($request->validated('teacher_ids'))
            ->map(fn (mixed $teacherId): int => (int) $teacherId)
            ->unique()
            ->values();

        DB::transaction(function () use ($course, $teacherIds): void {
            $existingAssignments = CourseTeacherEnrollment::withTrashed()
                ->where('course_id', $course->getKey())
                ->whereIn('user_id', $teacherIds)
                ->get()
                ->keyBy('user_id');

            foreach ($teacherIds as $teacherId) {
                /** @var CourseTeacherEnrollment|null $assignment */
                $assignment = $existingAssignments->get($teacherId);

                if ($assignment === null) {
                    CourseTeacherEnrollment::query()->create([
                        'course_id' => $course->getKey(),
                        'user_id' => $teacherId,
                        'assigned_at' => now(),
                    ]);

                    continue;
                }

                if ($assignment->trashed()) {
                    $assignment->restore();
                }

                $assignment->forceFill([
                    'assigned_at' => now(),
                    'deleted_at' => null,
                ])->save();
            }
        });

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('Docenti assegnati con successo.'));
    }

    public function assignTutors(AssignModuleTutorsRequest $request, Course $course, Module $module): RedirectResponse
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->type === 'live', 404);

        $tutorIds = collect($request->validated('tutor_ids'))
            ->map(fn (mixed $tutorId): int => (int) $tutorId)
            ->unique()
            ->values();

        DB::transaction(function () use ($course, $tutorIds): void {
            $existingAssignments = CourseTutorEnrollment::withTrashed()
                ->where('course_id', $course->getKey())
                ->whereIn('user_id', $tutorIds)
                ->get()
                ->keyBy('user_id');

            foreach ($tutorIds as $tutorId) {
                /** @var CourseTutorEnrollment|null $assignment */
                $assignment = $existingAssignments->get($tutorId);

                if ($assignment === null) {
                    CourseTutorEnrollment::query()->create([
                        'course_id' => $course->getKey(),
                        'user_id' => $tutorId,
                        'assigned_at' => now(),
                    ]);

                    continue;
                }

                if ($assignment->trashed()) {
                    $assignment->restore();
                }

                $assignment->forceFill([
                    'assigned_at' => now(),
                    'deleted_at' => null,
                ])->save();
            }
        });

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('Tutor assegnati con successo.'));
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
            'passing_score' => $module->isLearningQuiz() ? $validated['passing_score'] : null,
            'max_attempts' => $module->isLearningQuiz() ? $validated['max_attempts'] : null,
            // 'max_score' => $module->isQuiz() ? $validated['max_score'] : null, --- IGNORE ---
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

        if ($module->type === 'live') {
            $moduleAttributes['is_live_teacher'] = $request->boolean('is_live_teacher');
        }

        try {
            $module->update($moduleAttributes);

            return redirect()
                ->route('admin.courses.edit', $course)
                ->with('status', __('Module updated successfully.'));
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function confirmAttendance(
        ConfirmLiveAttendanceRequest $request,
        Course $course,
        Module $module,
        LiveModuleAttendanceService $liveModuleAttendanceService,
    ): RedirectResponse {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->type === 'live', 404);

        $stats = $liveModuleAttendanceService->confirmAttendance(
            $module,
            $request->effectiveStartAt(),
            $request->effectiveEndAt(),
            $request->minimumAttendancePercentage(),
        );

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', $this->attendanceConfirmationStatusMessage($stats));
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
            $satisfactionModuleId = $course->satisfactionModule()?->getKey();

            $normalizedModuleIds = collect($orderedModuleIds)
                ->reject(fn (int $moduleId): bool => $satisfactionModuleId !== null && $moduleId === (int) $satisfactionModuleId)
                ->values();

            if ($satisfactionModuleId !== null) {
                $normalizedModuleIds->push((int) $satisfactionModuleId);
            }

            $normalizedModuleIds
                ->values()
                ->each(function (int $moduleId, int $index) use ($course): void {
                    $course->modules()->whereKey($moduleId)->update([
                        'order' => $index + 1,
                    ]);
                });
        });

        $this->normalizeSatisfactionModuleOrder($course);

        return response()->json([
            'message' => __('Module order updated successfully.'),
        ]);
    }

    private function normalizeSatisfactionModuleOrder(Course $course): void
    {
        $satisfactionModule = $course->satisfactionModule();

        if ($satisfactionModule === null) {
            return;
        }

        $lastOrder = (int) $course->modules()
            ->whereKeyNot($satisfactionModule->getKey())
            ->max('order');

        if ((int) $satisfactionModule->order !== $lastOrder + 1) {
            Module::query()
                ->whereKey($satisfactionModule->getKey())
                ->update([
                    'order' => $lastOrder + 1,
                ]);
        }
    }

    private function moduleEditView(Module $module): string
    {
        return sprintf('admin.module.types.%s', $module->type);
    }

    private function assignedTeachers(Course $course)
    {
        return $course->teacherEnrollments()
            ->with('user')
            ->whereNull('deleted_at')
            ->latest('assigned_at')
            ->get();
    }

    private function availableTeachers(Course $course)
    {
        $assignedTeacherIds = $course->teacherEnrollments()
            ->whereNull('deleted_at')
            ->pluck('user_id');

        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'teacher'))
            ->whereNotIn('id', $assignedTeacherIds)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
    }

    private function assignedTutors(Course $course)
    {
        return $course->tutorEnrollments()
            ->with('user')
            ->whereNull('deleted_at')
            ->latest('assigned_at')
            ->get();
    }

    private function availableTutors(Course $course)
    {
        $assignedTutorIds = $course->tutorEnrollments()
            ->whereNull('deleted_at')
            ->pluck('user_id');

        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'tutor'))
            ->whereNotIn('id', $assignedTutorIds)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    private function moduleProgressStatusLabels(): array
    {
        return [
            'locked' => __('Bloccato'),
            'available' => __('Disponibile'),
            'in_progress' => __('In corso'),
            'completed' => __('Completato'),
            'failed' => __('Non superato'),
        ];
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function attendanceConfirmationStatusMessage(array $stats): string
    {
        return __('Presenze confermate. :confirmed utenti abilitati, :alreadyCompleted già completati, :notCurrent sopra soglia ma non ancora sul modulo corrente.', [
            'confirmed' => $stats['confirmed'],
            'alreadyCompleted' => $stats['already_completed'],
            'notCurrent' => $stats['skipped_not_current'],
        ]);
    }

    /**
     * Assegna un video al modulo (API)
     */
    public function assignVideoToModule(Request $request, Module $module): JsonResponse
    {
        $videoId = $request->input('video_id');
        if (! $videoId || ! Video::find($videoId)) {
            return response()->json(['success' => false, 'message' => 'Video non valido'], 422);
        }
        $module->video_id = $videoId;
        $module->save();

        return response()->json(['success' => true, 'video_id' => $videoId]);
    }

    /**
     * Rimuove l'assegnazione del video dal modulo (API)
     */
    public function unassignVideoFromModule(Module $module): JsonResponse
    {
        $module->video_id = null;
        $module->save();

        return response()->json(['success' => true]);
    }

    /**
     * Restituisce la validità del modulo (API)
     */
    public function getModuleValidity(Module $module): JsonResponse
    {
        $isValid = $this->moduleValidator->validate($module);
        $errors = $isValid ? [] : $this->moduleValidator->getValidationErrors($module);

        return response()->json([
            'isValid' => $isValid,
            'errors' => $errors,
        ]);
    }
}
