<x-layouts.admin>
    @php
        $hasTeacherAssignmentErrors = $errors->has('teacher_ids') || $errors->has('teacher_ids.*');
        $hasTutorAssignmentErrors = $errors->has('tutor_ids') || $errors->has('tutor_ids.*');
        $hasAttendanceConfirmationErrors = $errors->has('effective_start_time')
            || $errors->has('effective_end_time')
            || $errors->has('minimum_attendance_percentage');
    @endphp

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-module-edit-page
        data-course-edit-page
        data-has-teacher-assignment-errors="{{ $hasTeacherAssignmentErrors ? 'true' : 'false' }}"
        data-has-tutor-assignment-errors="{{ $hasTutorAssignmentErrors ? 'true' : 'false' }}"
        data-has-attendance-confirmation-errors="{{ $hasAttendanceConfirmationErrors ? 'true' : 'false' }}"
    >
        <x-page-header :title="__('Edit module')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.edit', $course) }}?section=modules" class="btn btn-ghost">
                        <x-lucide-arrow-left class="h-4 w-4" />
                        <span>{{ __('Back to course') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Course: :course. Type: :type.', ['course' => $course->title, 'type' => $moduleTypeLabels[$module->type] ?? $module->type]) }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.courses.modules.update', [$course, $module]) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-6">
                        <x-dynamic-component :component="$moduleEditView" :data="get_defined_vars()" />
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Save module') }}</span>
                            <x-lucide-save class="h-4 w-4" />
                        </button>
                    </div>

                </form>
                @stack('after-form')
            </div>
        </div>

        @if ($appointmentControlledByClasses)
            <x-admin.module.classes-card :data="get_defined_vars()" />
        @endif

        @if ($module->type === 'live')
            <x-admin.module.live-attendance-card :data="get_defined_vars()" />
        @endif

        {{-- @if ($course->type === 'res' && $module->type === 'learning_quiz')
            <x-admin.module.quiz-documents :data="array_merge(get_defined_vars(), ['course' => $course, 'module' => $module])" />
        @endif --}}

        @if ($module->type === 'learning_quiz')
            <x-admin.module.quiz-questions :data="array_merge(get_defined_vars(), ['course' => $course, 'module' => $module])" />
            @if (in_array($module->permitted_submission, ['upload', 'all']))
                <x-admin.module.quiz-documents :data="array_merge(get_defined_vars(), ['course' => $course, 'module' => $module])" />
            @endif
            <x-admin.module.quiz-recent-submissions :data="array_merge(get_defined_vars(), ['course' => $course, 'module' => $module])" />
        @endif

        @if ($module->type === 'video')
            <x-admin.module.teaching-materials :data="array_merge(get_defined_vars(), ['course' => $course, 'module' => $module])" />
            <x-admin.module.video-exercises :data="array_merge(get_defined_vars(), ['course' => $course, 'module' => $module])" />
            <x-admin.module.video-table :data="array_merge(get_defined_vars(), ['course' => $course, 'module' => $module])" />
        @endif

        <x-admin.module.enrollments-card :data="get_defined_vars()" />
    </div>

    @vite(['resources/js/pages/admin-module-edit.js', 'resources/js/pages/admin-course-edit.js'])
</x-layouts.admin>
