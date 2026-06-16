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
            ['key' => 'details', 'label' => __('Dati anagrafici corso'), 'icon' => 'lucide-book-open-text', 'group' => 'course'],
            ...in_array($course->type, ['res', 'blended'], true)
                ? [['key' => 'venue', 'label' => __('Sede'), 'icon' => 'lucide-map-pin', 'group' => 'course']]
                : [],
            ['key' => 'attachments', 'label' => __('Allegati'), 'icon' => 'lucide-paperclip', 'group' => 'course'],
            ['key' => 'documents', 'label' => __('Documenti'), 'icon' => 'lucide-file-up', 'group' => 'course'],
            ['key' => 'duration', 'label' => __('Durata corso'), 'icon' => 'lucide-clock-3', 'group' => 'planning'],
            ['key' => 'program', 'label' => __('Programma corso'), 'icon' => 'lucide-calendar-clock', 'group' => 'planning'],
            ['key' => 'survey', 'label' => __('Gradimento'), 'icon' => 'lucide-message-square-heart', 'group' => 'planning'],
            ['key' => 'certificates', 'label' => __('Abilitazioni di rischio'), 'icon' => 'lucide-file-badge', 'group' => 'certificates'],
            ['key' => 'certificate-templates', 'label' => __('Template attestati'), 'icon' => 'lucide-file-text', 'group' => 'certificates'],
            ['key' => 'categorization', 'label' => __('Categorizzazione'), 'icon' => 'lucide-tags', 'group' => 'audience'],
            ['key' => 'partners', 'label' => __('Partner'), 'icon' => 'lucide-handshake', 'group' => 'audience'],
            ['key' => 'recipients', 'label' => __('Destinatari'), 'icon' => 'lucide-users', 'group' => 'audience'],
            ['key' => 'modules', 'label' => __('Moduli'), 'icon' => 'lucide-blocks', 'group' => 'delivery'],
            ['key' => 'teachers', 'label' => __('Docenti'), 'icon' => 'lucide-graduation-cap', 'group' => 'delivery'],
            ['key' => 'tutors', 'label' => __('Tutor'), 'icon' => 'lucide-users-round', 'group' => 'delivery'],
            ['key' => 'faculty', 'label' => __('Faculty'), 'icon' => 'lucide-id-card', 'group' => 'delivery'],
            ['key' => 'enrollments', 'label' => __('Iscritti'), 'icon' => 'lucide-user-plus', 'group' => 'delivery'],
            ['key' => 'operations', 'label' => __('Operazioni corso'), 'icon' => 'lucide-wrench', 'group' => 'operations'],
        ]);
        $courseEditGroupLabels = [
            'course' => __('Corso'),
            'planning' => __('Pianificazione'),
            'certificates' => __('Attestati'),
            'audience' => __('Destinatari e partner'),
            'delivery' => __('Erogazione'),
            'operations' => __('Operazioni'),
        ];
        $courseEditGroupIcons = [
            'course' => 'lucide-book-open',
            'planning' => 'lucide-calendar-days',
            'certificates' => 'lucide-award',
            'audience' => 'lucide-users',
            'delivery' => 'lucide-presentation',
            'operations' => 'lucide-settings',
        ];
        $courseEditSectionGroups = $courseEditSections->groupBy('group');
        $activeCourseEditSection = request('section', 'details');

        if (! $courseEditSections->contains(fn (array $section): bool => $section['key'] === $activeCourseEditSection)) {
            $activeCourseEditSection = 'details';
        }

        $activeCourseEditSectionConfig = $courseEditSections->firstWhere('key', $activeCourseEditSection);

        $courseDetailsUpdateUrl = route('admin.courses.details.update', $course);
        $courseDurationUpdateUrl = route('admin.courses.duration.update', $course);
        $courseProgramUpdateUrl = route('admin.courses.program.update', $course);
        $courseAttachmentsUpdateUrl = route('admin.courses.attachments.update', $course);
        $courseDocumentsStoreUrl = route('admin.courses.documents.store', $course);
        $courseSurveyUpdateUrl = route('admin.courses.survey.update', $course);
        $courseCertificatesUpdateUrl = route('admin.courses.certificates.update', $course);
        $courseCertificateTemplatesUpdateUrl = route('admin.courses.certificate-templates.update', $course);
        $courseCategoriesUpdateUrl = route('admin.courses.categories.update', $course);
        $coursePartnersUpdateUrl = route('admin.courses.partners.update', $course);
        $courseRecipientsUpdateUrl = route('admin.courses.recipients.update', $course);
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
            'code' => old('code', $course->code),
            'description' => old('description', $course->description),
            'teaching_material' => old('teaching_material', $course->teaching_material),
            'max_participants' => old('max_participants', $course->max_participants),
            'participant_presence_verification' => old(
                'participant_presence_verification',
                $course->participant_presence_verification
            ),
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
            'is_financed' => (bool) old('is_financed', $course->is_financed),
            'funding_entity_id' => old('funding_entity_id', $course->funding_entity_id),
            'job_unit_id' => old('job_unit_id', $course->job_unit_id),
            'venue_id' => old('venue_id', $course->venue_id),
            'has_satisfaction_survey' => (bool) old('has_satisfaction_survey', $course->has_satisfaction_survey),
            'satisfaction_survey_required_for_certificate' => (bool) old(
                'satisfaction_survey_required_for_certificate',
                $course->satisfaction_survey_required_for_certificate
            ),
        ];
        $courseProgramSchedule = old('program_schedule', $course->program_schedule ?? []);
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
        class="min-h-screen max-w-full overflow-x-hidden bg-base-100"
        data-course-edit-page
        data-has-create-module-errors="{{ $errors->has('type') || $errors->has('title') ? 'true' : 'false' }}"
        data-course-is-published="{{ $course->status === 'published' ? 'true' : 'false' }}"
    >
        <div class="grid min-h-screen w-full min-w-0 grid-cols-1 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="min-w-0 border-b border-base-300 bg-base-200 p-4 lg:min-h-screen lg:border-b-0 lg:border-r">
                <div class="lg:sticky lg:top-4">
                    <details class="collapse collapse-arrow border border-base-300 bg-base-100 shadow-sm lg:hidden">
                        <summary class="collapse-title flex min-h-0 items-center gap-3 px-4 py-3 text-base font-medium">
                            <x-dynamic-component :component="$activeCourseEditSectionConfig['icon']" class="h-5 w-5 shrink-0" />
                            <span class="min-w-0 truncate">{{ $activeCourseEditSectionConfig['label'] }}</span>
                        </summary>
                        <div class="collapse-content px-2 pb-2">
                            <ul class="menu w-full gap-1">
                                @foreach ($courseEditSectionGroups as $group => $sections)
                                    <li>
                                        <details open>
                                            <summary>
                                                <x-dynamic-component :component="$courseEditGroupIcons[$group]" class="mr-2 inline-block h-5 w-5" />
                                                {{ $courseEditGroupLabels[$group] }}
                                            </summary>
                                            <ul>
                                                @foreach ($sections as $section)
                                                    <li>
                                                        <a
                                                            href="{{ route('admin.courses.edit', $course).'?section='.$section['key'] }}"
                                                            @class([
                                                                'menu-active' => $activeCourseEditSection === $section['key'],
                                                            ])
                                                        >
                                                            <x-dynamic-component :component="$section['icon']" class="mr-2 inline-block h-5 w-5" />
                                                            {{ $section['label'] }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </details>

                    <div class="hidden lg:block">
                        <ul class="menu w-full gap-1">
                            @foreach ($courseEditSectionGroups as $group => $sections)
                                <li>
                                    <details open>
                                        <summary>
                                            <x-dynamic-component :component="$courseEditGroupIcons[$group]" class="mr-2 inline-block h-5 w-5" />
                                            {{ $courseEditGroupLabels[$group] }}
                                        </summary>
                                        <ul>
                                            @foreach ($sections as $section)
                                                <li>
                                                    <a
                                                        href="{{ route('admin.courses.edit', $course).'?section='.$section['key'] }}"
                                                        @class([
                                                            'menu-active' => $activeCourseEditSection === $section['key'],
                                                        ])
                                                    >
                                                        <x-dynamic-component :component="$section['icon']" class="mr-2 inline-block h-5 w-5" />
                                                        {{ $section['label'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 overflow-hidden">
                <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                    <x-page-header :title="__('Modifica corso')" />

                    <div class="flex flex-col gap-6">
                        @if ($activeCourseEditSection === 'details')
                            <x-admin.course.edit.sections.details
                                :course="$course"
                                :course-base-values="$courseBaseValues"
                                :course-detail-accordion-fields="$courseDetailAccordionFields"
                                :course-participant-presence-verification-labels="\App\Models\Course::availableParticipantPresenceVerificationLabels()"
                                :course-status-labels="$courseStatusLabels"
                                :course-validator="$courseValidator"
                                :funding-entities="$fundingEntities"
                                :update-url="$courseDetailsUpdateUrl"
                            />
                        @elseif ($activeCourseEditSection === 'duration')
                            <x-admin.course.edit.sections.duration
                                :course="$course"
                                :course-base-values="$courseBaseValues"
                                :course-validator="$courseValidator"
                                :update-url="$courseDurationUpdateUrl"
                            />
                        @elseif ($activeCourseEditSection === 'program')
                            <x-admin.course.edit.sections.program
                                :course="$course"
                                :course-program-schedule="$courseProgramSchedule"
                                :course-program-teaching-method-labels="$courseProgramTeachingMethodLabels"
                                :course-validator="$courseValidator"
                                :update-url="$courseProgramUpdateUrl"
                            />
                        @elseif ($activeCourseEditSection === 'attachments')
                            <x-admin.course.edit.sections.attachments
                                :course="$course"
                                :course-validator="$courseValidator"
                                :update-url="$courseAttachmentsUpdateUrl"
                            />
                        @elseif ($activeCourseEditSection === 'documents')
                            <x-admin.course.edit.sections.documents
                                :category-labels="$courseDocumentCategoryLabels"
                                :course="$course"
                                :course-validator="$courseValidator"
                                :file-type-labels="$courseDocumentFileTypeLabels"
                                :store-url="$courseDocumentsStoreUrl"
                            />
                        @elseif ($activeCourseEditSection === 'venue' && in_array($course->type, ['res', 'blended'], true))
                            <x-admin.course.edit.sections.venue
                                :course="$course"
                                :course-base-values="$courseBaseValues"
                                :course-validator="$courseValidator"
                                :job-units="$jobUnits"
                                :update-url="$courseDetailsUpdateUrl"
                                :venues="$venues"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'survey')
                            <x-admin.course.edit.sections.survey
                                :active-satisfaction-survey-template="$activeSatisfactionSurveyTemplate"
                                :course="$course"
                                :course-base-values="$courseBaseValues"
                                :course-type-labels="$courseTypeLabels"
                                :course-validator="$courseValidator"
                                :update-url="$courseSurveyUpdateUrl"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'certificates')
                            <x-admin.course.edit.sections.certificates
                                :all-risk-based-requirements-payload="$allRiskBasedRequirementsPayload"
                                :course="$course"
                                :course-risk-requirement-validity-type-labels="$courseRiskRequirementValidityTypeLabels"
                                :course-validator="$courseValidator"
                                :risk-based-requirements="$riskBasedRequirements"
                                :risk-levels="$riskLevels"
                                :selected-risk-based-requirements-payload="$selectedRiskBasedRequirementsPayload"
                                :update-url="$courseCertificatesUpdateUrl"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'certificate-templates')
                            <x-admin.course.edit.sections.certificate-templates
                                :course="$course"
                                :course-certificate-templates="$courseCertificateTemplates"
                                :course-validator="$courseValidator"
                                :custom-certificate-type-labels="$customCertificateTypeLabels"
                                :update-url="$courseCertificateTemplatesUpdateUrl"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'categorization')
                            <x-admin.course.edit.sections.categorization
                                :course="$course"
                                :course-categories="$courseCategories"
                                :course-event-type-labels="$courseEventTypeLabels"
                                :course-type-labels="$courseTypeLabels"
                                :course-validator="$courseValidator"
                                :update-url="$courseCategoriesUpdateUrl"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'partners')
                            <x-admin.course.edit.sections.partners
                                :course="$course"
                                :partners="$partners"
                                :update-url="$coursePartnersUpdateUrl"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'recipients')
                            <x-admin.course.edit.sections.recipients
                                :course="$course"
                                :course-validator="$courseValidator"
                                :job-roles="$jobRoles"
                                :job-tasks="$jobTasks"
                                :job-units="$jobUnits"
                                :update-url="$courseRecipientsUpdateUrl"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'modules')
                            <x-admin.course.edit.sections.modules
                                :course="$course"
                                :course-validator="$courseValidator"
                                :creatable-module-type-labels="$creatableModuleTypeLabels"
                                :module-status-labels="$moduleStatusLabels"
                                :module-type-icons="$moduleTypeIcons"
                                :module-type-labels="$moduleTypeLabels"
                                :modules="$modules"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'teachers')
                            <x-admin.course.edit.sections.teachers
                                :course="$course"
                                :course-validator="$courseValidator"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'tutors')
                            <x-admin.course.edit.sections.tutors
                                :course="$course"
                                :course-validator="$courseValidator"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'enrollments')
                            <x-admin.course.edit.sections.enrollments
                                :course="$course"
                                :course-validator="$courseValidator"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'faculty')
                            <x-admin.course.edit.sections.faculty
                                :course="$course"
                                :course-validator="$courseValidator"
                                :role-labels="\App\Models\CourseFacultyMember::roleLabels()"
                            />
                        @endif

                        @if ($activeCourseEditSection === 'operations')
                            <x-admin.course.edit.sections.operations :course="$course" :course-validator="$courseValidator" />
                        @endif

                    </div>
                </div>
            </main>
        </div>
    </div>

    @vite('resources/js/pages/admin-course-edit.js')
</x-layouts.admin>
