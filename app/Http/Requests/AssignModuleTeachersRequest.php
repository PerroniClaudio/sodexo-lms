<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignModuleTeachersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_ids' => ['required', 'array', 'min:1'],
            'teacher_ids.*' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id'),
            ],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $teacherIds = collect($this->input('teacher_ids', []))
                    ->filter()
                    ->map(fn (mixed $teacherId): int => (int) $teacherId)
                    ->unique()
                    ->values();

                if ($teacherIds->isEmpty()) {
                    return;
                }

                $teachersCount = User::query()
                    ->whereIn('id', $teacherIds)
                    ->whereHas('roles', fn ($query) => $query->where('name', 'teacher'))
                    ->count();

                if ($teachersCount !== $teacherIds->count()) {
                    $validator->errors()->add('teacher_ids', __('Seleziona solo utenti con ruolo docente.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'teacher_ids' => __('Docenti'),
            'teacher_ids.*' => __('Docente'),
        ];
    }
}
