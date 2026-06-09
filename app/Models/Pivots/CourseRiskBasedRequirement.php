<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CourseRiskBasedRequirement extends Pivot
{
    protected $table = 'course_risk_based_requirement';

    /**
     * @return array<int, string>
     */
    public function getCourseValidityTypesAttribute(mixed $value): array
    {
        $decodedValue = is_string($value)
            ? json_decode($value, true)
            : $value;

        return is_array($decodedValue) ? array_values($decodedValue) : [];
    }

    /**
     * @return array<int, string>
     */
    public function getIntegrativeStartRiskLevelsAttribute(mixed $value): array
    {
        $decodedValue = is_string($value)
            ? json_decode($value, true)
            : $value;

        return is_array($decodedValue) ? array_values($decodedValue) : [];
    }
}
