<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorizzazione base, personalizzare se necessario
        return true;
    }

    public function rules(): array
    {
        $routeUser = $this->route('user');
        $allowedRoles = $this->allowedRoles($routeUser instanceof User ? $routeUser : null);
        $isWorker = in_array('user', $this->input('roles', []), true);

        $rules = [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'string',
                Rule::in($allowedRoles),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($routeUser?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'fiscal_code' => [
                'required',
                'string',
                'size:16',
                Rule::unique('users', 'fiscal_code')->ignore($routeUser?->getKey()),
            ],
            // Campi user-only, validazione condizionale
            'is_foreigner_or_immigrant' => [$isWorker ? 'required' : 'nullable', 'boolean'],
            'declared_language_level_id' => ['nullable', 'integer', 'exists:language_levels,id'],
            'verified_language_level_id' => ['nullable', 'integer', 'exists:language_levels,id'],
            'employment_start_date' => [$isWorker ? 'required' : 'nullable', 'date'],
            'employment_end_date' => ['nullable', 'date', 'after_or_equal:employment_start_date'],
            'job_role_id' => [$isWorker ? 'required' : 'nullable', 'exists:job_roles,id'],
            'job_sector_id' => [$isWorker ? 'required' : 'nullable', 'exists:job_sectors,id'],
            'job_unit_id' => [$isWorker ? 'required' : 'nullable', 'exists:job_units,id'],
            'company_division_id' => [$isWorker ? 'nullable' : 'nullable', 'exists:company_divisions,id'],
            'job_tasks' => [$isWorker ? 'required' : 'nullable', 'array', 'min:1'],
            'job_tasks.*.job_task_id' => ['required_with:job_tasks', 'exists:job_tasks,id'],
            'job_tasks.*.starts_at' => ['required_with:job_tasks', 'date'],
            'job_tasks.*.ends_at' => ['nullable', 'date'],
            // Campi opzionali ma validi se presenti
            'job_category_id' => ['nullable', 'exists:job_categories,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
            // Campi facoltativi
            'password' => ['nullable', 'string', 'min:8'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:1'],
            'phone_prefix' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:100'], // Verrà convertito nell'id corrispondente
            'region' => ['nullable', 'string', 'max:100'], // Verrà convertito nell'id corrispondente
            'province' => ['nullable', 'string', 'max:100'], // Verrà convertito nell'id corrispondente
            'city' => ['nullable', 'string', 'max:100'], // Verrà convertito nell'id corrispondente
            // 'home_region_id' => ['nullable', 'integer', 'exists:world_divisions,id'],
            // 'home_province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            // 'home_city_id' => ['nullable', 'integer', 'exists:world_cities,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
        ];

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->filled('email')
            ? strtolower(trim((string) $this->input('email')))
            : null;

        $this->merge([
            'email' => $email,
            'fiscal_code' => strtoupper(trim((string) $this->input('fiscal_code'))),
            'roles' => array_values(array_unique((array) $this->input('roles', []))),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! in_array('user', $this->input('roles', []), true)) {
                    return;
                }

                $assignments = collect($this->input('job_tasks', []))
                    ->filter(fn (mixed $assignment): bool => is_array($assignment))
                    ->values();

                if ($assignments->isEmpty()) {
                    return;
                }

                $duplicateDefinitions = $assignments
                    ->groupBy(fn (array $assignment): string => implode('|', [
                        (string) ($assignment['job_task_id'] ?? ''),
                        (string) ($assignment['starts_at'] ?? ''),
                        (string) ($assignment['ends_at'] ?? ''),
                    ]))
                    ->contains(fn ($group): bool => $group->count() > 1);

                if ($duplicateDefinitions) {
                    $validator->errors()->add('job_tasks', __('Non puoi inserire due volte la stessa mansione con le stesse date.'));
                }
            },
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedRoles(?User $routeUser): array
    {
        $authenticatedUser = $this->user();

        if (! $authenticatedUser?->hasRole('superadmin')) {
            return $routeUser?->getRoleNames()->all() ?: ['user'];
        }

        $roles = ['user', 'admin', 'teacher', 'docente', 'tutor'];

        if ($routeUser?->hasRole('superadmin')) {
            $roles[] = 'superadmin';
        }

        return array_values(array_unique($roles));
    }
}
