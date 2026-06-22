@props([
    'user' => null,
])

@php
    $selectedRoles = collect(old('roles', $user?->getRoleNames()->all() ?? ['user']));
    $canManageRoles = auth()->user()?->hasRole('superadmin') ?? false;
    $roles = \Spatie\Permission\Models\Role::query()
        ->where('guard_name', config('auth.defaults.guard'))
        ->whereIn('name', ['user', 'admin', 'teacher', 'docente', 'tutor'])
        ->orderBy('name')
        ->pluck('name');

    if ($user?->hasRole('superadmin')) {
        $roles->push('superadmin');
    }

    $labels = [
        'user' => __('profile.options.account.user'),
        'admin' => __('profile.options.account.admin'),
        'teacher' => __('profile.options.account.teacher'),
        'docente' => __('profile.options.account.teacher'),
        'tutor' => __('profile.options.account.tutor'),
        'superadmin' => __('Superadmin'),
    ];
@endphp

<div class="grid gap-3 md:grid-cols-2" data-role-checkboxes>
    @foreach ($roles->unique() as $role)
        <label class="flex items-center gap-3 rounded-box border border-base-300 bg-base-100 p-4">
            <input
                type="checkbox"
                name="roles[]"
                value="{{ $role }}"
                class="checkbox checkbox-primary"
                data-role-checkbox
                @checked($selectedRoles->contains($role))
                @disabled(! $canManageRoles)
            >
            <span class="font-medium">{{ $labels[$role] ?? ucfirst($role) }}</span>
        </label>
        @if (! $canManageRoles && $selectedRoles->contains($role))
            <input type="hidden" name="roles[]" value="{{ $role }}">
        @endif
    @endforeach

    @error('roles')<span class="text-error text-sm md:col-span-2">{{ $message }}</span>@enderror
    @error('roles.*')<span class="text-error text-sm md:col-span-2">{{ $message }}</span>@enderror
</div>
