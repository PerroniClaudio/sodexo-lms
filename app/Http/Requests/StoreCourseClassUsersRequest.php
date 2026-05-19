<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCourseClassUsersRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
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
                $userIds = $this->userIds();
                $activeAssignedUserIds = $courseClass->userAssignments()
                    ->whereIn('user_id', $userIds)
                    ->pluck('user_id')
                    ->map(fn (mixed $userId): int => (int) $userId);

                if ($activeAssignedUserIds->isNotEmpty()) {
                    $validator->errors()->add('user_ids', __('Uno o più utenti sono già assegnati a questa classe.'));
                }

                $users = User::query()->whereKey($userIds)->get();
                $invalidUserExists = $users->contains(fn (User $user): bool => ! $user->hasRole('user'));

                if ($invalidUserExists) {
                    $validator->errors()->add('user_ids', __('Puoi assegnare alla classe solo utenti standard.'));
                }

                if (! $courseClass->hasUserCapacity(count($userIds))) {
                    $validator->errors()->add('user_ids', __('Una classe può contenere al massimo 30 utenti standard.'));
                }
            },
        ];
    }

    /**
     * @return array<int, int>
     */
    public function userIds(): array
    {
        return collect($this->input('user_ids', []))
            ->map(fn (mixed $userId): int => (int) $userId)
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
