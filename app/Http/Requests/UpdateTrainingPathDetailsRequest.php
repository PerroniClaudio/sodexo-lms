<?php

namespace App\Http\Requests;

use App\Models\TrainingPath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainingPathDetailsRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('training_paths', 'code')->ignore($this->route('trainingPath'))],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(TrainingPath::availableStatuses())],
            'enforce_course_order' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enforce_course_order' => $this->boolean('enforce_course_order'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Titolo del percorso formativo'),
            'code' => __('Codice percorso formativo'),
            'description' => __('Descrizione'),
            'status' => __('Stato'),
            'enforce_course_order' => __('Segui ordine corsi'),
        ];
    }
}
