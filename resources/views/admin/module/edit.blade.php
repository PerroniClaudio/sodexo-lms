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
        data-has-teacher-assignment-errors="{{ $hasTeacherAssignmentErrors ? 'true' : 'false' }}"
        data-has-tutor-assignment-errors="{{ $hasTutorAssignmentErrors ? 'true' : 'false' }}"
        data-has-attendance-confirmation-errors="{{ $hasAttendanceConfirmationErrors ? 'true' : 'false' }}"
    >
        <x-page-header :title="__('Edit module')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-ghost">
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
                        @includeFirst([$moduleEditView, 'admin.module.types.video'])
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

        @if ($module->supportsStaffAssignments())
            @include('admin.module.partials.live-teachers-card')
            @include('admin.module.partials.live-tutors-card')
        @endif

        @if ($module->type === 'live')
            @include('admin.module.partials.live-attendance-card')
        @endif

        {{-- @if ($course->type === 'res' && $module->type === 'learning_quiz')
            @include('admin.module.partials.quiz-documents', ['course' => $course, 'module' => $module])
        @endif --}}

        @if ($module->type === 'learning_quiz')
            @include('admin.module.partials.quiz-questions', ['course' => $course, 'module' => $module])
            @if (in_array($module->permitted_submission, ['upload', 'all']))
                @include('admin.module.partials.quiz-documents', ['course' => $course, 'module' => $module])
            @endif
            @include('admin.module.partials.quiz-recent-submissions', ['course' => $course, 'module' => $module])
        @endif

        @if ($module->type === 'video')
            @include('admin.module.partials.video-table', ['course' => $course, 'module' => $module])
        @endif

        @include('admin.module.partials.module-enrollments-card')
    </div>

    @vite('resources/js/pages/admin-module-edit.js')
</x-layouts.admin>
