<x-layouts.admin>
    @php
        $moduleTypeIcons = [
            'video' => 'lucide-clapperboard',
            'res' => 'lucide-users',
            'live' => 'lucide-monitor-play',
            'scorm' => 'lucide-package',
            'learning_quiz' => 'lucide-circle-help',
            'satisfaction_quiz' => 'lucide-message-square-heart',
        ];
        $courseEditSections = collect([
            ['key' => 'details', 'label' => __('Dati anagrafici corso'), 'icon' => 'lucide-book-open-text'],
            ['key' => 'duration', 'label' => __('Durata corso'), 'icon' => 'lucide-clock-3'],
            ['key' => 'survey', 'label' => __('Questionario di gradimento'), 'icon' => 'lucide-message-square-heart'],
            ['key' => 'certificates', 'label' => __('Certificati ottenibili'), 'icon' => 'lucide-file-badge'],
            ['key' => 'modules', 'label' => __('Moduli'), 'icon' => 'lucide-blocks'],
            ['key' => 'teachers', 'label' => __('Docenti'), 'icon' => 'lucide-graduation-cap'],
            ['key' => 'tutors', 'label' => __('Tutor'), 'icon' => 'lucide-users-round'],
            ['key' => 'enrollments', 'label' => __('Iscritti'), 'icon' => 'lucide-user-plus'],
            ['key' => 'operations', 'label' => __('Operazioni corso'), 'icon' => 'lucide-wrench'],
        ]);
        $activeCourseEditSection = request('section', 'details');

        if (! $courseEditSections->contains(fn (array $section): bool => $section['key'] === $activeCourseEditSection)) {
            $activeCourseEditSection = 'details';
        }

        $courseUpdateUrl = route('admin.courses.update', $course).'?section='.$activeCourseEditSection;
        $courseDetailAccordionFields = [
            'teaching_material' => __('Materiale didattico'),
            'internal_notes' => __('Note interne corso'),
            'training_objective' => __('Obiettivo formativo'),
            'knowledge' => __('Conoscenze'),
            'skills' => __('Abilità'),
            'competences' => __('Competenze'),
            'regulatory_reference' => __('Riferimento normativo'),
        ];
        $courseBaseValues = [
            'title' => old('title', $course->title),
            'description' => old('description', $course->description),
            'teaching_material' => old('teaching_material', $course->teaching_material),
            'max_participants' => old('max_participants', $course->max_participants),
            'internal_notes' => old('internal_notes', $course->internal_notes),
            'training_objective' => old('training_objective', $course->training_objective),
            'knowledge' => old('knowledge', $course->knowledge),
            'skills' => old('skills', $course->skills),
            'competences' => old('competences', $course->competences),
            'regulatory_reference' => old('regulatory_reference', $course->regulatory_reference),
            'course_start_date' => old('course_start_date', $course->course_start_date?->format('Y-m-d')),
            'course_end_date' => old('course_end_date', $course->course_end_date?->format('Y-m-d')),
            'access_closure_date' => old('access_closure_date', $course->access_closure_date?->format('Y-m-d')),
            'course_duration_hours' => old('course_duration_hours', $course->course_duration_hours),
            'interaction_duration_minutes' => old('interaction_duration_minutes', $course->interaction_duration_minutes),
            'year' => old('year', $course->year),
            'expiry_date' => old('expiry_date', $course->expiry_date?->format('Y-m-d')),
            'status' => old('status', $course->status),
            'has_satisfaction_survey' => (bool) old('has_satisfaction_survey', $course->has_satisfaction_survey),
            'satisfaction_survey_required_for_certificate' => (bool) old(
                'satisfaction_survey_required_for_certificate',
                $course->satisfaction_survey_required_for_certificate
            ),
        ];
        $selectedRiskBasedRequirementIds = collect(old(
            'risk_based_requirement_ids',
            $course->riskBasedRequirements->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ))->map(fn ($id) => (string) $id);
        $selectedRiskBasedRequirementValidityTypes = collect(
            old(
                'risk_based_requirement_validity_types',
                $course->riskBasedRequirements
                    ->mapWithKeys(fn ($riskBasedRequirement) => [
                        (string) $riskBasedRequirement->getKey() => $course
                            ->courseValidityTypesForRequirement($riskBasedRequirement)
                            ->pluck('value')
                            ->all(),
                    ])
                    ->all(),
            )
        );
        $allRiskBasedRequirementsPayload = $riskBasedRequirements
            ->map(fn ($riskBasedRequirement) => [
                'id' => (int) $riskBasedRequirement->getKey(),
                'name' => $riskBasedRequirement->name,
                'description' => $riskBasedRequirement->description,
                'risk_levels' => $riskBasedRequirement->risk_levels->pluck('value')->values()->all(),
                'single_risk_level' => $riskBasedRequirement->singleRiskLevel()?->value,
            ])
            ->values();
        $selectedRiskBasedRequirementsPayload = $riskBasedRequirements
            ->filter(fn ($riskBasedRequirement) => $selectedRiskBasedRequirementIds->contains((string) $riskBasedRequirement->getKey()))
            ->map(fn ($riskBasedRequirement) => [
                'id' => (int) $riskBasedRequirement->getKey(),
                'name' => $riskBasedRequirement->name,
                'description' => $riskBasedRequirement->description,
                'risk_levels' => $riskBasedRequirement->risk_levels->pluck('value')->values()->all(),
                'single_risk_level' => $riskBasedRequirement->singleRiskLevel()?->value,
                'course_validity_types' => collect($selectedRiskBasedRequirementValidityTypes->get(
                    (string) $riskBasedRequirement->getKey(),
                    [],
                ))->filter()->values()->all(),
                'integrative_start_risk_levels' => collect(old(
                    'risk_based_requirement_integrative_start_levels.'.(string) $riskBasedRequirement->getKey(),
                    $course->integrativeStartRiskLevelsForRequirement($riskBasedRequirement)->pluck('value')->all(),
                ))->filter()->values()->all(),
            ])
            ->values();
    @endphp

    <div
        class="min-h-screen bg-base-100"
        data-course-edit-page
        data-has-create-module-errors="{{ $errors->has('type') || $errors->has('title') ? 'true' : 'false' }}"
        data-course-is-published="{{ $course->status === 'published' ? 'true' : 'false' }}"
    >
        <div class="grid min-h-screen w-full lg:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="border-b border-base-300 bg-base-200 p-4 lg:min-h-screen lg:border-b-0 lg:border-r">
                <div class="lg:sticky lg:top-4">
                    <div class="overflow-x-auto">
                        <ul class="menu menu-horizontal w-max gap-1 lg:menu-vertical lg:w-full">
                            @foreach ($courseEditSections as $section)
                                <li class="w-full">
                                    <a
                                        href="{{ route('admin.courses.edit', $course).'?section='.$section['key'] }}"
                                        @class([
                                            'w-full whitespace-nowrap',
                                            'menu-active' => $activeCourseEditSection === $section['key'],
                                        ])
                                    >
                                        <x-dynamic-component :component="$section['icon']" class="mr-2 inline-block h-5 w-5" />
                                        {{ $section['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </aside>

            <main class="min-w-0">
                <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                    <x-page-header :title="__('Modifica corso')" />

                    <div class="flex flex-col gap-6">
                        @if ($activeCourseEditSection === 'details')
                            <form method="POST" action="{{ $courseUpdateUrl }}" class="flex flex-col gap-6">
                                @include('admin.course.partials.course-edit-badge-bar')

                                <div class="card border border-base-300 bg-base-100 shadow-sm">
                                    <div class="card-body gap-6">
                                        <div class="flex items-start gap-4">
                                            <div class="flex-1">
                                                <h2 class="card-title">{{ __('Dati anagrafici corso') }}</h2>
                                                <p class="text-sm text-base-content/70">
                                                    {{ __('Gestisci le informazioni principali del corso.') }}
                                                </p>
                                            </div>
                                        </div>

                                        @csrf
                                        @method('PUT')

                                        <input type="hidden" name="expiry_date" value="{{ $courseBaseValues['expiry_date'] }}">
                                        <input type="hidden" name="has_satisfaction_survey" value="{{ $courseBaseValues['has_satisfaction_survey'] ? '1' : '0' }}">
                                        <input type="hidden" name="satisfaction_survey_required_for_certificate" value="{{ $courseBaseValues['satisfaction_survey_required_for_certificate'] ? '1' : '0' }}">
                                        @foreach ($selectedRiskBasedRequirementsPayload as $selectedRiskBasedRequirement)
                                            <input type="hidden" name="risk_based_requirement_ids[]" value="{{ $selectedRiskBasedRequirement['id'] }}">
                                            @foreach ($selectedRiskBasedRequirement['course_validity_types'] as $courseValidityType)
                                                <input type="hidden" name="risk_based_requirement_validity_types[{{ $selectedRiskBasedRequirement['id'] }}][]" value="{{ $courseValidityType }}">
                                            @endforeach
                                            @foreach ($selectedRiskBasedRequirement['integrative_start_risk_levels'] as $integrativeStartRiskLevel)
                                                <input type="hidden" name="risk_based_requirement_integrative_start_levels[{{ $selectedRiskBasedRequirement['id'] }}][]" value="{{ $integrativeStartRiskLevel }}">
                                            @endforeach
                                        @endforeach

                                        <div class="grid gap-6 md:grid-cols-2">
                                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                                <label for="title" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Titolo del corso') }}</span>
                                                </label>
                                                <input
                                                    id="title"
                                                    name="title"
                                                    type="text"
                                                    value="{{ $courseBaseValues['title'] }}"
                                                    class="input input-bordered w-full @error('title') input-error @enderror"
                                                    required
                                                >
                                                @error('title')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                                <label for="description" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                                                </label>
                                                <textarea
                                                    id="description"
                                                    name="description"
                                                    class="textarea textarea-bordered min-h-32 w-full @error('description') textarea-error @enderror"
                                                    required
                                                >{{ $courseBaseValues['description'] }}</textarea>
                                                @error('description')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            @if (in_array($course->type, ['res', 'async', 'blended'], true))
                                                <div class="form-control flex flex-col gap-2">
                                                    <label for="max_participants" class="label p-0">
                                                        <span class="label-text font-medium">{{ __('Numero massimo partecipanti') }}</span>
                                                    </label>
                                                    <input
                                                        id="max_participants"
                                                        name="max_participants"
                                                        type="number"
                                                        min="1"
                                                        value="{{ $courseBaseValues['max_participants'] }}"
                                                        class="input input-bordered w-full @error('max_participants') input-error @enderror"
                                                    >
                                                    @error('max_participants')
                                                        <p class="text-sm text-error">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            @endif

                                            <div class="form-control flex flex-col gap-2">
                                                <label for="year" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Anno del corso') }}</span>
                                                </label>
                                                <input
                                                    id="year"
                                                    name="year"
                                                    type="number"
                                                    value="{{ $courseBaseValues['year'] }}"
                                                    class="input input-bordered w-full @error('year') input-error @enderror"
                                                    min="1900"
                                                    max="2100"
                                                    required
                                                >
                                                @error('year')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label for="edition" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Edizione') }}</span>
                                                </label>
                                                <input
                                                    id="edition"
                                                    type="number"
                                                    value="{{ $course->edition }}"
                                                    class="input input-bordered w-full"
                                                    readonly
                                                    disabled
                                                >
                                            </div>

                                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                                <label for="status" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Stato') }}</span>
                                                </label>
                                                <select
                                                    id="status"
                                                    name="status"
                                                    class="select select-bordered w-full @error('status') select-error @enderror"
                                                    required
                                                >
                                                    @foreach ($courseStatusLabels as $courseStatus => $courseStatusLabel)
                                                        <option value="{{ $courseStatus }}" @selected($courseBaseValues['status'] === $courseStatus)>
                                                            {{ $courseStatusLabel }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('status')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="flex flex-col gap-3">
                                    @foreach ($courseDetailAccordionFields as $fieldKey => $fieldLabel)
                                        <div class="collapse collapse-arrow border border-base-300 bg-base-100 shadow-sm">
                                            <input type="radio" name="course-details-accordion" @checked($loop->first) />
                                            <div class="collapse-title text-base font-medium">
                                                {{ $fieldLabel }}
                                            </div>
                                            <div class="collapse-content">
                                                <div class="form-control flex flex-col gap-2 pt-1">
                                                    <textarea
                                                        id="{{ $fieldKey }}"
                                                        name="{{ $fieldKey }}"
                                                        class="textarea textarea-bordered min-h-32 w-full @error($fieldKey) textarea-error @enderror"
                                                    >{{ $courseBaseValues[$fieldKey] }}</textarea>
                                                    @error($fieldKey)
                                                        <p class="text-sm text-error">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-primary">
                                        <span>{{ __('Salva dati') }}</span>
                                        <x-lucide-save class="h-4 w-4" />
                                    </button>
                                </div>
                            </form>
                        @elseif ($activeCourseEditSection === 'duration')
                            <form method="POST" action="{{ $courseUpdateUrl }}" class="flex flex-col gap-6">
                                @include('admin.course.partials.course-edit-badge-bar')

                                <div class="card border border-base-300 bg-base-100 shadow-sm">
                                    <div class="card-body gap-6">
                                        <div class="flex items-start gap-4">
                                            <div class="flex-1">
                                                <h2 class="card-title">{{ __('Durata corso') }}</h2>
                                                <p class="text-sm text-base-content/70">
                                                    {{ __('Gestisci date e durata del corso.') }}
                                                </p>
                                            </div>
                                        </div>

                                        @csrf
                                        @method('PUT')

                                        <input type="hidden" name="title" value="{{ $courseBaseValues['title'] }}">
                                        <input type="hidden" name="description" value="{{ $courseBaseValues['description'] }}">
                                        <input type="hidden" name="teaching_material" value="{{ $courseBaseValues['teaching_material'] }}">
                                        @if (in_array($course->type, ['res', 'async', 'blended'], true) && $courseBaseValues['max_participants'] !== null && $courseBaseValues['max_participants'] !== '')
                                            <input type="hidden" name="max_participants" value="{{ $courseBaseValues['max_participants'] }}">
                                        @endif
                                        <input type="hidden" name="internal_notes" value="{{ $courseBaseValues['internal_notes'] }}">
                                        <input type="hidden" name="training_objective" value="{{ $courseBaseValues['training_objective'] }}">
                                        <input type="hidden" name="knowledge" value="{{ $courseBaseValues['knowledge'] }}">
                                        <input type="hidden" name="skills" value="{{ $courseBaseValues['skills'] }}">
                                        <input type="hidden" name="competences" value="{{ $courseBaseValues['competences'] }}">
                                        <input type="hidden" name="regulatory_reference" value="{{ $courseBaseValues['regulatory_reference'] }}">
                                        <input type="hidden" name="year" value="{{ $courseBaseValues['year'] }}">
                                        <input type="hidden" name="status" value="{{ $courseBaseValues['status'] }}">
                                        <input type="hidden" name="has_satisfaction_survey" value="{{ $courseBaseValues['has_satisfaction_survey'] ? '1' : '0' }}">
                                        <input type="hidden" name="satisfaction_survey_required_for_certificate" value="{{ $courseBaseValues['satisfaction_survey_required_for_certificate'] ? '1' : '0' }}">
                                        @foreach ($selectedRiskBasedRequirementsPayload as $selectedRiskBasedRequirement)
                                            <input type="hidden" name="risk_based_requirement_ids[]" value="{{ $selectedRiskBasedRequirement['id'] }}">
                                            @foreach ($selectedRiskBasedRequirement['course_validity_types'] as $courseValidityType)
                                                <input type="hidden" name="risk_based_requirement_validity_types[{{ $selectedRiskBasedRequirement['id'] }}][]" value="{{ $courseValidityType }}">
                                            @endforeach
                                            @foreach ($selectedRiskBasedRequirement['integrative_start_risk_levels'] as $integrativeStartRiskLevel)
                                                <input type="hidden" name="risk_based_requirement_integrative_start_levels[{{ $selectedRiskBasedRequirement['id'] }}][]" value="{{ $integrativeStartRiskLevel }}">
                                            @endforeach
                                        @endforeach

                                        <div class="grid gap-6 md:grid-cols-2">
                                            <div class="form-control flex flex-col gap-2">
                                                <label for="course_start_date" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Inizio corso') }}</span>
                                                </label>
                                                <input
                                                    id="course_start_date"
                                                    name="course_start_date"
                                                    type="date"
                                                    value="{{ $courseBaseValues['course_start_date'] }}"
                                                    class="input input-bordered w-full @error('course_start_date') input-error @enderror"
                                                >
                                                @error('course_start_date')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label for="course_end_date" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Fine corso') }}</span>
                                                </label>
                                                <input
                                                    id="course_end_date"
                                                    name="course_end_date"
                                                    type="date"
                                                    value="{{ $courseBaseValues['course_end_date'] }}"
                                                    class="input input-bordered w-full @error('course_end_date') input-error @enderror"
                                                >
                                                @error('course_end_date')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label for="access_closure_date" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Chiusura fruizione') }}</span>
                                                </label>
                                                <input
                                                    id="access_closure_date"
                                                    name="access_closure_date"
                                                    type="date"
                                                    value="{{ $courseBaseValues['access_closure_date'] }}"
                                                    class="input input-bordered w-full @error('access_closure_date') input-error @enderror"
                                                >
                                                @error('access_closure_date')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label for="expiry_date" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Data scadenza') }}</span>
                                                </label>
                                                <input
                                                    id="expiry_date"
                                                    name="expiry_date"
                                                    type="date"
                                                    value="{{ $courseBaseValues['expiry_date'] }}"
                                                    class="input input-bordered w-full @error('expiry_date') input-error @enderror"
                                                    required
                                                >
                                                @error('expiry_date')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label for="course_duration_hours" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Durata corso (ore)') }}</span>
                                                </label>
                                                <input
                                                    id="course_duration_hours"
                                                    name="course_duration_hours"
                                                    type="number"
                                                    min="0"
                                                    value="{{ $courseBaseValues['course_duration_hours'] }}"
                                                    class="input input-bordered w-full @error('course_duration_hours') input-error @enderror"
                                                >
                                                @error('course_duration_hours')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label for="interaction_duration_minutes" class="label p-0">
                                                    <span class="label-text font-medium">{{ __('Durata interattività (minuti)') }}</span>
                                                </label>
                                                <input
                                                    id="interaction_duration_minutes"
                                                    name="interaction_duration_minutes"
                                                    type="number"
                                                    min="0"
                                                    value="{{ $courseBaseValues['interaction_duration_minutes'] }}"
                                                    class="input input-bordered w-full @error('interaction_duration_minutes') input-error @enderror"
                                                >
                                                @error('interaction_duration_minutes')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="flex justify-end">
                                            <button type="submit" class="btn btn-primary">
                                                <span>{{ __('Salva dati') }}</span>
                                                <x-lucide-save class="h-4 w-4" />
                                            </button>
                                        </div>
                                </div>
                            </form>
                        @endif

                        @if ($activeCourseEditSection === 'survey')
                            <form method="POST" action="{{ $courseUpdateUrl }}" class="flex flex-col gap-6">
                                @include('admin.course.partials.course-edit-badge-bar')

                                <div class="card border border-base-300 bg-base-100 shadow-sm">
                                    <div class="card-body gap-6">
                                        <div>
                                            <h2 class="card-title">{{ __('Questionario di gradimento') }}</h2>
                                            <p class="text-sm text-base-content/70">
                                                {{ __('Se abilitato, viene aggiunto automaticamente come ultimo modulo del corso.') }}
                                            </p>
                                        </div>

                                        @csrf
                                        @method('PUT')

                                        <input type="hidden" name="title" value="{{ $courseBaseValues['title'] }}">
                                        <input type="hidden" name="description" value="{{ $courseBaseValues['description'] }}">
                                        <input type="hidden" name="year" value="{{ $courseBaseValues['year'] }}">
                                        <input type="hidden" name="expiry_date" value="{{ $courseBaseValues['expiry_date'] }}">
                                        <input type="hidden" name="status" value="{{ $courseBaseValues['status'] }}">
                                        @foreach ($selectedRiskBasedRequirementsPayload as $selectedRiskBasedRequirement)
                                            <input type="hidden" name="risk_based_requirement_ids[]" value="{{ $selectedRiskBasedRequirement['id'] }}">
                                            @foreach ($selectedRiskBasedRequirement['course_validity_types'] as $courseValidityType)
                                                <input type="hidden" name="risk_based_requirement_validity_types[{{ $selectedRiskBasedRequirement['id'] }}][]" value="{{ $courseValidityType }}">
                                            @endforeach
                                            @foreach ($selectedRiskBasedRequirement['integrative_start_risk_levels'] as $integrativeStartRiskLevel)
                                                <input type="hidden" name="risk_based_requirement_integrative_start_levels[{{ $selectedRiskBasedRequirement['id'] }}][]" value="{{ $integrativeStartRiskLevel }}">
                                            @endforeach
                                        @endforeach

                                        <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                            <div class="flex flex-col gap-4">
                                                <label class="label cursor-pointer justify-start gap-3 p-0">
                                                    <input
                                                        type="checkbox"
                                                        name="has_satisfaction_survey"
                                                        value="1"
                                                        class="checkbox"
                                                        @checked($courseBaseValues['has_satisfaction_survey'])
                                                        data-satisfaction-enabled
                                                    >
                                                    <span class="label-text">{{ __('Includi questionario di gradimento') }}</span>
                                                </label>
                                                @error('has_satisfaction_survey')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror

                                                <label class="label cursor-pointer justify-start gap-3 p-0">
                                                    <input
                                                        type="checkbox"
                                                        name="satisfaction_survey_required_for_certificate"
                                                        value="1"
                                                        class="checkbox"
                                                        @checked($courseBaseValues['satisfaction_survey_required_for_certificate'])
                                                        data-satisfaction-required
                                                    >
                                                    <span class="label-text">{{ __('Rendi il questionario obbligatorio per l\'ottenimento dell\'attestato') }}</span>
                                                </label>
                                                @error('satisfaction_survey_required_for_certificate')
                                                    <p class="text-sm text-error">{{ $message }}</p>
                                                @enderror

                                                <div class="text-sm text-base-content/70">
                                                    @role('superadmin')
                                                        @if ($activeSatisfactionSurveyTemplate)
                                                            <a href="{{ route('admin.satisfaction-survey.edit') }}" class="link link-primary">
                                                                {{ __('Configura domande e risposte globali del questionario') }}
                                                            </a>
                                                        @else
                                                            <a href="{{ route('admin.satisfaction-survey.edit') }}" class="link link-error">
                                                                {{ __('Configura prima il questionario globale di gradimento') }}
                                                            </a>
                                                        @endif
                                                    @else
                                                    @endrole
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex justify-end">
                                            <button type="submit" class="btn btn-primary">
                                                <span>{{ __('Salva dati') }}</span>
                                                <x-lucide-save class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @endif

                        @if ($activeCourseEditSection === 'certificates')
                            <form method="POST" action="{{ $courseUpdateUrl }}" class="flex flex-col gap-6">
                                @include('admin.course.partials.course-edit-badge-bar')

                                <div class="card border border-base-300 bg-base-100 shadow-sm">
                                    <div class="card-body gap-6">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h2 class="card-title">{{ __('Certificati ottenibili') }}</h2>
                                                <p class="text-sm text-base-content/70">
                                                    {{ __('Seleziona i certificati ottenibili con questo corso e definisci una o più tipologie di validità per ciascuno.') }}
                                                </p>
                                            </div>

                                            @if ($riskBasedRequirements->isNotEmpty())
                                                <button type="button" class="btn btn-primary btn-sm" data-open-risk-requirement-selection-modal>
                                                    <span>{{ __('Aggiungi') }}</span>
                                                    <x-lucide-plus class="h-4 w-4" />
                                                </button>
                                            @endif
                                        </div>

                                        @csrf
                                        @method('PUT')

                                        <input type="hidden" name="title" value="{{ $courseBaseValues['title'] }}">
                                        <input type="hidden" name="description" value="{{ $courseBaseValues['description'] }}">
                                        <input type="hidden" name="year" value="{{ $courseBaseValues['year'] }}">
                                        <input type="hidden" name="expiry_date" value="{{ $courseBaseValues['expiry_date'] }}">
                                        <input type="hidden" name="status" value="{{ $courseBaseValues['status'] }}">
                                        <input type="hidden" name="has_satisfaction_survey" value="{{ $courseBaseValues['has_satisfaction_survey'] ? '1' : '0' }}">
                                        <input type="hidden" name="satisfaction_survey_required_for_certificate" value="{{ $courseBaseValues['satisfaction_survey_required_for_certificate'] ? '1' : '0' }}">

                                        <div class="flex flex-col gap-4" data-course-risk-requirements>
                                            @if ($riskBasedRequirements->isEmpty())
                                                <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
                                                    {{ __('Non ci sono ancora abilitazioni configurate.') }}
                                                </div>
                                            @else
                                                <div class="grid gap-3" data-course-risk-requirements-list></div>

                                                <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70 hidden" data-course-risk-requirements-empty>
                                                    {{ __('Nessuna abilitazione associata al corso.') }}
                                                </div>

                                                <div data-course-risk-requirements-hidden-inputs></div>

                                                <script type="application/json" data-course-risk-requirements-all>
                                                    {!! json_encode($allRiskBasedRequirementsPayload->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
                                                </script>
                                                <script type="application/json" data-course-risk-requirements-selected>
                                                    {!! json_encode($selectedRiskBasedRequirementsPayload->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
                                                </script>

                                                <dialog class="modal" data-course-risk-requirement-selection-modal>
                                                        <div class="modal-box max-w-4xl">
                                                            <div class="flex items-start justify-between gap-4">
                                                                <div>
                                                                    <h3 class="text-lg font-semibold">{{ __('Seleziona requisito di rischio') }}</h3>
                                                                    <p class="text-sm text-base-content/70">
                                                                        {{ __('Scegli uno dei requisiti disponibili da associare al corso.') }}
                                                                    </p>
                                                                </div>
                                                                <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-risk-requirement-selection-modal>
                                                                    <x-lucide-x class="h-4 w-4" />
                                                                </button>
                                                            </div>

                                                            <div class="mt-6 overflow-x-auto rounded-box border border-base-300">
                                                                <table class="table w-full">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>{{ __('Nome') }}</th>
                                                                            <th>{{ __('Descrizione') }}</th>
                                                                            <th class="text-right">{{ __('Azioni') }}</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody data-course-risk-requirement-selection-tbody></tbody>
                                                                </table>
                                                            </div>

                                                            <div class="mt-4 rounded-box border border-dashed border-base-300 bg-base-100/70 p-4 text-sm text-base-content/70 hidden" data-course-risk-requirement-selection-empty>
                                                                {{ __('Tutti i requisiti disponibili sono già associati al corso.') }}
                                                            </div>

                                                            <div class="modal-action">
                                                                <button type="button" class="btn btn-ghost" data-close-risk-requirement-selection-modal>
                                                                    {{ __('Chiudi') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <button type="button" class="modal-backdrop" data-close-risk-requirement-selection-modal>
                                                            {{ __('Chiudi') }}
                                                        </button>
                                                </dialog>

                                                <dialog class="modal" data-course-risk-requirement-validity-modal>
                                                        <div class="modal-box max-w-lg">
                                                            <div class="space-y-2">
                                                                <h3 class="text-lg font-semibold" data-course-risk-requirement-validity-modal-title>{{ __('Imposta validità del corso') }}</h3>
                                                                <p class="text-sm text-base-content/70" data-course-risk-requirement-validity-modal-description></p>
                                                            </div>

                                                            <div class="mt-6 rounded-box border border-base-300 bg-base-200/40 p-4">
                                                                <div class="space-y-2">
                                                                    <div class="font-medium text-base-content">{{ __('Validità del corso') }}</div>
                                                                    <p class="text-sm text-base-content/70">
                                                                        {{ __('Puoi selezionare una o più tipologie di validità per lo stesso requisito.') }}
                                                                    </p>
                                                                </div>

                                                                <div class="mt-4 flex flex-col gap-3" data-course-risk-requirement-validity-options>
                                                                    @foreach ($courseRiskRequirementValidityTypeLabels as $value => $label)
                                                                        <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 bg-base-100 px-4 py-3">
                                                                            <input
                                                                                type="checkbox"
                                                                                value="{{ $value }}"
                                                                                class="checkbox"
                                                                                data-course-risk-requirement-validity-option
                                                                            >
                                                                            <span class="label-text font-medium">{{ $label }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>

                                                            <div class="mt-4 hidden rounded-box border border-base-300 bg-base-200/40 p-4" data-course-risk-requirement-integrative-fields>
                                                                <div class="space-y-2">
                                                                    <div class="font-medium text-base-content">{{ __('Livelli di partenza ammessi') }}</div>
                                                                    <p class="text-sm text-base-content/70">
                                                                        {{ __('Seleziona i livelli che l\'utente deve possedere per poter frequentare il corso integrativo.') }}
                                                                    </p>
                                                                </div>

                                                                <div class="mt-4 flex flex-col gap-2" data-course-risk-requirement-integrative-options>
                                                                    @foreach ($riskLevels as $riskLevel)
                                                                        <label class="label cursor-pointer justify-start gap-3">
                                                                            <input
                                                                                type="checkbox"
                                                                                value="{{ $riskLevel->value }}"
                                                                                class="checkbox"
                                                                                data-integrative-start-level-option
                                                                            >
                                                                            <span class="badge badge-sm {{ $riskLevel->badgeColor() }} min-h-7 px-2 text-[11px]">{{ $riskLevel->label() }}</span>
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </div>

                                                            <div class="modal-action">
                                                                <button type="button" class="btn btn-ghost" data-close-risk-requirement-validity-modal>
                                                                    {{ __('Annulla') }}
                                                                </button>
                                                                <button type="button" class="btn btn-primary" data-confirm-risk-requirement-validity>
                                                                    {{ __('Conferma') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <button type="button" class="modal-backdrop" data-close-risk-requirement-validity-modal>
                                                            {{ __('Chiudi') }}
                                                        </button>
                                                </dialog>

                                                <dialog class="modal" data-course-risk-requirement-delete-modal>
                                                        <div class="modal-box max-w-lg">
                                                            <div class="space-y-2">
                                                                <h3 class="text-lg font-semibold">{{ __('Rimuovi associazione') }}</h3>
                                                                <p class="text-sm text-base-content/70" data-course-risk-requirement-delete-modal-description></p>
                                                            </div>

                                                            <div class="modal-action">
                                                                <button type="button" class="btn btn-ghost" data-close-risk-requirement-delete-modal>
                                                                    {{ __('Annulla') }}
                                                                </button>
                                                                <button type="button" class="btn btn-accent" data-confirm-risk-requirement-delete>
                                                                    {{ __('Conferma eliminazione') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <button type="button" class="modal-backdrop" data-close-risk-requirement-delete-modal>
                                                            {{ __('Chiudi') }}
                                                        </button>
                                                </dialog>
                                            @endif

                                            @error('risk_based_requirement_ids')
                                                <p class="text-sm text-error">{{ $message }}</p>
                                            @enderror
                                            @error('risk_based_requirement_validity_types')
                                                <p class="text-sm text-error">{{ $message }}</p>
                                            @enderror
                                            @error('risk_based_requirement_integrative_start_levels')
                                                <p class="text-sm text-error">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="flex justify-end">
                                            <button type="submit" class="btn btn-primary">
                                                <span>{{ __('Salva dati') }}</span>
                                                <x-lucide-save class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @endif

                        @if ($activeCourseEditSection === 'modules')
            <div class="flex flex-col gap-6">
                @include('admin.course.partials.course-edit-badge-bar')

                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="card-title">{{ __('Moduli') }}</h2>
                                <p class="text-sm text-base-content/70">
                                    {{ __('Aggiungi un nuovo modulo scegliendo la tipologia da creare.') }}
                                </p>
                            </div>

                            <span
                                @class([
                                    'tooltip tooltip-left' => $course->status === 'published',
                                ])
                                @if ($course->status === 'published')
                                    data-tip="{{ __('Non puoi aggiungere nuovi moduli mentre il corso è pubblicato.') }}"
                                @endif
                            >
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    data-open-module-modal
                                    @disabled($course->status === 'published')
                                >
                                    <span>{{ __('New module') }}</span>
                                    <x-lucide-plus class="h-4 w-4" />
                                </button>
                            </span>
                        </div>

                    @if ($modules->isEmpty())
                        <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70">
                            {{ __('Nessun modulo presente per questo corso.') }}
                        </div>
                    @else
                        <div
                            class="grid gap-4"
                            data-modules-sortable-list
                            data-reorder-url="{{ route('admin.courses.modules.reorder', $course) }}"
                        >
                            @foreach ($modules as $module)
                                <div
                                    class="rounded-box border border-base-300 bg-base-100 p-4 transition-shadow"
                                    draggable="{{ $module->isSatisfactionQuiz() ? 'false' : 'true' }}"
                                    data-module-item
                                    data-module-id="{{ $module->id }}"
                                    data-module-type="{{ $module->type }}"
                                >
                                    <div class="flex gap-3">
                                        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-base-300 text-base-content/60 cursor-move">
                                            <x-lucide-move class="h-4 w-4" />
                                        </div>

                                        <div class="flex min-w-0 flex-1 flex-col gap-3">
                                            <div class="min-w-0">
                                                <p class="break-words text-sm font-semibold text-base-content">
                                                    {{ $module->title }}
                                                </p>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                                                <span class="badge badge-sm badge-outline gap-1.5 min-h-7 px-2.5 text-[11px] font-medium">
                                                    <x-dynamic-component
                                                        :component="$moduleTypeIcons[$module->type] ?? 'lucide-shapes'"
                                                        class="h-3 w-3"
                                                    />
                                                    <span>{{ $moduleTypeLabels[$module->type] ?? $module->type }}</span>
                                                </span>
                                                <span class="badge badge-sm min-h-7 px-2.5 text-[11px] font-medium">
                                                    {{ $moduleStatusLabels[$module->status] ?? $module->status }}
                                                </span>
                                                @php
                                                    $moduleValidator = app(\App\Services\ModuleValidation\ModuleValidatorService::class);
                                                    $moduleIsValid = $moduleValidator->validate($module);
                                                @endphp
                                                @if ($moduleIsValid)
                                                    <span class="badge badge-sm badge-success min-h-7 px-2.5 text-[11px] font-medium">{{ __('Valido') }}</span>
                                                @else
                                                    <span class="badge badge-sm badge-error min-h-7 px-2.5 text-[11px] font-medium">{{ __('Non valido') }}</span>
                                                @endif
                                            </div>

                                            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}" class="btn btn-secondary btn-sm w-full sm:w-auto whitespace-nowrap">
                                                    <x-lucide-pencil class="h-4 w-4" />
                                                    {{ __('Edit') }}
                                                </a>
                                                <button
                                                    type="button"
                                                    class="btn btn-accent btn-sm w-full sm:w-auto whitespace-nowrap"
                                                    data-open-delete-module-modal
                                                    data-modal-target="#delete-module-modal-{{ $module->id }}"
                                                >
                                                    <x-lucide-trash-2 class="h-4 w-4" />
                                                    {{ __('Delete') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <dialog id="delete-module-modal-{{ $module->id }}" class="modal">
                                    <div class="modal-box max-w-lg">
                                        <div class="space-y-2">
                                            <h3 class="text-lg font-semibold">{{ __('Delete module') }}</h3>
                                            <p class="text-sm text-base-content/70">
                                                {{ __('This action will move the module to the trash. Do you want to continue?') }}
                                            </p>
                                        </div>

                                        <div class="modal-action mt-6">
                                            <form method="dialog">
                                                <button type="submit" class="btn btn-ghost">
                                                    {{ __('Cancel') }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.courses.modules.destroy', [$course, $module]) }}">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="btn btn-accent" data-modal-submit-loading data-loading-text="{{ __('Eliminazione...') }}">
                                                    <span>{{ __('Confirm deletion') }}</span>
                                                    <x-lucide-trash-2 class="h-4 w-4" />
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <form method="dialog" class="modal-backdrop">
                                        <button type="submit">{{ __('Close') }}</button>
                                    </form>
                                </dialog>
                            @endforeach
                        </div>
                    @endif

                    <dialog id="create-module-modal" class="modal">
                        <div class="modal-box max-w-2xl">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold">{{ __('New module') }}</h3>
                                <p class="text-sm text-base-content/70">
                                    {{ __('Select a type, then confirm to create the module.') }}
                                </p>
                            </div>

                            <form method="POST" action="{{ route('admin.courses.modules.store', $course) }}" class="mt-6 space-y-6">
                                @csrf

                                <fieldset class="space-y-3">
                                    <legend class="text-sm font-medium text-base-content">
                                        {{ __('Module type') }}
                                    </legend>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($creatableModuleTypeLabels as $moduleType => $moduleTypeLabel)
                                    <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="type"
                                                    value="{{ $moduleType }}"
                                                    class="peer sr-only"
                                                    @checked(old('type') === $moduleType)
                                                >
                                                <span class="flex min-h-24 items-center rounded-box border border-base-300 bg-base-100 px-4 py-3 text-sm font-medium transition peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary hover:border-primary/40">
                                                    {{ $moduleTypeLabel }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    @error('type')
                                        <p class="text-sm text-error">{{ $message }}</p>
                                    @enderror
                                </fieldset>

                                <div id="module-title-field" class="form-control flex flex-col gap-2">
                                    <label for="module-title" class="label p-0">
                                        <span class="label-text font-medium">{{ __('Module title') }}</span>
                                    </label>
                                    <input
                                        id="module-title"
                                        name="title"
                                        type="text"
                                        value="{{ old('title') }}"
                                        class="input input-bordered w-full @error('title') input-error @enderror"
                                        required
                                    >
                                    @error('title')
                                        <p class="text-sm text-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="modal-action mt-0">
                                    <button
                                        type="button"
                                        class="btn btn-ghost"
                                        data-close-module-modal
                                    >
                                        {{ __('Cancel') }}
                                    </button>
                                    <button type="submit" class="btn btn-primary" data-modal-submit-loading data-loading-text="{{ __('Salvataggio...') }}">
                                        <span>{{ __('Confirm') }}</span>
                                        <x-lucide-check class="h-4 w-4" />
                                    </button>
                                </div>
                            </form>
                        </div>

                        <form method="dialog" class="modal-backdrop">
                            <button type="submit">{{ __('Close') }}</button>
                        </form>
                    </dialog>

                    </div>
                </div>
            </div>
                        @endif

                        @if ($activeCourseEditSection === 'teachers')
            <section class="flex flex-col gap-6">
                @include('admin.course.partials.course-edit-badge-bar')
                @include('admin.course.partials.teacher-assignments-card')
            </section>
                        @endif

                        @if ($activeCourseEditSection === 'tutors')
            <div class="flex flex-col gap-6">
                @include('admin.course.partials.course-edit-badge-bar')

                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <div>
                            <h2 class="card-title">{{ __('Tutor') }}</h2>
                            <p class="text-sm text-base-content/70">
                                {{ __('Sezione riservata alla futura gestione dei tutor del corso.') }}
                            </p>
                        </div>

                        <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-sm text-base-content/70">
                            {{ __('Funzione in sviluppo. Per ora non sono previste azioni in questa sezione.') }}
                        </div>
                    </div>
                </div>
            </div>
                        @endif

                        @if ($activeCourseEditSection === 'enrollments')
            <div class="flex flex-col gap-6">
                @include('admin.course.partials.course-edit-badge-bar')

                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div
                        class="card-body gap-6"
                        data-enrollments-table
                        data-enrollments-api-url="{{ route('admin.api.courses.enrollments.index', $course) }}"
                        data-enrollments-search-users-api-url="{{ route('admin.api.courses.enrollments.search-users', $course) }}"
                        data-enrollments-store-api-url="{{ route('admin.api.courses.enrollments.store', $course) }}"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="card-title">{{ __('Iscritti') }}</h2>
                                @if($course->status === 'draft')
                                    <p class="text-sm text-base-content/70">
                                        {{ __('Il corso è in stato bozza, non è possibile aggiungere iscritti finché non viene pubblicato.') }}
                                    </p>
                                @else
                                    <p class="text-sm text-base-content/70">
                                        {{ __('Gestisci gli iscritti al corso. Puoi aggiungere nuovi utenti o rimuovere quelli esistenti.') }}
                                    </p>
                                @endif
                            </div>

                            <button
                                type="button"
                                class="btn btn-primary"
                                data-open-iscritto-modal
                            >
                                <span>{{ __('Nuovo iscritto') }}</span>
                                <x-lucide-plus class="h-4 w-4" />
                            </button>
                        </div>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <label class="label cursor-pointer justify-start gap-3 p-0">
                            <input type="checkbox" class="checkbox" data-enrollments-show-trashed>
                            <span class="label-text">{{ __('Mostra eliminati') }}</span>
                        </label>

                        <div class="flex w-full max-w-xl items-center gap-2">
                            <label class="input input-bordered flex w-full items-center gap-2">
                                <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                <input
                                    type="search"
                                    class="grow"
                                    data-enrollments-search
                                    placeholder="{{ __('Cerca nome, cognome, CF, email') }}"
                                >
                            </label>
                            <button type="button" class="btn btn-primary" data-enrollments-search-button>
                                {{ __('Cerca') }}
                            </button>
                        </div>
                    </div>

                    <div class="relative" data-enrollments-table-container>
                        <div class="pointer-events-none absolute inset-0 z-10 hidden items-center justify-center bg-base-100/70" data-enrollments-loader>
                            <span class="loading loading-spinner loading-md"></span>
                        </div>

                        <div class="overflow-x-auto rounded-box border border-base-300">
                            <table class="table table-zebra w-full">
                                <thead>
                                    <tr>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-2" data-sort-key="surname">
                                                {{ __('Cognome') }}
                                                <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="surname" />
                                                <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="surname" />
                                                <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="surname" />
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-2" data-sort-key="name">
                                                {{ __('Nome') }}
                                                <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="name" />
                                                <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="name" />
                                                <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="name" />
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-2" data-sort-key="fiscal_code">
                                                {{ __('CF') }}
                                                <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="fiscal_code" />
                                                <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="fiscal_code" />
                                                <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="fiscal_code" />
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-2" data-sort-key="email">
                                                {{ __('Email') }}
                                                <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="email" />
                                                <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="email" />
                                                <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="email" />
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-2" data-sort-key="status">
                                                {{ __('Stato iscrizione') }}
                                                <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="status" />
                                                <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="status" />
                                                <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="status" />
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-2" data-sort-key="completion_percentage">
                                                {{ __('Completamento') }}
                                                <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="completion_percentage" />
                                                <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="completion_percentage" />
                                                <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="completion_percentage" />
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-2" data-sort-key="assigned_at">
                                                {{ __('Assegnato il') }}
                                                <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="assigned_at" />
                                                <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="assigned_at" />
                                                <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="assigned_at" />
                                            </button>
                                        </th>
                                        <th class="sticky right-0 z-20 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">{{ __('Azioni') }}</th>
                                    </tr>
                                </thead>
                                <tbody data-enrollments-tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70 hidden" data-enrollments-empty>
                        {{ __('Nessun iscritto presente per questo corso.') }}
                    </div>

                    <div class="flex flex-col gap-3 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
                        <p data-enrollments-summary></p>
                        <div class="join" data-enrollments-pagination></div>
                    </div>

                    <template data-enrollment-row-template>
                        <tr class="hover:bg-base-200">
                            <td data-cell="surname"></td>
                            <td data-cell="name"></td>
                            <td data-cell="fiscal_code"></td>
                            <td data-cell="email"></td>
                            <td>
                                <span class="badge badge-outline h-fit" data-cell="status"></span>
                            </td>
                            <td data-cell="completion_percentage"></td>
                            <td data-cell="assigned_at"></td>
                            <td class="sticky right-0 z-10 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">
                                <div class="flex flex-col gap-2 xl:flex-row">
                                    {{-- <a class="btn btn-xs btn-primary xl:btn-sm" data-action="edit">{{ __('Modifica') }}</a> --}}
                                    <button type="button" class="btn btn-xs btn-error xl:btn-sm" data-action="delete">{{ __('Elimina') }}</button>
                                    <button type="button" class="btn btn-xs btn-success xl:btn-sm" data-action="restore">{{ __('Ripristina') }}</button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <dialog id="create-enrollment-modal" class="modal" data-create-enrollment-modal>
                        <div class="modal-box max-w-3xl">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold">{{ __('Nuovo iscritto') }}</h3>
                                <p class="text-sm text-base-content/70">
                                    {{ __('Cerca un utente per nome, cognome, codice fiscale, email o ID e selezionalo per iscriverlo al corso.') }}
                                </p>
                            </div>

                            <div class="mt-6 flex items-center gap-2">
                                <label class="input input-bordered flex w-full items-center gap-2">
                                    <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                    <input
                                        type="search"
                                        class="grow"
                                        data-enrollment-user-search
                                        placeholder="{{ __('Cerca nome, cognome, CF, email o ID utente') }}"
                                    >
                                </label>
                                <button type="button" class="btn btn-primary" data-enrollment-user-search-button>
                                    {{ __('Cerca') }}
                                </button>
                            </div>

                            <div class="mt-4 overflow-x-auto rounded-box border border-base-300">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>{{ __('Cognome') }}</th>
                                            <th>{{ __('Nome') }}</th>
                                            <th>{{ __('CF') }}</th>
                                            <th>{{ __('Email') }}</th>
                                            <th>{{ __('Azioni') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody data-enrollment-user-results></tbody>
                                </table>
                            </div>

                            <div class="mt-4 rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70 hidden" data-enrollment-user-results-empty>
                                {{ __('Nessun utente trovato.') }}
                            </div>

                            <template data-enrollment-user-row-template>
                                <tr class="hover:bg-base-200">
                                    <td data-cell="id"></td>
                                    <td data-cell="surname"></td>
                                    <td data-cell="name"></td>
                                    <td data-cell="fiscal_code"></td>
                                    <td data-cell="email"></td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm" data-action="select-user">
                                            {{ __('Seleziona') }}
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <div class="modal-action mt-6">
                                <button type="button" class="btn btn-ghost" data-close-create-enrollment-modal>
                                    {{ __('Chiudi') }}
                                </button>
                            </div>
                        </div>

                        <form method="dialog" class="modal-backdrop">
                            <button type="submit">{{ __('Close') }}</button>
                        </form>
                    </dialog>

                    <dialog id="confirm-enrollment-modal" class="modal" data-confirm-enrollment-modal>
                        <div class="modal-box max-w-lg">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold">{{ __('Conferma iscrizione') }}</h3>
                                <p class="text-sm text-base-content/70" data-confirm-enrollment-message></p>
                            </div>

                            <div class="modal-action mt-6">
                                <form method="dialog">
                                    <button type="submit" class="btn btn-ghost">{{ __('Annulla') }}</button>
                                </form>
                                <button type="button" class="btn btn-primary" data-confirm-enrollment-submit data-loading-text="{{ __('Salvataggio...') }}">
                                    {{ __('Conferma') }}
                                </button>
                            </div>
                        </div>

                        <form method="dialog" class="modal-backdrop">
                            <button type="submit">{{ __('Close') }}</button>
                        </form>
                    </dialog>
                </div>
            </div>
                        @endif

                        @if ($activeCourseEditSection === 'operations')
            <div class="flex flex-col gap-6">
                @include('admin.course.partials.course-edit-badge-bar')

                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-6">
                        <div>
                            <h2 class="card-title">{{ __('Operazioni corso') }}</h2>
                            <p class="text-sm text-base-content/70">
                                {{ __('Azioni amministrative disponibili per questo corso.') }}
                            </p>
                        </div>

                        <div class="grid gap-3 sm:max-w-sm">
                            @can('duplicate courses')
                                <form method="POST" action="{{ route('admin.courses.duplicate', $course) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-outline w-full justify-start">
                                        <x-lucide-copy class="h-4 w-4" />
                                        <span>{{ __('Duplica corso') }}</span>
                                    </button>
                                </form>
                            @endcan

                            <button type="button" class="btn btn-accent btn-outline w-full justify-start" data-open-delete-course-modal>
                                <x-lucide-trash-2 class="h-4 w-4" />
                                <span>{{ __('Delete course') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
                        @endif

                    </div>

                    <dialog id="delete-course-modal" class="modal">
                        <div class="modal-box max-w-lg">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold">{{ __('Delete course') }}</h3>
                                <p class="text-sm text-base-content/70">
                                    {{ __('This action will move the course to the trash. Do you want to continue?') }}
                                </p>
                            </div>

                            <div class="modal-action mt-6">
                                <form method="dialog">
                                    <button type="submit" class="btn btn-ghost" data-close-delete-course-modal>
                                        {{ __('Cancel') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.courses.destroy', $course) }}">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-accent" data-modal-submit-loading data-loading-text="{{ __('Eliminazione...') }}">
                                        <span>{{ __('Confirm deletion') }}</span>
                                        <x-lucide-trash-2 class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </div>

                        <form method="dialog" class="modal-backdrop">
                            <button type="submit">{{ __('Close') }}</button>
                        </form>
                    </dialog>
                </div>
            </main>
        </div>
    </div>

    @vite('resources/js/pages/admin-course-edit.js')
</x-layouts.admin>
