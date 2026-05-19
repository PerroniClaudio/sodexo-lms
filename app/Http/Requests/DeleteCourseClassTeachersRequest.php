<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassTeacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeleteCourseClassTeachersRequest extends FormRequest
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
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'distinct', 'exists:course_class_teachers,id'],
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
                $assignmentIds = $this->assignmentIds();
                $matchingAssignmentsCount = CourseClassTeacher::query()
                    ->where('course_class_id', $courseClass->getKey())
                    ->whereKey($assignmentIds)
                    ->count();

                if ($matchingAssignmentsCount !== count($assignmentIds)) {
                    $validator->errors()->add('assignment_ids', __('Una o più assegnazioni docente non appartengono a questa classe.'));
                }
            },
        ];
    }

    /**
     * @return array<int, int>
     */
    public function assignmentIds(): array
    {
        return collect($this->input('assignment_ids', []))
            ->map(fn (mixed $assignmentId): int => (int) $assignmentId)
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
