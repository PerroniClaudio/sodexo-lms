<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\Module;

class UpdateCourseClassRequest extends StoreCourseClassRequest
{
    public function authorize(): bool
    {
        $course = $this->course();
        $courseClass = $this->route('courseClass');
        $module = $courseClass?->module;

        return $course instanceof Course
            && $course->supportsClasses()
            && $module instanceof Module
            && (int) $module->belongsTo === (int) $course->getKey();
    }
}
