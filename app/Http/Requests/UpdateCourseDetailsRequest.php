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
        $supportsVenue = in_array($this->route('course')?->type, ['res', 'blended'], true);
        $usesJobUnit = $supportsVenue && $this->input('venue_mode') === 'job_unit';
        $usesVenue = $supportsVenue && $this->input('venue_mode') === 'venue';

        return [
            'title' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('courses', 'code')->ignore($this->route('course'))],
            'description' => ['required', 'string'],
            'teaching_material' => ['nullable', 'string'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'participant_presence_verification' => [
                'nullable',
                'string',
                Rule::in(Course::availableParticipantPresenceVerifications()),
                Rule::prohibitedIf(fn (): bool => ! in_array($this->route('course')?->type, ['res', 'blended'], true)),
            ],
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
            'venue_mode' => [
                'nullable',
                'string',
                Rule::in(['job_unit', 'venue']),
                Rule::prohibitedIf(! $supportsVenue),
            ],
            'job_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('job_units', 'id'),
                Rule::requiredIf($usesJobUnit),
                Rule::prohibitedIf($usesVenue || ! $supportsVenue),
            ],
            'venue_id' => [
                'nullable',
                'integer',
                Rule::exists('venues', 'id'),
                Rule::prohibitedIf($usesJobUnit || ! $supportsVenue),
            ],
            'venue_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf($usesVenue && blank($this->input('venue_id'))),
                Rule::prohibitedIf($usesJobUnit || ! $supportsVenue),
            ],
            'country' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf($usesVenue && blank($this->input('venue_id'))),
                Rule::prohibitedIf($usesJobUnit || ! $supportsVenue),
            ],
            'region' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf($usesVenue && blank($this->input('venue_id'))),
                Rule::prohibitedIf($usesJobUnit || ! $supportsVenue),
            ],
            'province' => [
                'nullable',
                'string',
                'max:100',
                Rule::prohibitedIf($usesJobUnit || ! $supportsVenue),
            ],
            'city' => [
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf($usesVenue && blank($this->input('venue_id'))),
                Rule::prohibitedIf($usesJobUnit || ! $supportsVenue),
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
                Rule::prohibitedIf($usesJobUnit || ! $supportsVenue),
            ],
            'postal_code' => [
                'nullable',
                'string',
                'max:20',
                Rule::prohibitedIf($usesJobUnit || ! $supportsVenue),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $isFinanced = $this->boolean('is_financed');

        $this->merge([
            'is_financed' => $isFinanced,
            'funding_entity_id' => $isFinanced ? $this->input('funding_entity_id') : null,
            'venue_mode' => $this->input('venue_mode') ?: null,
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
            'participant_presence_verification' => __('Verifica Presenza Partecipanti'),
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
            'venue_mode' => __('Tipo sede'),
            'job_unit_id' => __('Unità produttiva'),
            'venue_id' => __('Sede esistente'),
            'venue_name' => __('Nome sede'),
            'country' => __('Paese'),
            'region' => __('Regione'),
            'province' => __('Provincia'),
            'city' => __('Comune'),
            'address' => __('Indirizzo'),
            'postal_code' => __('CAP'),
        ];
    }
}
