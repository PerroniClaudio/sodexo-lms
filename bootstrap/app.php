<?php

use App\Http\Middleware\EnsureUserOnboarded;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureUserOnboarded::class,
        ]);

        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user?->hasAnyRole(['admin', 'superadmin'])) {
                return route('dashboard');
            }

            return route('reserved-area');
        });

        // Registra middleware alias di Spatie Permission
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return redirect()
                ->route('login')
                ->with('error', __('Devi effettuare il login per continuare.'));
        });

        $exceptions->render(function (UnauthorizedException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $user = $request->user();

            $redirectTo = $user?->hasAnyRole(['admin', 'superadmin'])
                ? route('dashboard')
                : route('reserved-area');

            return redirect($redirectTo)
                ->with('error', __('Non sei autorizzato ad accedere a questa sezione.'));
        });
    })->create();
