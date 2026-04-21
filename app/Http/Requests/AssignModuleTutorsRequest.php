<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignModuleTutorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tutor_ids' => ['required', 'array', 'min:1'],
            'tutor_ids.*' => [
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
                $tutorIds = collect($this->input('tutor_ids', []))
                    ->filter()
                    ->map(fn (mixed $tutorId): int => (int) $tutorId)
                    ->unique()
                    ->values();

                if ($tutorIds->isEmpty()) {
                    return;
                }

                $tutorsCount = User::query()
                    ->whereIn('id', $tutorIds)
                    ->whereHas('roles', fn ($query) => $query->where('name', 'tutor'))
                    ->count();

                if ($tutorsCount !== $tutorIds->count()) {
                    $validator->errors()->add('tutor_ids', __('Seleziona solo utenti con ruolo tutor.'));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'tutor_ids' => __('Tutor'),
            'tutor_ids.*' => __('Tutor'),
        ];
    }
}
