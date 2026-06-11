<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\Module;
use App\Models\RiskBasedRequirement;
use Illuminate\Support\Facades\DB;

class DuplicateCourseStructure
{
    public function handle(Course $sourceCourse, string $code): Course
    {
        return DB::transaction(function () use ($sourceCourse, $code): Course {
            $sourceCourse->loadMissing([
                'modules',
                'riskBasedRequirements',
            ]);

            $duplicatedCourse = $sourceCourse
                ->replicate([
                    'status',
                    'edition',
                    'original_course_id',
                ])
                ->fill([
                    'code' => $code,
                    'status' => 'draft',
                    'edition' => 1,
                    'original_course_id' => null,
                ]);

            $duplicatedCourse->save();

            $duplicatedCourse->riskBasedRequirements()->sync(
                $this->riskRequirementSyncPayload($sourceCourse)
            );

            $sourceCourse->modules
                ->sortBy('order')
                ->each(function (Module $module) use ($duplicatedCourse): void {
                    $duplicatedCourse->modules()->save(
                        $module->replicate(['belongsTo'])->fill([
                            'belongsTo' => (string) $duplicatedCourse->getKey(),
                        ])
                    );
                });

            return $duplicatedCourse->fresh([
                'modules',
                'riskBasedRequirements',
            ]);
        });
    }

    /**
     * @return array<int, array{course_validity_types: ?string, integrative_start_risk_levels: ?string}>
     */
    private function riskRequirementSyncPayload(Course $sourceCourse): array
    {
        return $sourceCourse->riskBasedRequirements
            ->mapWithKeys(function (RiskBasedRequirement $riskBasedRequirement): array {
                return [
                    $riskBasedRequirement->getKey() => [
                        'course_validity_types' => $riskBasedRequirement->pivot->course_validity_types,
                        'integrative_start_risk_levels' => $riskBasedRequirement->pivot->integrative_start_risk_levels,
                    ],
                ];
            })
            ->all();
    }
}
