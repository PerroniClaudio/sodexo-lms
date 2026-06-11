<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\Module;
use App\Models\RiskBasedRequirement;
use Illuminate\Support\Facades\DB;

class DuplicateCourse
{
    public function handle(Course $sourceCourse): Course
    {
        return DB::transaction(function () use ($sourceCourse): Course {
            $sourceCourse->loadMissing([
                'modules',
                'riskBasedRequirements',
                'originalCourse',
            ]);

            $rootCourse = $sourceCourse->originalCourse ?? $sourceCourse;
            $rootCourseId = $sourceCourse->familyRootCourseId();
            $nextEdition = $this->nextEdition($rootCourseId);

            $duplicatedCourse = $sourceCourse
                ->replicate([
                    'title',
                    'status',
                    'edition',
                    'original_course_id',
                ])
                ->fill([
                    'title' => sprintf('%s - edizione %d', $rootCourse->title, $nextEdition),
                    'status' => 'draft',
                    'edition' => $nextEdition,
                    'original_course_id' => $rootCourseId,
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
                'originalCourse',
            ]);
        });
    }

    private function nextEdition(int $rootCourseId): int
    {
        return (int) Course::query()
            ->where(function ($query) use ($rootCourseId): void {
                $query
                    ->whereKey($rootCourseId)
                    ->orWhere('original_course_id', $rootCourseId);
            })
            ->max('edition') + 1;
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
