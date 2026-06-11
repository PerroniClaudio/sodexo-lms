<?php

namespace App\Http\Requests;

use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Models\RiskBasedRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCourseCertificatesRequest extends FormRequest
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
            'risk_based_requirement_ids' => ['nullable', 'array'],
            'risk_based_requirement_ids.*' => ['integer', 'exists:risk_based_requirements,id'],
            'risk_based_requirement_validity_types' => ['nullable', 'array'],
            'risk_based_requirement_validity_types.*' => ['nullable', 'array'],
            'risk_based_requirement_validity_types.*.*' => ['nullable', 'string', Rule::in(CourseRiskRequirementValidityType::values())],
            'risk_based_requirement_integrative_start_levels' => ['nullable', 'array'],
            'risk_based_requirement_integrative_start_levels.*' => ['nullable', 'array'],
            'risk_based_requirement_integrative_start_levels.*.*' => ['nullable', 'string', Rule::in(RiskLevel::values())],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $selectedRequirementIds = collect($this->input('risk_based_requirement_ids', []))
                    ->map(fn (mixed $value): int => (int) $value)
                    ->filter()
                    ->values();

                if ($selectedRequirementIds->isEmpty()) {
                    return;
                }

                $requirements = RiskBasedRequirement::query()
                    ->whereIn('id', $selectedRequirementIds)
                    ->get()
                    ->keyBy('id');
                $validityTypes = collect($this->input('risk_based_requirement_validity_types', []));
                $integrativeStartLevels = collect($this->input('risk_based_requirement_integrative_start_levels', []));

                $selectedRequirementIds->each(function (int $requirementId) use ($requirements, $validityTypes, $integrativeStartLevels, $validator): void {
                    $requirement = $requirements->get($requirementId);
                    $selectedValidityTypes = CourseRiskRequirementValidityType::normalizeMany(
                        collect($validityTypes->get((string) $requirementId, []))
                            ->filter()
                            ->values()
                            ->all()
                    );

                    if ($selectedValidityTypes === []) {
                        $validator->errors()->add(
                            "risk_based_requirement_validity_types.$requirementId",
                            __('Seleziona almeno una tipologia di validità per ogni requisito associato al corso.')
                        );

                        return;
                    }

                    if (! $requirement instanceof RiskBasedRequirement || ! collect($selectedValidityTypes)->contains(
                        fn (CourseRiskRequirementValidityType $validityType): bool => $validityType === CourseRiskRequirementValidityType::Integrative
                    )) {
                        return;
                    }

                    if (! $requirement->isRiskSpecific()) {
                        $validator->errors()->add(
                            "risk_based_requirement_validity_types.$requirementId",
                            __('Il corso integrativo è disponibile solo per requisiti associati a un solo livello di rischio.')
                        );

                        return;
                    }

                    $targetRiskLevel = $requirement->singleRiskLevel();
                    $startLevels = collect($integrativeStartLevels->get((string) $requirementId, []))
                        ->filter()
                        ->unique()
                        ->values();

                    if ($startLevels->isEmpty()) {
                        $validator->errors()->add(
                            "risk_based_requirement_integrative_start_levels.$requirementId",
                            __('Se selezioni un corso integrativo devi indicare almeno un livello di partenza valido.')
                        );

                        return;
                    }

                    if ($targetRiskLevel !== null && $startLevels->contains($targetRiskLevel->value)) {
                        $validator->errors()->add(
                            "risk_based_requirement_integrative_start_levels.$requirementId",
                            __('Il livello finale del requisito non può essere usato come livello di partenza del corso integrativo.')
                        );
                    }

                    if ($targetRiskLevel !== null && $startLevels->contains(function (string $level) use ($targetRiskLevel): bool {
                        $riskLevel = RiskLevel::tryFrom($level);

                        return $riskLevel instanceof RiskLevel && ! $riskLevel->isLowerThan($targetRiskLevel);
                    })) {
                        $validator->errors()->add(
                            "risk_based_requirement_integrative_start_levels.$requirementId",
                            __('I livelli iniziali del corso integrativo devono essere inferiori al livello finale coperto dal requisito.')
                        );
                    }
                });
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'risk_based_requirement_ids' => __('Requisiti di rischio'),
            'risk_based_requirement_validity_types' => __('Tipo di validità corso per requisito'),
            'risk_based_requirement_integrative_start_levels' => __('Livelli iniziali corso integrativo'),
        ];
    }
}
