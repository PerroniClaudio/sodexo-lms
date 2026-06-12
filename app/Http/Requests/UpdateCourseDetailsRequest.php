<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseDetailsRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('courses', 'code')->ignore($this->route('course'))],
            'description' => ['required', 'string'],
            'teaching_material' => ['nullable', 'string'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'internal_notes' => ['nullable', 'string'],
            'training_objective' => ['nullable', 'string'],
            'knowledge' => ['nullable', 'string'],
            'skills' => ['nullable', 'string'],
            'competences' => ['nullable', 'string'],
            'regulatory_reference' => ['nullable', 'string'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'status' => ['required', 'string', Rule::in(Course::availableStatuses())],
            'is_financed' => ['nullable', 'boolean'],
            'funding_entity_id' => [
                'nullable',
                'integer',
                Rule::exists('funding_entities', 'id'),
                Rule::requiredIf($this->boolean('is_financed')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $isFinanced = $this->boolean('is_financed');

        $this->merge([
            'is_financed' => $isFinanced,
            'funding_entity_id' => $isFinanced ? $this->input('funding_entity_id') : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Titolo del corso'),
            'code' => __('Codice corso'),
            'description' => __('Descrizione'),
            'teaching_material' => __('Materiale didattico'),
            'max_participants' => __('Numero massimo partecipanti'),
            'internal_notes' => __('Note interne corso'),
            'training_objective' => __('Obiettivo formativo'),
            'knowledge' => __('Conoscenze'),
            'skills' => __('Abilità'),
            'competences' => __('Competenze'),
            'regulatory_reference' => __('Riferimento normativo'),
            'year' => __('Anno del corso'),
            'status' => __('Stato'),
            'is_financed' => __('Corso finanziato'),
            'funding_entity_id' => __('Ente finanziatore'),
        ];
    }
}
