<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SyncCourseClassTeachers;
use App\Actions\SyncCourseClassTutors;
use App\Actions\SyncCourseClassUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteCourseClassTeachersRequest;
use App\Http\Requests\DeleteCourseClassTutorsRequest;
use App\Http\Requests\DeleteCourseClassUsersRequest;
use App\Http\Requests\StoreCourseClassAttendanceRegisterRequest;
use App\Http\Requests\StoreCourseClassAttendanceRequest;
use App\Http\Requests\StoreCourseClassRequest;
use App\Http\Requests\StoreCourseClassTeachersRequest;
use App\Http\Requests\StoreCourseClassTutorsRequest;
use App\Http\Requests\StoreCourseClassUsersRequest;
use App\Http\Requests\UpdateCourseClassRequest;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassSchedule;
use App\Models\CourseClassTeacher;
use App\Models\CourseClassTutor;
use App\Models\CourseClassUser;
use App\Models\User;
use App\Support\CloudStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseClassController extends Controller
{
    public function index(Course $course): JsonResponse
    {
        $this->abortUnlessCourseSupportsClasses($course);

        $classes = CourseClass::query()
            ->with([
                'module',
                'schedules',
                'userAssignments.user' => fn ($query) => $query->select(['id', 'name', 'surname', 'email', 'fiscal_code']),
                'teacherAssignments.user' => fn ($query) => $query->select(['id', 'name', 'surname', 'email', 'fiscal_code']),
                'tutorAssignments.user' => fn ($query) => $query->select(['id', 'name', 'surname', 'email', 'fiscal_code']),
            ])
            ->whereHas('module', fn ($query) => $query->where('belongsTo', (string) $course->getKey()))
            ->get()
            ->sortBy(fn (CourseClass $courseClass): array => [
                $courseClass->module?->order ?? PHP_INT_MAX,
                $courseClass->scheduledStartAt()?->timestamp ?? PHP_INT_MAX,
                $courseClass->getKey(),
            ])
            ->values()
            ->map(fn (CourseClass $courseClass): array => $this->classPayload($course, $courseClass));

        return response()->json(['data' => $classes]);
    }

    public function edit(Course $course, CourseClass $courseClass): View
    {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        return view('admin.course-classes.edit', [
            'course' => $course,
            'module' => $courseClass->module,
            'courseClass' => $courseClass,
            'courseClassPayloads' => collect([$this->classPayload($course, $courseClass)]),
        ]);
    }

    public function attendance(Course $course, CourseClass $courseClass): View
    {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);
        abort_unless($this->classHasEnded($courseClass), Response::HTTP_NOT_FOUND);

        $courseClass->loadMissing([
            'module',
            'userAssignments.user' => fn ($query) => $query->select(['id', 'name', 'surname', 'fiscal_code']),
        ]);
        $recordedUserIds = $this->recordedAttendanceUserIds($course);

        return view('admin.course-classes.attendance', [
            'course' => $course,
            'module' => $courseClass->module,
            'courseClass' => $courseClass,
            'existingAttendanceRows' => $this->existingAttendanceRows($course, $recordedUserIds),
            'attendanceRegisterFile' => $this->attendanceRegisterFile($courseClass),
            'assignments' => $courseClass->userAssignments
                ->reject(fn (CourseClassUser $assignment): bool => $recordedUserIds->contains($assignment->user_id))
                ->sortBy([
                    ['user.surname', 'asc'],
                    ['user.name', 'asc'],
                ])
                ->values(),
        ]);
    }

    public function storeAttendanceRegister(
        StoreCourseClassAttendanceRegisterRequest $request,
        Course $course,
        CourseClass $courseClass,
    ): RedirectResponse {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);
        abort_unless($this->classHasEnded($courseClass), Response::HTTP_NOT_FOUND);

        $existingFile = $this->attendanceRegisterFile($courseClass);
        if ($existingFile !== null) {
            Storage::disk($existingFile->disk)->delete($existingFile->path);
        }

        $file = $request->file('register_file');
        $path = $file->storeAs(
            'course-classes/'.$courseClass->getKey().'/attendance-register',
            Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'pdf'),
            CloudStorage::disk(),
        );

        DB::table('course_class_attendance_register_files')->updateOrInsert(
            ['course_class_id' => $courseClass->getKey()],
            [
                'uploaded_by_user_id' => $request->user()->getKey(),
                'disk' => CloudStorage::disk(),
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'updated_at' => now(),
                'created_at' => $existingFile?->created_at ?? now(),
            ],
        );

        return redirect()
            ->route('admin.courses.classes.attendance', [$course, $courseClass])
            ->with('status', __('Registro presenze caricato.'));
    }

    public function downloadAttendanceRegister(Course $course, CourseClass $courseClass): StreamedResponse
    {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        $file = $this->attendanceRegisterFile($courseClass);
        abort_unless($file !== null, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), Response::HTTP_NOT_FOUND);

        return $disk->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function storeAttendance(
        StoreCourseClassAttendanceRequest $request,
        Course $course,
        CourseClass $courseClass,
    ): RedirectResponse {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);
        abort_unless($this->classHasEnded($courseClass), Response::HTTP_NOT_FOUND);

        $allowedUsersByFiscalCode = $courseClass->userAssignments()
            ->with('user:id,fiscal_code')
            ->whereNotIn('user_id', $this->recordedAttendanceUserIds($course))
            ->get()
            ->mapWithKeys(fn (CourseClassUser $assignment): array => [
                Str::upper((string) $assignment->user?->fiscal_code) => (int) $assignment->user_id,
            ]);

        $recordDate = $courseClass->scheduledEndAt()?->toDateString() ?? now()->toDateString();
        $records = $this->attendanceImportRows($request->file('attendance_file')->getRealPath())
            ->map(fn (array $row): array => [
                ...$row,
                'user_id' => $allowedUsersByFiscalCode[Str::upper($row['fiscal_code'])] ?? null,
            ])
            ->filter(fn (array $row): bool => $row['user_id'] !== null)
            ->flatMap(function (array $pair) use ($course, $recordDate, $request): array {
                $sessionId = (string) Str::uuid();

                return [
                    [
                        'user_id' => $pair['user_id'],
                        'course_id' => $course->getKey(),
                        'type' => 'entry',
                        'session_id' => $sessionId,
                        'created_by_user_id' => $request->user()->getKey(),
                        'recorded_at' => "{$recordDate} {$pair['in']}:00",
                    ],
                    [
                        'user_id' => $pair['user_id'],
                        'course_id' => $course->getKey(),
                        'type' => 'exit',
                        'session_id' => $sessionId,
                        'created_by_user_id' => $request->user()->getKey(),
                        'recorded_at' => "{$recordDate} {$pair['out']}:00",
                    ],
                ];
            })
            ->values();

        if ($records->isNotEmpty()) {
            DB::table('course_attendance_records')->insert($records->all());
        }

        return redirect()
            ->route('admin.courses.classes.attendance', [$course, $courseClass])
            ->with('status', $records->isEmpty() ? __('Nessuna presenza importata.') : __('Presenze importate.'));
    }

    public function downloadAttendanceTemplate(Course $course, CourseClass $courseClass): StreamedResponse
    {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);
        abort_unless($this->classHasEnded($courseClass), Response::HTTP_NOT_FOUND);

        $recordedUserIds = $this->recordedAttendanceUserIds($course);
        $courseClass->loadMissing(['userAssignments.user:id,name,surname,fiscal_code']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Presenze');
        $participants = $courseClass->userAssignments
            ->reject(fn (CourseClassUser $assignment): bool => $recordedUserIds->contains($assignment->user_id))
            ->sortBy([['user.surname', 'asc'], ['user.name', 'asc']])
            ->values();

        $sheet->fromArray([[
            'nome',
            'cognome',
            'codice fiscale',
            'orario entrata',
            'orario uscita',
        ]]);

        $participants
            ->each(function (CourseClassUser $assignment, int $index) use ($sheet): void {
                $sheet->fromArray([[
                    $assignment->user?->name,
                    $assignment->user?->surname,
                    $assignment->user?->fiscal_code,
                    '',
                    '',
                ]], null, 'A'.($index + 2));
            });

        foreach (range(1, 5) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $participantsSheet = $spreadsheet->createSheet();
        $participantsSheet->setTitle('Partecipanti');
        $participantsSheet->fromArray([[
            'nome',
            'cognome',
            'codice fiscale',
        ]]);
        $participants->each(function (CourseClassUser $assignment, int $index) use ($participantsSheet): void {
            $participantsSheet->fromArray([[
                $assignment->user?->name,
                $assignment->user?->surname,
                $assignment->user?->fiscal_code,
            ]], null, 'A'.($index + 2));
        });

        foreach (range(1, 3) as $column) {
            $participantsSheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'template-presenze.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(StoreCourseClassRequest $request, Course $course): JsonResponse
    {
        $courseClass = CourseClass::query()->create([
            'module_id' => $request->integer('module_id'),
            'name' => $request->validated('name'),
        ]);
        $this->syncSchedules($courseClass, $request->schedules()->all());

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Classe creata con successo.'),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateCourseClassRequest $request, Course $course, CourseClass $courseClass): JsonResponse
    {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        $courseClass->update([
            'module_id' => $request->integer('module_id'),
            'name' => $request->validated('name'),
        ]);
        $this->syncSchedules($courseClass, $request->schedules()->all());

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Classe aggiornata con successo.'),
        ]);
    }

    public function destroy(Course $course, CourseClass $courseClass): JsonResponse
    {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        DB::transaction(function () use ($courseClass): void {
            $courseClass->userAssignments()->delete();
            $courseClass->teacherAssignments()->delete();
            $courseClass->tutorAssignments()->delete();
            $courseClass->delete();
        });

        return response()->json([
            'message' => __('Classe eliminata con successo.'),
        ]);
    }

    public function searchUsers(Course $course): JsonResponse
    {
        $this->abortUnlessCourseSupportsClasses($course);

        return $this->searchCourseUsers($course);
    }

    public function searchTeachers(Course $course): JsonResponse
    {
        $this->abortUnlessCourseSupportsClasses($course);

        return $this->searchCourseTeachers($course);
    }

    public function searchTutors(Course $course): JsonResponse
    {
        $this->abortUnlessCourseSupportsClasses($course);

        return $this->searchCourseTutors($course);
    }

    public function storeUsers(
        StoreCourseClassUsersRequest $request,
        Course $course,
        CourseClass $courseClass,
        SyncCourseClassUsers $syncCourseClassUsers,
    ): JsonResponse {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        $syncCourseClassUsers->handle($courseClass, $request->userIds());

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Utenti assegnati con successo.'),
        ], Response::HTTP_CREATED);
    }

    public function destroyUser(Course $course, CourseClass $courseClass, CourseClassUser $assignment): JsonResponse
    {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);
        abort_unless($assignment->course_class_id === $courseClass->getKey(), Response::HTTP_NOT_FOUND);

        $assignment->delete();

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Utente rimosso dalla classe.'),
        ]);
    }

    public function destroyUsers(
        DeleteCourseClassUsersRequest $request,
        Course $course,
        CourseClass $courseClass,
    ): JsonResponse {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        CourseClassUser::query()
            ->where('course_class_id', $courseClass->getKey())
            ->whereKey($request->assignmentIds())
            ->delete();

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Utenti rimossi dalla classe.'),
        ]);
    }

    public function storeTeachers(
        StoreCourseClassTeachersRequest $request,
        Course $course,
        CourseClass $courseClass,
        SyncCourseClassTeachers $syncCourseClassTeachers,
    ): JsonResponse {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        $syncCourseClassTeachers->handle($courseClass, $request->teacherIds());

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Docenti assegnati con successo.'),
        ], Response::HTTP_CREATED);
    }

    public function destroyTeacher(Course $course, CourseClass $courseClass, CourseClassTeacher $assignment): JsonResponse
    {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);
        abort_unless($assignment->course_class_id === $courseClass->getKey(), Response::HTTP_NOT_FOUND);

        $assignment->delete();

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Docente rimosso dalla classe.'),
        ]);
    }

    public function destroyTeachers(
        DeleteCourseClassTeachersRequest $request,
        Course $course,
        CourseClass $courseClass,
    ): JsonResponse {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        CourseClassTeacher::query()
            ->where('course_class_id', $courseClass->getKey())
            ->whereKey($request->assignmentIds())
            ->delete();

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Docenti rimossi dalla classe.'),
        ]);
    }

    public function storeTutors(
        StoreCourseClassTutorsRequest $request,
        Course $course,
        CourseClass $courseClass,
        SyncCourseClassTutors $syncCourseClassTutors,
    ): JsonResponse {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        $syncCourseClassTutors->handle($courseClass, $request->tutorIds());

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Tutor assegnati con successo.'),
        ], Response::HTTP_CREATED);
    }

    public function destroyTutor(Course $course, CourseClass $courseClass, CourseClassTutor $assignment): JsonResponse
    {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);
        abort_unless($assignment->course_class_id === $courseClass->getKey(), Response::HTTP_NOT_FOUND);

        $assignment->delete();

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Tutor rimosso dalla classe.'),
        ]);
    }

    public function destroyTutors(
        DeleteCourseClassTutorsRequest $request,
        Course $course,
        CourseClass $courseClass,
    ): JsonResponse {
        $this->abortUnlessClassBelongsToCourse($course, $courseClass);

        CourseClassTutor::query()
            ->where('course_class_id', $courseClass->getKey())
            ->whereKey($request->assignmentIds())
            ->delete();

        return response()->json([
            'data' => $this->classPayload($course, $courseClass->fresh()),
            'message' => __('Tutor rimossi dalla classe.'),
        ]);
    }

    private function searchCourseUsers(Course $course): JsonResponse
    {
        return response()->json([
            'data' => $this->searchPeopleCollection($course->users()->getQuery()->whereNull('course_user.deleted_at'))->values(),
        ]);
    }

    private function searchCourseTeachers(Course $course): JsonResponse
    {
        return response()->json([
            'data' => $this->searchPeopleCollection($course->getTeachersQuery())->values(),
        ]);
    }

    private function searchCourseTutors(Course $course): JsonResponse
    {
        return response()->json([
            'data' => $this->searchPeopleCollection($course->getTutorsQuery())->values(),
        ]);
    }

    private function searchPeopleCollection($query)
    {
        $search = trim((string) request('search', ''));

        if ($search === '') {
            return collect();
        }

        $isNumericSearch = ctype_digit($search);

        if (! $isNumericSearch && mb_strlen($search) < 2) {
            return collect();
        }

        $prefixSearch = addcslashes($search, '\\%_').'%';
        $fiscalCodePrefixSearch = addcslashes(mb_strtoupper($search), '\\%_').'%';

        return $query
            ->select(['users.id', 'users.name', 'users.surname', 'users.email', 'users.fiscal_code'])
            ->where(function ($nestedQuery) use ($fiscalCodePrefixSearch, $isNumericSearch, $prefixSearch, $search): void {
                if ($isNumericSearch) {
                    $nestedQuery->orWhere('users.id', (int) $search);
                }

                $nestedQuery
                    ->orWhere('users.name', 'like', $prefixSearch)
                    ->orWhere('users.surname', 'like', $prefixSearch)
                    ->orWhere('users.fiscal_code', 'like', $fiscalCodePrefixSearch)
                    ->orWhere('users.email', 'like', $prefixSearch);
            })
            ->orderBy('users.surname')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->limit(20)
            ->get()
            ->map(fn (User $user): array => $this->userPayload($user));
    }

    private function abortUnlessClassBelongsToCourse(Course $course, CourseClass $courseClass): void
    {
        $this->abortUnlessCourseSupportsClasses($course);
        abort_unless((int) $courseClass->module?->belongsTo === (int) $course->getKey(), Response::HTTP_NOT_FOUND);
    }

    private function abortUnlessCourseSupportsClasses(Course $course): void
    {
        abort_unless($course->supportsClasses(), Response::HTTP_NOT_FOUND);
    }

    private function classPayload(Course $course, CourseClass $courseClass): array
    {
        $courseClass->loadMissing([
            'module',
            'schedules',
            'userAssignments' => fn ($query) => $query->select(['id', 'course_class_id', 'user_id']),
            'userAssignments.user' => fn ($query) => $query->select(['id', 'name', 'surname', 'email', 'fiscal_code']),
            'teacherAssignments' => fn ($query) => $query->select(['id', 'course_class_id', 'user_id']),
            'teacherAssignments.user' => fn ($query) => $query->select(['id', 'name', 'surname', 'email', 'fiscal_code']),
            'tutorAssignments' => fn ($query) => $query->select(['id', 'course_class_id', 'user_id']),
            'tutorAssignments.user' => fn ($query) => $query->select(['id', 'name', 'surname', 'email', 'fiscal_code']),
        ]);

        $resolvedSchedule = $courseClass->resolvedSchedule();
        $schedules = $courseClass->orderedSchedules();

        return [
            'id' => $courseClass->getKey(),
            'module_id' => $courseClass->module?->getKey(),
            'module_title' => $courseClass->module?->title,
            'name' => $courseClass->name,
            'starts_at' => $resolvedSchedule?->starts_at?->toAtomString(),
            'starts_at_label' => $resolvedSchedule?->starts_at?->format('d/m/Y H:i'),
            'ends_at' => $resolvedSchedule?->ends_at?->toAtomString(),
            'ends_at_label' => $resolvedSchedule?->ends_at?->format('d/m/Y H:i'),
            'schedules_count' => $schedules->count(),
            'schedules' => $schedules->map(fn (CourseClassSchedule $schedule): array => [
                'id' => $schedule->getKey(),
                'starts_at' => $schedule->starts_at->toAtomString(),
                'starts_at_label' => $schedule->starts_at->format('d/m/Y H:i'),
                'starts_at_date' => $schedule->starts_at->format('Y-m-d'),
                'starts_at_time' => $schedule->starts_at->format('H:i'),
                'ends_at' => $schedule->ends_at->toAtomString(),
                'ends_at_label' => $schedule->ends_at->format('d/m/Y H:i'),
                'ends_at_date' => $schedule->ends_at->format('Y-m-d'),
                'ends_at_time' => $schedule->ends_at->format('H:i'),
            ])->values(),
            'users_count' => $courseClass->userAssignments->count(),
            'teachers_count' => $courseClass->teacherAssignments->count(),
            'tutors_count' => $courseClass->tutorAssignments->count(),
            'remaining_user_slots' => $courseClass->remainingUserSlots(),
            'users' => $courseClass->userAssignments
                ->map(fn (CourseClassUser $assignment): array => [
                    'assignment_id' => $assignment->getKey(),
                    'delete_url' => route('admin.courses.classes.users.destroy', [$course, $courseClass, $assignment]),
                    ...$this->userPayload($assignment->user),
                ])
                ->values(),
            'teachers' => $courseClass->teacherAssignments
                ->map(fn (CourseClassTeacher $assignment): array => [
                    'assignment_id' => $assignment->getKey(),
                    'delete_url' => route('admin.courses.classes.teachers.destroy', [$course, $courseClass, $assignment]),
                    ...$this->userPayload($assignment->user),
                ])
                ->values(),
            'tutors' => $courseClass->tutorAssignments
                ->map(fn (CourseClassTutor $assignment): array => [
                    'assignment_id' => $assignment->getKey(),
                    'delete_url' => route('admin.courses.classes.tutors.destroy', [$course, $courseClass, $assignment]),
                    ...$this->userPayload($assignment->user),
                ])
                ->values(),
            'routes' => [
                'edit' => route('admin.courses.classes.edit', [$course, $courseClass]),
                'attendance' => $this->classHasEnded($courseClass) ? route('admin.courses.classes.attendance', [$course, $courseClass]) : null,
                'update' => route('admin.courses.classes.update', [$course, $courseClass]),
                'delete' => route('admin.courses.classes.destroy', [$course, $courseClass]),
                'users_store' => route('admin.courses.classes.users.store', [$course, $courseClass]),
                'users_destroy_many' => route('admin.courses.classes.users.destroy-many', [$course, $courseClass]),
                'teachers_store' => route('admin.courses.classes.teachers.store', [$course, $courseClass]),
                'teachers_destroy_many' => route('admin.courses.classes.teachers.destroy-many', [$course, $courseClass]),
                'tutors_store' => route('admin.courses.classes.tutors.store', [$course, $courseClass]),
                'tutors_destroy_many' => route('admin.courses.classes.tutors.destroy-many', [$course, $courseClass]),
            ],
        ];
    }

    private function classHasEnded(CourseClass $courseClass): bool
    {
        return $courseClass->scheduledEndAt()?->lte(now()) ?? false;
    }

    private function recordedAttendanceUserIds(Course $course): Collection
    {
        return DB::table('course_attendance_records')
            ->where('course_id', $course->getKey())
            ->pluck('user_id')
            ->map(fn (int|string $userId): int => (int) $userId);
    }

    private function existingAttendanceRows(Course $course, Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return DB::table('course_attendance_records')
            ->join('users', 'users.id', '=', 'course_attendance_records.user_id')
            ->where('course_attendance_records.course_id', $course->getKey())
            ->whereIn('course_attendance_records.user_id', $userIds)
            ->select([
                'course_attendance_records.user_id',
                'users.name',
                'users.surname',
                'users.fiscal_code',
                'course_attendance_records.session_id',
                'course_attendance_records.type',
                'course_attendance_records.recorded_at',
            ])
            ->orderBy('users.surname')
            ->orderBy('users.name')
            ->orderBy('course_attendance_records.recorded_at')
            ->get()
            ->groupBy(fn (object $record): string => $record->user_id.'-'.$record->session_id)
            ->map(function (Collection $records): array {
                $firstRecord = $records->first();

                return [
                    'name' => $firstRecord->name,
                    'surname' => $firstRecord->surname,
                    'fiscal_code' => $firstRecord->fiscal_code,
                    'entry' => $records->firstWhere('type', 'entry')?->recorded_at,
                    'exit' => $records->firstWhere('type', 'exit')?->recorded_at,
                ];
            })
            ->values();
    }

    private function attendanceRegisterFile(CourseClass $courseClass): ?object
    {
        return DB::table('course_class_attendance_register_files')
            ->where('course_class_id', $courseClass->getKey())
            ->first();
    }

    private function attendanceImportRows(string $path): Collection
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = collect();

        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $fiscalCode = trim((string) $sheet->getCell("C{$row}")->getValue());
            $entry = $this->excelTime($sheet->getCell("D{$row}")->getValue());
            $exit = $this->excelTime($sheet->getCell("E{$row}")->getValue());

            if ($fiscalCode === '' || $entry === null || $exit === null) {
                continue;
            }

            $rows->push([
                'fiscal_code' => $fiscalCode,
                'in' => $entry,
                'out' => $exit,
            ]);
        }

        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    private function excelTime(mixed $value): ?string
    {
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('H:i');
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{1,2}:\d{2}$/', $value) !== 1) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $value));

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * @param  array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $schedules
     */
    private function syncSchedules(CourseClass $courseClass, array $schedules): void
    {
        $courseClass->schedules()->delete();

        $courseClass->schedules()->createMany(
            collect($schedules)
                ->map(fn (array $schedule): array => [
                    'starts_at' => $schedule['starts_at'],
                    'ends_at' => $schedule['ends_at'],
                ])
                ->all()
        );
    }

    private function userPayload(?User $user): array
    {
        return [
            'id' => $user?->getKey(),
            'name' => $user?->name,
            'surname' => $user?->surname,
            'full_name' => $user?->full_name,
            'email' => $user?->email,
            'fiscal_code' => $user?->fiscal_code,
        ];
    }
}
