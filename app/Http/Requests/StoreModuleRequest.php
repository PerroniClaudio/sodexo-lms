<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreModuleRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(Module::creatableTypes())],
            'title' => [
                Rule::requiredIf(fn () => Module::requiresManualTitle((string) $this->input('type'))),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $course = $this->route('course');
                $moduleType = (string) $this->input('type');

                if (! $course instanceof Course || $moduleType === '' || ! in_array($moduleType, Module::creatableTypes(), true)) {
                    return;
                }

                if (! $course->allowsModuleType($moduleType)) {
                    $validator->errors()->add(
                        'type',
                        $course->moduleTypeRestrictionMessage($moduleType)
                            ?? __('Il tipo di modulo selezionato non Ã¨ disponibile per questo corso.')
                    );
                }

                if (
                    $moduleType === Module::TYPE_SCORM
                    && $course->modules()->where('type', Module::TYPE_SCORM)->exists()
                ) {
                    $validator->errors()->add('type', __('Il corso può contenere un solo modulo SCORM.'));
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
            'type' => __('modules.fields.type'),
            'title' => __('modules.fields.title'),
        ];
    }
}
