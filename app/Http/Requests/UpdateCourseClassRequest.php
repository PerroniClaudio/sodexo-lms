<?php

namespace App\Http\Requests;

use App\Models\Course;

class UpdateCourseClassRequest extends StoreCourseClassRequest
{
    public function authorize(): bool
    {
        $course = $this->course();
        $courseClass = $this->route('courseClass');

        return $course instanceof Course
            && $course->supportsClasses()
            && $courseClass?->course_id === $course->getKey();
    }
}
