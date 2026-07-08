@props([
    'user' => null,
    'companyDivisions' => collect(),
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

<div class="mt-4 max-w-xl" data-user-only-block>
    <label for="company_division_id" class="label p-0 pb-2">
        <span class="label-text font-medium">{{ __('Divisione aziendale') }}</span>
        <span class="label-text-alt text-base-content/60">{{ __('Opzionale') }}</span>
    </label>
    <select id="company_division_id" name="company_division_id" class="select select-bordered w-full @error('company_division_id') select-error @enderror">
        <option value="">{{ __('Nessuna divisione') }}</option>
        @foreach ($companyDivisions as $division)
            <option value="{{ $division->getKey() }}" @selected((int) old('company_division_id', $user?->company_division_id) === (int) $division->getKey())>
                {{ $division->name }}
            </option>
        @endforeach
    </select>
    @error('company_division_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
</div>
