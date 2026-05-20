<?php

namespace App\Jobs;

use App\Models\CourseEnrollment;
use App\Services\Certificates\CourseCertificateGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;

class GenerateCourseCertificate implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    #[WithoutRelations]
    public CourseEnrollment $courseEnrollment;

    public function __construct(CourseEnrollment $courseEnrollment)
    {
        $this->courseEnrollment = $courseEnrollment;
    }

    public function handle(CourseCertificateGenerator $courseCertificateGenerator): void
    {
        $courseCertificateGenerator->generateForEnrollment($this->courseEnrollment);
    }
}
