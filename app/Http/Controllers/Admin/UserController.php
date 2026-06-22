<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\DocumentType;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\LanguageLevel;
use App\Models\Province;
use App\Models\RiskBasedRequirement;
use App\Models\User;
use App\Models\UserCertificate;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use App\Services\CourseRiskRequirementService;
use App\Services\RiskCalculationService;
use App\Services\UserJobAssignmentService;
use App\Support\NeedsLanguageLevelVerificationResolver;
use App\Support\UserGeographyMapper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Controller solo per gestione utenti da backend (Blade, no API)
 * Ricerca, ordinamento e paginazione su nome, cognome, CF, email
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserGeographyMapper $userGeographyMapper,
        private readonly UserJobAssignmentService $userJobAssignmentService,
        private readonly CourseRiskRequirementService $courseRiskRequirementService,
        private readonly NeedsLanguageLevelVerificationResolver $needsLanguageLevelVerificationResolver,
    ) {}

    public function index(Request $request): View
    {
        $query = User::query()->with('jobRole');

        // Mostra eliminati
        $showTrashed = $request->boolean('show_trashed');
        if ($showTrashed) {
            $query->withTrashed();
        }

        // Ricerca
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('surname', 'like', "%$search%")
                    ->orWhere('fiscal_code', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        $userRiskOverview = $this->buildUserRiskOverview(clone $query);

        // Ordinamento
        $sortable = ['name', 'surname', 'fiscal_code', 'email', 'account_type', 'role', 'status'];
        $sort = $request->input('sort', 'surname');
        $direction = $request->input('direction', 'asc');
        if (! in_array($sort, $sortable)) {
            $sort = 'surname';
        }
        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'asc';
        }

        if ($sort === 'status') {
            // Ordinamento per stato: attivo (non eliminato) prima di eliminato
            $users = $query->get()->sortBy(function ($user) {
                return $user->trashed() ? 1 : 0;
            }, SORT_REGULAR, $direction === 'desc');
            // Pagina manualmente la Collection
            $perPage = 20;
            $page = $request->input('page', 1);
            $paged = $users->slice(($page - 1) * $perPage, $perPage)->values();
            $users = new LengthAwarePaginator($paged, $users->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        } elseif ($sort === 'account_type') {
            // Ordina per ruolo Spatie (primo ruolo alfabetico) lato PHP dopo la query
            $users = $query->get()->sortBy(function ($user) {
                return $user->getRoleNames()->first() ?? '';
            }, SORT_REGULAR, $direction === 'desc');
            // Pagina manualmente la Collection
            $perPage = 20;
            $page = $request->input('page', 1);
            $paged = $users->slice(($page - 1) * $perPage, $perPage)->values();
            $users = new LengthAwarePaginator($paged, $users->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        } elseif ($sort === 'role') {
            // Ordina per nome ruolo tabellare
            $query->leftJoin('job_roles', 'users.job_role_id', '=', 'job_roles.id')
                ->orderBy('job_roles.name', $direction)
                ->select('users.*');
            $users = $query->paginate(20)->appends($request->only(['search', 'sort', 'direction', 'show_trashed']));
        } else {
            $query->orderBy($sort, $direction);
            $users = $query->paginate(20)->appends($request->only(['search', 'sort', 'direction', 'show_trashed']));
        }

        return view('admin.users.index', compact('users', 'sort', 'direction', 'search', 'showTrashed', 'userRiskOverview'));
    }

    public function create(): View
    {
        $jobCategories = JobCategory::all();
        $jobLevels = JobLevel::all();
        $jobTasks = JobTask::all();
        $jobRoles = JobRole::all();
        $jobSectors = JobSector::all();
        $jobUnits = JobUnit::all();
        $languageLevels = LanguageLevel::query()->ordered()->get();

        return view('admin.users.create', compact('jobCategories', 'jobLevels', 'jobTasks', 'jobRoles', 'jobSectors', 'jobUnits', 'languageLevels'));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $this->userGeographyMapper->toHomeIds($request->validated());
        $roles = $this->resolveRoles($data['roles'] ?? ['user']);
        $isWorkerAccount = in_array('user', $roles, true);
        $jobTaskAssignments = $data['job_tasks'] ?? [];
        unset($data['roles']);
        unset($data['job_tasks']);
        $data['declared_language_level_id'] = $data['declared_language_level_id']
            ?? LanguageLevel::defaultOrFirst()?->getKey();
        $data['needs_language_level_verification'] = $this->needsLanguageLevelVerificationResolver
            ->resolve($data['is_foreigner_or_immigrant'] ?? false);

        // Normalizza i campi opzionali a null se stringa vuota
        foreach (['job_category_id', 'job_level_id'] as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        // Controllo coerenza geografica
        $geoConsistent = $this->checkGeographicConsistency(
            $data['home_city_id'] ?? null,
            $data['home_province_id'] ?? null,
            $data['home_region_id'] ?? null,
            $data['home_country_id'] ?? null
        );
        if ($geoConsistent === false) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['geography' => 'Attenzione: città, provincia, regione e nazione non sono coerenti tra loro.']);
        }

        $user = DB::transaction(function () use ($data, $isWorkerAccount, $jobTaskAssignments, $roles): User {
            $user = User::create($data);
            $user->syncRoles($roles);
            $this->userJobAssignmentService->syncAssignments($user, $jobTaskAssignments, $isWorkerAccount);

            return $user;
        });

        return redirect()->route('admin.users.index')->with('success', 'Utente creato con successo');
    }

    public function edit(User $user): View
    {
        $user->load('roles', 'homeCountry', 'homeRegion', 'homeProvince', 'homeCity', 'jobTasks');
        $jobCategories = JobCategory::all();
        $jobLevels = JobLevel::all();
        $jobTasks = JobTask::all();
        $jobRoles = JobRole::all();
        $jobSectors = JobSector::all();
        $jobUnits = JobUnit::all();
        $languageLevels = LanguageLevel::query()->ordered()->get();
        $allRiskBasedRequirements = RiskBasedRequirement::query()->orderBy('name')->get(['id', 'name']);
        $documentTypes = DocumentType::query()->orderBy('name')->get(['id', 'name']);
        $availableCourses = $user->courseEnrollments()
            ->where('status', CourseEnrollment::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->with(['course:id,title,code'])
            ->orderByDesc('completed_at')
            ->get()
            ->filter(fn (CourseEnrollment $enrollment): bool => $enrollment->course !== null)
            ->map(fn (CourseEnrollment $enrollment): array => [
                'id' => (int) $enrollment->course->getKey(),
                'code' => $enrollment->course->code,
                'title' => $enrollment->course->title,
                'completed_at_label' => $enrollment->completed_at?->format('d/m/Y'),
            ])
            ->unique('id')
            ->values();

        $riskSummary = $this->buildRiskSummary($user);

        return view('admin.users.edit', compact(
            'user',
            'jobCategories',
            'jobLevels',
            'jobTasks',
            'jobRoles',
            'jobSectors',
            'jobUnits',
            'languageLevels',
            'allRiskBasedRequirements',
            'documentTypes',
            'availableCourses',
            'riskSummary',
        ));
    }

    public function update(UserRequest $request, User $user): RedirectResponse|JsonResponse
    {
        $beforeRiskSnapshot = $this->captureRiskSnapshot($user->fresh(['jobSector', 'jobTasks', 'roles']));
        $data = $this->userGeographyMapper->toHomeIds($request->validated());
        $roles = $this->resolveRoles($data['roles'] ?? $user->getRoleNames()->all());
        $isWorkerAccount = in_array('user', $roles, true);
        $jobTaskAssignments = $data['job_tasks'] ?? [];
        unset($data['roles']);
        unset($data['job_tasks']);
        $data['needs_language_level_verification'] = $this->needsLanguageLevelVerificationResolver
            ->resolve($data['is_foreigner_or_immigrant'] ?? $user->is_foreigner_or_immigrant);

        // Normalizza i campi opzionali a null se stringa vuota
        foreach (['job_category_id', 'job_level_id'] as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        // Controllo coerenza geografica
        $geoConsistent = $this->checkGeographicConsistency(
            $data['home_city_id'] ?? null,
            $data['home_province_id'] ?? null,
            $data['home_region_id'] ?? null,
            $data['home_country_id'] ?? null
        );
        if ($geoConsistent === false) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['geography' => 'Attenzione: città, provincia, regione e nazione non sono coerenti tra loro.']);
        }

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        DB::transaction(function () use ($data, $isWorkerAccount, $jobTaskAssignments, $roles, $user): void {
            $user->update($data);
            $user->syncRoles($roles);
            $this->userJobAssignmentService->syncAssignments($user, $jobTaskAssignments, $isWorkerAccount);
        });

        $afterRiskSnapshot = $this->captureRiskSnapshot($user->fresh(['jobSector', 'jobTasks', 'roles']));
        $riskWarnings = $this->buildRiskChangeWarnings($beforeRiskSnapshot, $afterRiskSnapshot, $user);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Utente aggiornato con successo',
                'warnings' => $riskWarnings,
            ]);
        }

        $redirect = redirect()->route('admin.users.index')->with('success', 'Utente aggiornato con successo');

        if ($riskWarnings !== []) {
            $redirect->with('warning', collect($riskWarnings)->implode(' '));
        }

        return $redirect;
    }

    public function updateUserSection(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $beforeRiskSnapshot = $this->captureRiskSnapshot($user->fresh(['jobSector', 'jobTasks', 'roles']));

        if ($request->filled('fiscal_code')) {
            $request->merge([
                'fiscal_code' => Str::upper(trim((string) $request->input('fiscal_code'))),
            ]);
        }

        $data = $request->validate($this->userSectionRules($request, $user));
        $data['needs_language_level_verification'] = $this->needsLanguageLevelVerificationResolver
            ->resolve($data['is_foreigner_or_immigrant'] ?? $user->is_foreigner_or_immigrant);

        DB::transaction(function () use ($data, $user): void {
            $user->update($data);
        });

        $afterRiskSnapshot = $this->captureRiskSnapshot($user->fresh(['jobSector', 'jobTasks', 'roles']));
        $riskWarnings = $this->buildRiskChangeWarnings($beforeRiskSnapshot, $afterRiskSnapshot, $user);

        return $this->userSectionResponse($request, $user, 'user', 'Dati utente aggiornati con successo', $riskWarnings);
    }

    public function updatePermissionsSection(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $roles = $this->resolveRoles($request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'in:'.implode(',', $this->allowedAssignableRoles($request, $user))],
        ])['roles']);

        DB::transaction(function () use ($roles, $user): void {
            $user->syncRoles($roles);

            if (! in_array('user', $roles, true)) {
                $this->userJobAssignmentService->syncAssignments($user, [], false);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Permessi utente aggiornati con successo',
            ]);
        }

        return redirect()
            ->route('admin.users.edit', ['user' => $user, 'section' => 'permissions'])
            ->with('success', 'Permessi utente aggiornati con successo');
    }

    public function updateResidenceSection(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
        ]);

        $data = $this->userGeographyMapper->toHomeIds($validated);

        $geoConsistent = $this->checkGeographicConsistency(
            $data['home_city_id'] ?? null,
            $data['home_province_id'] ?? null,
            $data['home_region_id'] ?? null,
            $data['home_country_id'] ?? null
        );

        if ($geoConsistent === false) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['geography' => 'Attenzione: città, provincia, regione e nazione non sono coerenti tra loro.']);
        }

        $user->update($data);

        return $this->userSectionResponse($request, $user, 'residence', 'Residenza aggiornata con successo');
    }

    public function updateWorkSection(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $beforeRiskSnapshot = $this->captureRiskSnapshot($user->fresh(['jobSector', 'jobTasks', 'roles']));
        $isWorkerAccount = $user->hasRole('user');
        $validator = Validator::make($request->all(), $this->workSectionRules($isWorkerAccount));

        $validator->after(function ($validator) use ($request, $isWorkerAccount): void {
            if ($validator->errors()->isNotEmpty() || ! $isWorkerAccount) {
                return;
            }

            $assignments = collect($request->input('job_tasks', []))
                ->filter(fn (mixed $assignment): bool => is_array($assignment))
                ->values();

            if ($assignments->isEmpty()) {
                return;
            }

            $duplicateDefinitions = $assignments
                ->groupBy(fn (array $assignment): string => implode('|', [
                    (string) ($assignment['job_task_id'] ?? ''),
                    (string) ($assignment['starts_at'] ?? ''),
                    (string) ($assignment['ends_at'] ?? ''),
                ]))
                ->contains(fn ($group): bool => $group->count() > 1);

            if ($duplicateDefinitions) {
                $validator->errors()->add('job_tasks', __('Non puoi inserire due volte la stessa mansione con le stesse date.'));
            }
        });

        $data = $validator->validate();
        $jobTaskAssignments = $data['job_tasks'] ?? [];
        unset($data['job_tasks']);

        foreach (['job_category_id', 'job_level_id'] as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        DB::transaction(function () use ($data, $jobTaskAssignments, $user, $isWorkerAccount): void {
            $user->update($data);
            $this->userJobAssignmentService->syncAssignments($user, $jobTaskAssignments, $isWorkerAccount);
        });

        $afterRiskSnapshot = $this->captureRiskSnapshot($user->fresh(['jobSector', 'jobTasks', 'roles']));
        $riskWarnings = $this->buildRiskChangeWarnings($beforeRiskSnapshot, $afterRiskSnapshot, $user);

        return $this->userSectionResponse($request, $user, 'work', 'Dati lavoro aggiornati con successo', $riskWarnings);
    }

    public function riskSummaryApi(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->buildRiskSummary($user),
        ]);
    }

    public function riskCourseSelection(User $user): View
    {
        $user->loadMissing(['jobSector', 'jobTasks', 'roles']);

        $riskSummary = $this->buildRiskSummary($user);
        $enrolledCoursesByRequirement = $this->enrolledCourseTitlesByRequirement($user);
        $riskSummary['risk_based_requirements'] = collect($riskSummary['risk_based_requirements'])
            ->map(function (array $requirement) use ($enrolledCoursesByRequirement): array {
                $isMissingRequirement = ($requirement['status'] ?? null) === 'missing';
                $associatedCourseTitles = $isMissingRequirement
                    ? ($enrolledCoursesByRequirement[(int) ($requirement['risk_based_requirement_id'] ?? 0)] ?? [])
                    : [];

                $requirement['has_associated_course'] = $associatedCourseTitles !== [];
                $requirement['associated_course_titles'] = $associatedCourseTitles;

                return $requirement;
            })
            ->all();

        $requirementNeeds = $this->collectRequirementNeeds($riskSummary);
        $courses = Course::query()
            ->where('status', 'published')
            ->whereHas('riskBasedRequirements', function ($query) use ($requirementNeeds): void {
                $query->whereIn('risk_based_requirements.id', $requirementNeeds->keys());
            })
            ->with('riskBasedRequirements')
            ->orderBy('title')
            ->get()
            ->map(function (Course $course) use ($requirementNeeds, $user): array {
                $matchingRequirement = $course->riskBasedRequirements
                    ->first(fn (RiskBasedRequirement $requirement): bool => $requirementNeeds->has($requirement->getKey()));
                $requiredNeed = $matchingRequirement !== null
                    ? $requirementNeeds->get($matchingRequirement->getKey())
                    : null;
                $referenceDate = is_array($requiredNeed) && ! empty($requiredNeed['effective_on'])
                    ? Carbon::parse((string) $requiredNeed['effective_on'])
                    : null;

                return [
                    'course' => $course,
                    'matching_requirement' => $matchingRequirement,
                    'required_need' => $requiredNeed,
                    'course_validity_types' => $matchingRequirement !== null
                        ? $course->courseValidityTypesForRequirement($matchingRequirement)
                        : null,
                    'integrative_start_risk_levels' => $matchingRequirement !== null
                        ? $course->integrativeStartRiskLevelsForRequirement($matchingRequirement)
                        : collect(),
                    'matches_required_need' => $matchingRequirement !== null
                        && is_array($requiredNeed)
                        && $this->courseRiskRequirementService->courseRequirementMatchesUserNeed(
                            $user,
                            $matchingRequirement,
                            $course->courseValidityTypesForRequirement($matchingRequirement)->all(),
                            $referenceDate,
                        ),
                    'eligible_to_enroll' => $this->courseRiskRequirementService->userCanEnrollInCourse($user, $course),
                ];
            })
            ->filter(fn (array $entry): bool => $entry['matches_required_need'] === true)
            ->values();

        return view('admin.users.risk-course-selection', [
            'user' => $user,
            'riskSummary' => $riskSummary,
            'latestCertificates' => $user->latestCertificatesByRequirementGroup(),
            'courseRecommendations' => $courses,
        ]);
    }

    public function enrollRiskCourse(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'course_validity_type' => ['nullable', 'string', 'in:'.implode(',', array_map(fn ($case) => $case->value, CourseRiskRequirementValidityType::cases()))],
            'is_integrative_enrollment' => ['nullable', 'boolean'],
        ]);

        $course = Course::query()->with('riskBasedRequirements')->findOrFail($validated['course_id']);

        if (! $this->courseRiskRequirementService->userCanEnrollInCourse($user, $course)) {
            return redirect()
                ->route('admin.users.risk-course-selection', $user)
                ->with('error', __('L\'utente non possiede i prerequisiti necessari per l\'iscrizione a questo corso.'));
        }

        try {
            CourseEnrollment::enroll(
                $user,
                $course,
                $validated['course_validity_type'] ?? null,
                (bool) ($validated['is_integrative_enrollment'] ?? false),
            );
        } catch (\DomainException $exception) {
            return redirect()
                ->route('admin.users.risk-course-selection', $user)
                ->with('error', __($exception->getMessage()));
        }

        return redirect()
            ->route('admin.users.risk-course-selection', $user)
            ->with('status', __('Corso assegnato con successo all\'utente.'));
    }

    public function recommendedCoursesApi(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:title,course_type,validity_type'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'title';
        $direction = $validated['direction'] ?? 'asc';

        $user->loadMissing(['jobSector', 'jobTasks', 'roles']);

        $riskSummary = $this->buildRiskSummary($user);
        $requirementNeeds = $this->collectRequirementNeeds($riskSummary);
        $enrolledCourseIds = $this->enrolledCourseIds($user);

        if ($requirementNeeds->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'query' => [
                    'search' => $search,
                    'sort' => $sort,
                    'direction' => $direction,
                ],
            ]);
        }

        $query = Course::query()
            ->where('status', 'published')
            ->whereHas('riskBasedRequirements', function ($query) use ($requirementNeeds): void {
                $query->whereIn('risk_based_requirements.id', $requirementNeeds->keys());
            })
            ->with('riskBasedRequirements');

        // Ricerca globale
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('riskBasedRequirements', function ($q2) use ($search): void {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Calcola i dati per ogni corso prima dell'ordinamento
        $coursesData = $query->get()->map(function (Course $course) use ($enrolledCourseIds, $requirementNeeds, $user): ?array {
            $matchingRequirement = $course->riskBasedRequirements
                ->first(fn (RiskBasedRequirement $requirement): bool => $requirementNeeds->has($requirement->getKey()));

            if ($matchingRequirement === null) {
                return null;
            }

            $requiredNeed = $requirementNeeds->get($matchingRequirement->getKey());
            $courseValidityTypes = $course->courseValidityTypesForRequirement($matchingRequirement);
            $integrativeStartRiskLevels = $course->integrativeStartRiskLevelsForRequirement($matchingRequirement);
            $referenceDate = is_array($requiredNeed) && ! empty($requiredNeed['effective_on'])
                ? Carbon::parse((string) $requiredNeed['effective_on'])
                : null;
            $matchesRequiredNeed = $this->courseRiskRequirementService->courseRequirementMatchesUserNeed(
                $user,
                $matchingRequirement,
                $courseValidityTypes->all(),
                $referenceDate,
            );

            if (! $matchesRequiredNeed) {
                return null;
            }

            $eligibleToEnroll = $this->courseRiskRequirementService->userCanEnrollInCourse($user, $course);
            $isEnrolled = in_array((int) $course->getKey(), $enrolledCourseIds, true);

            // Ottieni tutti i requisiti coperti dal corso
            $coveredRequirements = $course->riskBasedRequirements
                ->filter(fn (RiskBasedRequirement $req): bool => $requirementNeeds->has($req->getKey()))
                ->map(fn (RiskBasedRequirement $req): array => [
                    'id' => $req->getKey(),
                    'name' => $req->name,
                ])
                ->values()
                ->all();

            // Determina i prerequisiti necessari
            $prerequisites = [];
            if (
                $courseValidityTypes->contains(fn (CourseRiskRequirementValidityType $validityType): bool => $validityType === CourseRiskRequirementValidityType::Integrative)
                && $integrativeStartRiskLevels->isNotEmpty()
            ) {
                $prerequisites = $integrativeStartRiskLevels->map(fn ($level) => $level->label())->all();
            }

            return [
                'course_id' => $course->getKey(),
                'code' => $course->code,
                'title' => $course->title,
                'description' => $course->description,
                'course_type' => $course->course_type,
                'course_type_label' => match ($course->course_type) {
                    'fad' => 'FAD',
                    'res' => 'RES',
                    'webinar' => 'Webinar',
                    'blended' => 'Blended',
                    default => $course->course_type,
                },
                'validity_types' => $courseValidityTypes->pluck('value')->all(),
                'validity_type_label' => $courseValidityTypes->isEmpty()
                    ? '—'
                    : CourseRiskRequirementValidityType::labelsText($courseValidityTypes->all()),
                'covered_requirements' => $coveredRequirements,
                'prerequisites' => $prerequisites,
                'prerequisites_label' => empty($prerequisites)
                    ? '—'
                    : __('Livelli iniziali ammessi: :levels', ['levels' => implode(', ', $prerequisites)]),
                'is_enrolled' => $isEnrolled,
                'eligible_to_enroll' => $eligibleToEnroll,
                'ineligible_reason' => $eligibleToEnroll
                    ? null
                    : __('L\'utente non può essere iscritto perché manca un attestato valido di partenza richiesto dal corso integrativo.'),
            ];
        })
            ->filter()
            ->values();

        // Ordinamento
        $coursesData = match ($sort) {
            'title' => $direction === 'asc'
                ? $coursesData->sortBy('title')
                : $coursesData->sortByDesc('title'),
            'course_type' => $direction === 'asc'
                ? $coursesData->sortBy('course_type_label')
                : $coursesData->sortByDesc('course_type_label'),
            'validity_type' => $direction === 'asc'
                ? $coursesData->sortBy('validity_type_label')
                : $coursesData->sortByDesc('validity_type_label'),
            default => $coursesData,
        };

        // Paginazione manuale
        $perPage = 10;
        $currentPage = (int) ($validated['page'] ?? 1);
        $total = $coursesData->count();
        $items = $coursesData->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $items->all(),
            'meta' => [
                'current_page' => $currentPage,
                'last_page' => (int) ceil($total / $perPage),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $items->isEmpty() ? null : (($currentPage - 1) * $perPage) + 1,
                'to' => $items->isEmpty() ? null : (($currentPage - 1) * $perPage) + $items->count(),
            ],
            'query' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $riskSummary
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRequirementNeeds(array $riskSummary)
    {
        $currentNeeds = collect($riskSummary['risk_based_requirements'] ?? [])
            ->filter(fn (array $requirement): bool => in_array($requirement['status'] ?? null, ['missing', 'expired'], true))
            ->mapWithKeys(fn (array $requirement): array => [
                (int) ($requirement['risk_based_requirement_id'] ?? 0) => [
                    ...$requirement,
                    'effective_on' => null,
                    'effective_on_label' => null,
                ],
            ])
            ->filter(fn (array $requirement, int $requirementId): bool => $requirementId > 0);

        $futureNeeds = collect($riskSummary['future_risk_transitions'] ?? [])
            ->flatMap(function (array $transition) {
                $effectiveOn = $transition['effective_on'] ?? null;
                $effectiveOnLabel = $transition['effective_on_label'] ?? null;

                return collect($transition['risk_based_requirements'] ?? [])
                    ->filter(fn (array $requirement): bool => in_array($requirement['status'] ?? null, ['missing', 'expired'], true))
                    ->map(function (array $requirement) use ($effectiveOn, $effectiveOnLabel): array {
                        return [
                            ...$requirement,
                            'effective_on' => $effectiveOn,
                            'effective_on_label' => $effectiveOnLabel,
                        ];
                    });
            })
            ->filter(fn (array $requirement): bool => (int) ($requirement['risk_based_requirement_id'] ?? 0) > 0)
            ->groupBy(fn (array $requirement): int => (int) $requirement['risk_based_requirement_id'])
            ->map(function ($requirements) {
                return collect($requirements)
                    ->sortBy(fn (array $requirement): string => (string) ($requirement['effective_on'] ?? '9999-12-31'))
                    ->first();
            });

        return $currentNeeds->union($futureNeeds);
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Utente eliminato con successo');
    }

    public function restore($id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return redirect()->route('admin.users.index')->with('success', 'Utente ripristinato con successo');
    }

    /**
     * Verifica la coerenza tra città, provincia, regione e nazione (solo per i valori inseriti).
     * Restituisce true se coerenti, false se incoerenti, null se non verificabile.
     */
    private function checkGeographicConsistency(?int $cityId, ?int $provinceId, ?int $regionId, ?int $countryId): ?bool
    {
        // Se nessun valore è inserito, non verificabile
        if (! $cityId && ! $provinceId && ! $regionId && ! $countryId) {
            return null;
        }

        $city = $cityId ? WorldCity::find($cityId) : null;
        $province = $provinceId ? Province::find($provinceId) : null;
        $region = $regionId ? WorldDivision::find($regionId) : null;
        $country = $countryId ? WorldCountry::find($countryId) : null;

        // Verifica coerenza città-provincia
        if ($city && $province && $city->province_id !== $province->id) {
            return false;
        }

        // Verifica coerenza provincia-regione
        if ($province && $region && $province->region_id !== $region->id) {
            return false;
        }

        // Verifica coerenza regione-nazione
        if ($region && $country && $region->country_id !== $country->id) {
            return false;
        }

        // Verifica coerenza città-regione (se manca la provincia)
        if ($city && $region && $city->region_id && $city->region_id !== $region->id) {

            return false;
        }

        // Verifica coerenza città-nazione (se manca la provincia e la regione)
        if ($city && $country && $city->country_id && $city->country_id !== $country->id) {
            return false;
        }

        // Se tutte le verifiche passano o non sono verificabili, ritorna true
        return true;
    }

    private function resolveAccountType(string $accountType): string
    {
        if (! in_array($accountType, ['teacher', 'docente'], true)) {
            return $accountType;
        }

        $availableTeacherRoles = Role::query()
            ->whereIn('name', ['teacher', 'docente'])
            ->where('guard_name', config('auth.defaults.guard'))
            ->pluck('name');

        if ($availableTeacherRoles->contains($accountType)) {
            return $accountType;
        }

        return $availableTeacherRoles->first() ?? $accountType;
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private function resolveRoles(array $roles): array
    {
        return collect($roles)
            ->map(fn (string $role): string => $this->resolveAccountType($role))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function allowedAssignableRoles(Request $request, User $routeUser): array
    {
        if (! $request->user()?->hasRole('superadmin')) {
            return $routeUser->getRoleNames()->all() ?: ['user'];
        }

        $roles = ['user', 'admin', 'teacher', 'docente', 'tutor'];

        if ($routeUser->hasRole('superadmin')) {
            $roles[] = 'superadmin';
        }

        return array_values(array_unique($roles));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function userSectionRules(Request $request, User $routeUser): array
    {
        $isWorkerAccount = $routeUser->hasRole('user');

        return [
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($routeUser->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'fiscal_code' => ['required', 'string', 'size:16', Rule::unique('users', 'fiscal_code')->ignore($routeUser->getKey())],
            'phone_prefix' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:1'],
            'is_foreigner_or_immigrant' => [$isWorkerAccount ? 'required' : 'nullable', 'boolean'],
            'declared_language_level_id' => ['nullable', 'integer', 'exists:language_levels,id'],
            'verified_language_level_id' => ['nullable', 'integer', 'exists:language_levels,id'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function workSectionRules(bool $isWorkerAccount): array
    {
        return [
            'employment_start_date' => [$isWorkerAccount ? 'required' : 'nullable', 'date'],
            'employment_end_date' => ['nullable', 'date', 'after_or_equal:employment_start_date'],
            'job_role_id' => [$isWorkerAccount ? 'required' : 'nullable', 'exists:job_roles,id'],
            'job_sector_id' => [$isWorkerAccount ? 'required' : 'nullable', 'exists:job_sectors,id'],
            'job_unit_id' => [$isWorkerAccount ? 'required' : 'nullable', 'exists:job_units,id'],
            'job_tasks' => [$isWorkerAccount ? 'required' : 'nullable', 'array', 'min:1'],
            'job_tasks.*.job_task_id' => ['required_with:job_tasks', 'exists:job_tasks,id'],
            'job_tasks.*.starts_at' => ['required_with:job_tasks', 'date'],
            'job_tasks.*.ends_at' => ['nullable', 'date'],
            'job_category_id' => ['nullable', 'exists:job_categories,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
        ];
    }

    /**
     * @param  array<int, string>  $riskWarnings
     */
    private function userSectionResponse(Request $request, User $user, string $section, string $message, array $riskWarnings = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'warnings' => $riskWarnings,
            ]);
        }

        $redirect = redirect()
            ->route('admin.users.edit', ['user' => $user, 'section' => $section])
            ->with('success', $message);

        if ($riskWarnings !== []) {
            $redirect->with('warning', collect($riskWarnings)->implode(' '));
        }

        return $redirect;
    }

    /**
     * @return array{
     *     total_users: int,
     *     classified_users: int,
     *     unclassified_users: int,
     *     risk_counts: array<int, array{
     *         level: string,
     *         label: string,
     *         count: int,
     *         value_class: string
     *     }>
     * }
     */
    private function buildUserRiskOverview(Builder $query): array
    {
        $riskCounts = collect(RiskLevel::ordered())
            ->mapWithKeys(fn (RiskLevel $riskLevel): array => [$riskLevel->value => 0]);

        $workerRoleName = $this->resolveAccountType('user');
        $totalUsers = (clone $query)->count();

        $classifiedUsers = (clone $query)
            ->with(['jobSector', 'jobTasks', 'roles'])
            ->whereHas('roles', fn (Builder $builder): Builder => $builder->where('name', $workerRoleName))
            ->get()
            ->reduce(function ($counts, User $user) {
                $riskLevel = rescue(
                    fn (): ?RiskLevel => $user->getEffectiveWorkerRisk(),
                    null,
                    false,
                );

                if (! $riskLevel instanceof RiskLevel) {
                    return $counts;
                }

                $counts->put($riskLevel->value, $counts->get($riskLevel->value, 0) + 1);

                return $counts;
            }, $riskCounts);

        $classifiedTotal = $classifiedUsers->sum();

        return [
            'total_users' => $classifiedTotal,
            'classified_users' => $classifiedTotal,
            'unclassified_users' => $totalUsers - $classifiedTotal,
            'risk_counts' => collect(RiskLevel::ordered())
                ->map(function (RiskLevel $riskLevel) use ($classifiedUsers): array {
                    return [
                        'level' => $riskLevel->value,
                        'label' => $riskLevel->label(),
                        'count' => $classifiedUsers->get($riskLevel->value, 0),
                        'value_class' => match ($riskLevel) {
                            RiskLevel::LOW => 'text-success',
                            RiskLevel::MEDIUM => 'text-warning',
                            RiskLevel::HIGH => 'text-error',
                        },
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     risk_label: ?string,
     *     risk_badge_class: string,
     *     is_applicable: bool,
     *     message: string,
     *     future_risk_transitions: array<int, array{
     *         effective_on: string,
     *         effective_on_label: string,
     *         risk_level: string,
     *         risk_label: string,
     *         risk_badge_class: string,
     *         risk_based_requirements: array<int, array{
     *             risk_based_requirement_id: int,
     *             risk_based_requirement_name: string,
     *             risk_based_requirement_description: ?string,
     *             satisfied: bool,
     *             status: string,
     *             status_label: string,
     *             certificate_expires_at: ?string,
     *             required_course_validity_type_label: ?string
     *         }>
     *     }>,
     *     risk_based_requirements: array<int, array{
     *         risk_based_requirement_id: int,
     *         risk_based_requirement_name: string,
     *         risk_based_requirement_description: ?string,
     *         satisfied: bool,
     *         status: string,
     *         status_label: string,
     *         certificate_expires_at: ?string,
     *         expires_at: ?string,
     *         expires_at_label: ?string,
     *         certificate_ids: array<int, int>,
     *         certificate_names: array<int, string>
     *     }>
     * }
     */
    private function buildRiskSummary(User $user): array
    {
        try {
            $effectiveRisk = $user->getEffectiveWorkerRisk();
            $riskBasedRequirementsCompliance = $user->checkRiskBasedRequirementsCompliance();

            // Aggiungi certificate_expires_at ai requisiti correnti
            $enrichedCurrentRequirements = $riskBasedRequirementsCompliance->map(function ($requirement) {
                $requirement['certificate_expires_at'] = $requirement['expires_at_label'];

                return $requirement;
            })->all();

            // Ottieni le transizioni future con i requisiti
            $futureTransitions = $user->futureRiskTransitions()->map(function ($transition) use ($user) {
                // Per ogni transizione futura, calcola i requisiti a quella data
                $futureDate = Carbon::parse($transition['effective_on']);
                $futureRiskLevel = RiskLevel::from($transition['risk_level']);

                // Ottieni i requisiti per quel livello di rischio
                $riskCalculationService = app(RiskCalculationService::class);
                $courseRiskRequirementService = app(CourseRiskRequirementService::class);
                $futureRequirements = collect($riskCalculationService->getRiskBasedRequirementsForRiskLevel($futureRiskLevel))
                    ->filter(fn ($req) => $req instanceof RiskBasedRequirement)
                    ->map(function (RiskBasedRequirement $req) use ($user, $courseRiskRequirementService) {
                        $coverage = $courseRiskRequirementService->bestValidCertificateCoverageForRequirement($user, $req);
                        $bestValidCertificate = $coverage['certificate'] ?? null;
                        $matchingExpiredCertificates = $courseRiskRequirementService->expiredCertificatesForRequirement($user, $req);
                        $isSatisfied = $bestValidCertificate instanceof UserCertificate;
                        $expiresAt = $bestValidCertificate?->expires_at;
                        $status = $isSatisfied ? 'satisfied' : ($matchingExpiredCertificates->isNotEmpty() ? 'expired' : 'missing');
                        $requiredCourseValidityType = in_array($status, ['missing', 'expired'], true)
                            ? $courseRiskRequirementService->determineRequiredCourseValidityType($user, $req)
                            : null;

                        return [
                            'risk_based_requirement_id' => (int) $req->getKey(),
                            'risk_based_requirement_name' => $req->name,
                            'risk_based_requirement_description' => $req->description,
                            'satisfied' => $isSatisfied,
                            'status' => $status,
                            'status_label' => match ($status) {
                                'satisfied' => $expiresAt === null
                                    ? __('Soddisfatto')
                                    : __('Soddisfatto fino al :date', ['date' => $expiresAt->format('d/m/Y')]),
                                'expired' => __('Scaduto'),
                                default => __('Mancante'),
                            },
                            'certificate_expires_at' => $expiresAt?->format('d/m/Y'),
                            'required_course_validity_type_label' => $requiredCourseValidityType?->label(),
                        ];
                    })
                    ->values()
                    ->all();

                $transition['risk_based_requirements'] = $futureRequirements;

                return $transition;
            })->all();

            return [
                'risk_label' => $effectiveRisk->label(),
                'risk_badge_class' => $effectiveRisk->badgeColor(),
                'is_applicable' => true,
                'message' => $riskBasedRequirementsCompliance->isEmpty()
                    ? __('Nessun requisito di rischio disponibile per il rischio corrente.')
                    : __('Requisiti di rischio in base al rischio effettivo dell\'utente.'),
                'future_risk_transitions' => $futureTransitions,
                'risk_based_requirements' => $enrichedCurrentRequirements,
            ];
        } catch (\LogicException) {
            return [
                'risk_label' => null,
                'risk_badge_class' => 'badge-ghost',
                'is_applicable' => false,
                'message' => __('Nessun requisito di rischio disponibile per il rischio corrente o utente non classificabile come lavoratore.'),
                'future_risk_transitions' => [],
                'risk_based_requirements' => [],
            ];
        }
    }

    /**
     * @return array{current_risk: ?string, future_risks: array<int, string>}
     */
    private function captureRiskSnapshot(User $user): array
    {
        $currentRisk = rescue(
            fn (): ?string => $user->getEffectiveWorkerRisk()->value,
            null,
            false,
        );
        $futureRisks = rescue(
            fn (): array => $user->futureRiskTransitions()->pluck('risk_level')->all(),
            [],
            false,
        );

        return [
            'current_risk' => $currentRisk,
            'future_risks' => $futureRisks,
        ];
    }

    /**
     * @param  array{current_risk: ?string, future_risks: array<int, string>}  $beforeRiskSnapshot
     * @param  array{current_risk: ?string, future_risks: array<int, string>}  $afterRiskSnapshot
     * @return array<int, string>
     */
    private function buildRiskChangeWarnings(array $beforeRiskSnapshot, array $afterRiskSnapshot, User $user): array
    {
        $warnings = [];

        if ($beforeRiskSnapshot['current_risk'] !== $afterRiskSnapshot['current_risk']) {
            $beforeRiskLabel = $this->riskLabelFromValue($beforeRiskSnapshot['current_risk']);
            $afterRiskLabel = $this->riskLabelFromValue($afterRiskSnapshot['current_risk']);

            if ($afterRiskLabel === null) {
                $warnings[] = __('Il rischio attuale di :user non è più calcolabile dopo questa modifica.', ['user' => $user->full_name]);
            } elseif ($beforeRiskLabel === null) {
                $warnings[] = __('Il rischio attuale di :user è ora calcolato come :risk.', [
                    'user' => $user->full_name,
                    'risk' => $afterRiskLabel,
                ]);
            } else {
                $warnings[] = __('Il rischio attuale di :user è cambiato da :before a :after.', [
                    'user' => $user->full_name,
                    'before' => $beforeRiskLabel,
                    'after' => $afterRiskLabel,
                ]);
            }
        }

        if ($beforeRiskSnapshot['future_risks'] !== $afterRiskSnapshot['future_risks']) {
            $warnings[] = __('Le mansioni programmate modificano anche il rischio futuro. Verifica il corso da assegnare nella selezione manuale.');
        }

        return $warnings;
    }

    private function riskLabelFromValue(?string $riskLevel): ?string
    {
        return RiskLevel::tryFrom((string) $riskLevel)?->label();
    }

    /**
     * @return array<int, int>
     */
    private function enrolledCourseIds(User $user): array
    {
        return $user->courseEnrollments()
            ->whereNotIn('status', [
                CourseEnrollment::STATUS_CANCELLED,
                CourseEnrollment::STATUS_EXPIRED,
            ])
            ->pluck('course_id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function enrolledCourseTitlesByRequirement(User $user): array
    {
        $courseTitlesByRequirement = $user->courseEnrollments()
            ->whereNotIn('status', [
                CourseEnrollment::STATUS_CANCELLED,
                CourseEnrollment::STATUS_EXPIRED,
            ])
            ->with([
                'course:id,title',
                'course.riskBasedRequirements:id,name',
            ])
            ->get()
            ->reduce(function (array $carry, CourseEnrollment $courseEnrollment): array {
                $course = $courseEnrollment->course;

                if ($course === null) {
                    return $carry;
                }

                foreach ($course->riskBasedRequirements as $riskBasedRequirement) {
                    $requirementId = (int) $riskBasedRequirement->getKey();
                    $carry[$requirementId] ??= [];
                    $carry[$requirementId][$course->getKey()] = $course->title;
                }

                return $carry;
            }, []);

        return collect($courseTitlesByRequirement)
            ->map(fn (array $titles): array => array_values($titles))
            ->all();
    }
}
