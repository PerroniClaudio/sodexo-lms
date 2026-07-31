<?php

namespace App\Http\Middleware;

use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Services\QuizAccessDelayService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccessDelayElapsed
{
    public function __construct(private readonly QuizAccessDelayService $accessDelayService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $module = $request->route('module');

        if (! $module instanceof Module || ! $module->hasAccessDelay()) {
            return $next($request);
        }

        $enrollment = CourseEnrollment::query()
            ->where('course_id', $module->belongsTo)
            ->where('user_id', $request->user()?->getKey())
            ->first();

        $progress = $enrollment?->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->first();

        if ($progress?->status === 'completed') {
            return $next($request);
        }

        $accessGate = $enrollment === null ? null : $this->accessDelayService->resolve($enrollment, $module);

        if (! ($accessGate['active'] ?? false)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => __('Questo modulo sarà disponibile allo scadere del timer di accesso.'),
                'access_gate' => $accessGate,
            ], Response::HTTP_LOCKED);
        }

        abort(Response::HTTP_LOCKED);
    }
}
