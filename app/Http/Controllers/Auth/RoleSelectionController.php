<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class RoleSelectionController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $roles = $request->user()?->getRoleNames() ?? collect();

        if ($roles->count() <= 1) {
            return $this->redirectToRole($request, $roles->first());
        }

        return view('auth.select-role', ['roles' => $roles]);
    }

    public function update(Request $request): RedirectResponse
    {
        $role = (string) $request->validate([
            'role' => ['required', 'string'],
        ])['role'];

        abort_unless($request->user()?->hasRole($role), 403);

        return $this->redirectToRole($request, $role);
    }

    public function switch(Request $request, string $role): RedirectResponse
    {
        abort_unless($request->user()?->hasRole($role), 403);

        $redirectRoute = $request->query('redirect_route');

        return $this->redirectToRole($request, $role, is_string($redirectRoute) ? $redirectRoute : null);
    }

    private function redirectToRole(Request $request, ?string $role, ?string $redirectRoute = null): RedirectResponse
    {
        $request->session()->put('active_role', $role);

        if ($redirectRoute !== null && Route::has($redirectRoute)) {
            return redirect()->route($redirectRoute);
        }

        return redirect(match ($role) {
            'admin', 'superadmin' => route('admin.dashboard'),
            'teacher', 'docente' => route('teacher.courses.index'),
            'tutor' => route('tutor.dashboard'),
            default => route('user.dashboard'),
        });
    }
}
