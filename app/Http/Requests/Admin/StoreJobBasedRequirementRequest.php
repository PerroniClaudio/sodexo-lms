<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobBasedRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                foreach ($this->input('rules', []) as $groupIndex => $group) {
                    foreach ($group as $conditionIndex => $condition) {
                        if ($this->normalizeListValue($condition['value'] ?? null) === []) {
                            $validator->errors()->add(
                                "rules.{$groupIndex}.{$conditionIndex}.value",
                                __('Seleziona almeno un valore valido.'),
                            );
                        }
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $decodedRules = json_decode((string) $this->input('rules_json', '[]'), true);

        $this->merge([
            'rules' => is_array($decodedRules) ? $decodedRules : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*' => ['required', 'array', 'min:1'],
            'rules.*.*.field' => ['required', 'string', Rule::in(['job_role_id', 'job_task_id'])],
            'rules.*.*.operator' => ['required', 'string', Rule::in(['IN'])],
            'rules.*.*.value' => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * @return array{name: string, description: ?string, is_active: bool, rules: array<int, array<int, array{field: string, operator: string, value: array<int, int>}>>}
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'rules' => collect($validated['rules'])
                ->map(fn (array $group): array => collect($group)
                    ->map(fn (array $condition): array => [
                        'field' => $condition['field'],
                        'operator' => 'IN',
                        'value' => $this->normalizeListValue($condition['value'] ?? []),
                    ])
                    ->values()
                    ->all())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function normalizeListValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $item): int => (int) $item)
            ->filter(fn (int $item): bool => $item > 0)
            ->unique()
            ->values()
            ->all();
    }
}
