<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCourseClassTeachersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->course();
        $courseClass = $this->courseClass();

        return $course instanceof Course
            && $course->supportsClasses()
            && $courseClass instanceof CourseClass
            && $courseClass->course_id === $course->getKey();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'teacher_ids' => ['required', 'array', 'min:1'],
            'teacher_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $courseClass = $this->courseClass();
                $teacherIds = $this->teacherIds();
                $activeAssignedTeacherIds = $courseClass->teacherAssignments()
                    ->whereIn('user_id', $teacherIds)
                    ->pluck('user_id')
                    ->map(fn (mixed $teacherId): int => (int) $teacherId);

                if ($activeAssignedTeacherIds->isNotEmpty()) {
                    $validator->errors()->add('teacher_ids', __('Uno o più docenti sono già assegnati a questa classe.'));
                }

                $teachers = User::query()->whereKey($teacherIds)->get();
                $invalidTeacherExists = $teachers->contains(
                    fn (User $user): bool => ! $user->hasAnyRole(['teacher', 'docente'])
                );

                if ($invalidTeacherExists) {
                    $validator->errors()->add('teacher_ids', __('Puoi assegnare alla classe solo utenti docente.'));
                }
            },
        ];
    }

    /**
     * @return array<int, int>
     */
    public function teacherIds(): array
    {
        return collect($this->input('teacher_ids', []))
            ->map(fn (mixed $teacherId): int => (int) $teacherId)
            ->values()
            ->all();
    }

    protected function course(): ?Course
    {
        return $this->route('course');
    }

    protected function courseClass(): ?CourseClass
    {
        return $this->route('courseClass');
    }
}
