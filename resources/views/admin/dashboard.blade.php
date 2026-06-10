<x-layouts.admin>
    <section class="flex min-h-full w-full flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-admin.dashboard.metric-stat
                icon="users"
                :label="__('Utenti attivi')"
                :value="$overview['active_learners_count']"
                value-class="text-primary"
            />
            <x-admin.dashboard.metric-stat
                icon="book-open"
                :label="__('Corsi pubblicati')"
                :value="$overview['published_courses_count']"
                value-class="text-secondary"
            />
            <x-admin.dashboard.metric-stat
                icon="badge-check"
                :label="__('Completamenti 30 giorni')"
                :value="$overview['completions_last_30_days']"
                value-class="text-success"
            />
            <x-admin.dashboard.metric-stat
                icon="gauge"
                :label="__('Avanzamento medio')"
                :value="sprintf('%s%%', $overview['course_completion_average'])"
            />
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <x-admin.dashboard.overview-card :overview="$overview" />
            </div>

            <x-admin.dashboard.certificates-card :certificate-summary="$certificateSummary" />
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <x-admin.event-calendar />
            </div>

            <x-admin.dashboard.activity-summary-card
                :recent-residential-without-documents="$recentResidentialWithoutDocuments"
                :compliance="$compliance"
                :follow-up-users="$followUpUsers"
            />
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <x-admin.dashboard.follow-up-users-card
                    :follow-up-users="$followUpUsers"
                    :follow-up-inactive-days="$followUpInactiveDays"
                />
            </div>

            <x-admin.dashboard.compliance-card :compliance="$compliance" />
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <x-admin.dashboard.evaluation-card :evaluation="$evaluation" />
            <x-admin.dashboard.recent-residential-card :recent-residential-without-documents="$recentResidentialWithoutDocuments" />
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="xl:col-span-2">
                <x-admin.dashboard.survey-summary-card :survey-summary="$surveySummary" />
            </div>
        </div>
    </section>

    <x-admin.dashboard.survey-distribution-modal />
</x-layouts.admin>
