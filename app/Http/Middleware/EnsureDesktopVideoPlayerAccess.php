<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\Module;
use App\Models\TrainingPathEnrollment;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureDesktopVideoPlayerAccess
{
    private const MOBILE_USER_AGENT_KEYWORDS = [
        'android',
        'iphone',
        'ipad',
        'ipod',
        'mobile',
        'windows phone',
        'opera mini',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $module = $request->route('module');

        if (
            config('app.show_courses_mobile')
            || ! $module instanceof Module
            || $module->type !== Module::TYPE_VIDEO
            || ! $this->isMobileRequest($request)
        ) {
            return $next($request);
        }

        $course = $request->route('course');
        $trainingPathEnrollment = $request->route('trainingPathEnrollment');

        return $this->redirectToCourseDetail($course, $trainingPathEnrollment);
    }

    private function isMobileRequest(Request $request): bool
    {
        $userAgent = Str::lower($request->userAgent() ?? '');

        return $userAgent !== '' && Str::contains($userAgent, self::MOBILE_USER_AGENT_KEYWORDS);
    }

    private function redirectToCourseDetail(
        mixed $course,
        mixed $trainingPathEnrollment,
    ): RedirectResponse {
        $message = __('Questo contenuto è visualizzabile solo da PC.');

        if ($course instanceof Course && $trainingPathEnrollment instanceof TrainingPathEnrollment) {
            return redirect()
                ->route('user.training-paths.courses.show', [$trainingPathEnrollment, $course])
                ->with('error', $message);
        }

        if ($course instanceof Course) {
            return redirect()
                ->route('user.courses.show', $course)
                ->with('error', $message);
        }

        return redirect()
            ->route('user.courses.index')
            ->with('error', $message);
    }
}
