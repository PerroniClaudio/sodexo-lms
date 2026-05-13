<?php

namespace App\Http\Requests;

use App\Models\Module;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderCourseModulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'modules' => ['required', 'array', 'list'],
            'modules.*' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists(Module::class, 'id')->where(fn ($query) => $query->where('belongsTo', (string) $this->route('course')?->getKey())),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $course = $this->route('course');

                if ($course === null) {
                    return;
                }

                $submittedModuleIds = collect($this->input('modules', []))
                    ->map(fn (mixed $moduleId): string => (string) $moduleId)
                    ->values();

                $expectedModuleIds = $course->modules()
                    ->pluck('id')
                    ->map(fn (mixed $moduleId): string => (string) $moduleId)
                    ->values();

                if (
                    $submittedModuleIds->count() !== $expectedModuleIds->count()
                    || $submittedModuleIds->diff($expectedModuleIds)->isNotEmpty()
                    || $expectedModuleIds->diff($submittedModuleIds)->isNotEmpty()
                ) {
                    $validator->errors()->add('modules', __('The module order is invalid.'));
                }

            },
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'modules' => __('Modules'),
        ];
    }
}
