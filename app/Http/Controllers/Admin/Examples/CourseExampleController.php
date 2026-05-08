<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\CourseValidation\CourseValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Example controller showing how to use CourseValidatorService and observers.
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly CourseValidatorService $courseValidator
    ) {}

    /**
     * Validate a course.
     *
     * This shows how to manually check if a course is valid.
     */
    public function validateCourse(Course $course): JsonResponse
    {
        $isValid = $this->courseValidator->validate($course);

        if (! $isValid) {
            $errors = $this->courseValidator->getValidationErrors($course);

            return response()->json([
                'valid' => false,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Il corso è valido.',
        ]);
    }

    /**
     * Check if course is publishable.
     *
     * A course is publishable only if all modules are valid AND published.
     */
    public function checkPublishable(Course $course): JsonResponse
    {
        $isPublishable = $this->courseValidator->isPublishable($course);

        if (! $isPublishable) {
            $errors = $this->courseValidator->getPublishabilityErrors($course);

            return response()->json([
                'publishable' => false,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'publishable' => true,
            'message' => 'Il corso può essere pubblicato.',
        ]);
    }

    /**
     * Publish a course.
     *
     * The observer will automatically validate if the course can be published.
     * Will fail if:
     * - Any module is invalid
     * - Any module is not published
     */
    public function publish(Course $course): JsonResponse
    {
        try {
            $course->status = 'published';
            $course->save();

            return response()->json([
                'success' => true,
                'message' => 'Corso pubblicato con successo.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unpublish a course.
     *
     * The observer will automatically check if unpublishing is allowed.
     * Will fail if there are enrollments (even trashed).
     */
    public function unpublish(Course $course): JsonResponse
    {
        try {
            $course->status = 'draft';
            $course->save();

            return response()->json([
                'success' => true,
                'message' => 'Pubblicazione rimossa con successo.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a course.
     *
     * The observer will prevent updates if the course is published.
     */
    public function update(Request $request, Course $course): JsonResponse
    {
        try {
            $course->update($request->only(['title', 'description', 'type']));

            return response()->json([
                'success' => true,
                'message' => 'Corso aggiornato con successo.',
                'course' => $course,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get detailed validation report for a course.
     *
     * Useful for admin dashboards to show what needs to be fixed.
     */
    public function validationReport(Course $course): JsonResponse
    {
        $isValid = $this->courseValidator->validate($course);
        $isPublishable = $this->courseValidator->isPublishable($course);

        return response()->json([
            'course_id' => $course->id,
            'course_title' => $course->title,
            'is_valid' => $isValid,
            'is_publishable' => $isPublishable,
            'validation_errors' => $this->courseValidator->getValidationErrors($course),
            'publishability_errors' => $this->courseValidator->getPublishabilityErrors($course),
            'modules_count' => $course->modules->count(),
            'published_modules_count' => $course->modules->where('status', 'published')->count(),
        ]);
    }
}
