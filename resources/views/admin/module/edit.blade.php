<x-layouts.admin>
    @php
        $hasTeacherAssignmentErrors = $errors->has('teacher_ids') || $errors->has('teacher_ids.*');
        $hasTutorAssignmentErrors = $errors->has('tutor_ids') || $errors->has('tutor_ids.*');
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Edit module')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Back to course') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Course: :course. Type: :type.', ['course' => $course->title, 'type' => $moduleTypeLabels[$module->type] ?? $module->type]) }}
        </x-page-header>

        <div
            class="card border border-base-300 bg-base-100 shadow-sm"
            data-module-edit-page
            data-has-teacher-assignment-errors="{{ $hasTeacherAssignmentErrors ? 'true' : 'false' }}"
            data-has-tutor-assignment-errors="{{ $hasTutorAssignmentErrors ? 'true' : 'false' }}"
        >
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
            </div>
        </div>

        @if ($module->type === 'live')
            @include('admin.module.partials.live-teachers-card')
            @include('admin.module.partials.live-tutors-card')
        @endif
    </div>

    @vite('resources/js/pages/admin-module-edit.js')
</x-layouts.admin>
