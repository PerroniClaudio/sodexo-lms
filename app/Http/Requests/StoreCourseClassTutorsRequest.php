<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCourseClassTutorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->course();
        $courseClass = $this->courseClass();

        return $course instanceof Course
            && $course->supportsClasses()
            && $courseClass instanceof CourseClass
            && (int) $courseClass->module?->belongsTo === (int) $course->getKey();
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tutor_ids' => ['required', 'array', 'min:1'],
            'tutor_ids.*' => ['integer', 'distinct', 'exists:users,id'],
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
                $tutorIds = $this->tutorIds();
                $activeAssignedTutorIds = $courseClass->tutorAssignments()
                    ->whereIn('user_id', $tutorIds)
                    ->pluck('user_id')
                    ->map(fn (mixed $tutorId): int => (int) $tutorId);

                if ($activeAssignedTutorIds->isNotEmpty()) {
                    $validator->errors()->add('tutor_ids', __('Uno o più tutor sono già assegnati a questa classe.'));
                }

                $tutors = User::query()->whereKey($tutorIds)->get();
                $invalidTutorExists = $tutors->contains(
                    fn (User $user): bool => ! $user->hasRole('tutor')
                );

                if ($invalidTutorExists) {
                    $validator->errors()->add('tutor_ids', __('Puoi assegnare alla classe solo utenti tutor.'));
                }

                $assignableTutorIds = $this->course()?->getTutorsQuery()
                    ->whereKey($tutorIds)
                    ->pluck('users.id')
                    ->map(fn (mixed $tutorId): int => (int) $tutorId)
                    ->all();

                if (count($assignableTutorIds) !== count($tutorIds)) {
                    $validator->errors()->add('tutor_ids', __('Puoi assegnare alla classe solo tutor già assegnati al corso.'));
                }
            },
        ];
    }

    /**
     * @return array<int, int>
     */
    public function tutorIds(): array
    {
        return collect($this->input('tutor_ids', []))
            ->map(fn (mixed $tutorId): int => (int) $tutorId)
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
