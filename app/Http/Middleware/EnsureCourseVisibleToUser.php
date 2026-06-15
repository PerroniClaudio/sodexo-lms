<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCourseVisibleToUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $course = $request->route('course');
        $user = $request->user();

        if ($course instanceof Course && $user instanceof User) {
            abort_unless($course->isVisibleTo($user), Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
