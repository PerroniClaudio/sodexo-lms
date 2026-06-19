@props([
    'user' => null,
    'languageLevels' => collect(),
])

@php
    $availableTeacherAccountType = \Spatie\Permission\Models\Role::query()->where('name', 'teacher')->exists()
        ? 'teacher'
        : 'docente';
    $selectedAccountType = old('account_type', $user?->getRoleNames()->first() ?? 'user');
    $canManageAccountType = auth()->user()?->hasRole('superadmin') ?? false;
    $accountTypeLabels = collect([
        'user' => __('profile.options.account.user'),
        'admin' => __('profile.options.account.admin'),
        $availableTeacherAccountType => __('profile.options.account.teacher'),
        'tutor' => __('profile.options.account.tutor'),
    ]);

    if ($selectedAccountType === 'docente' && ! $accountTypeLabels->has('docente')) {
        $accountTypeLabels->put('docente', __('profile.options.account.teacher'));
    }

    if ($selectedAccountType === 'superadmin') {
        $accountTypeLabels->put('superadmin', __('Superadmin'));
    }
@endphp

<div class="flex flex-col gap-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="form-control">
            <label for="account_type" class="label">
                <span class="label-text font-semibold">{{ __('profile.fields.account_type') }} <span class="text-error">*</span></span>
            </label>
            @if (! $user || $canManageAccountType)
                <select name="account_type" id="account_type" class="select select-bordered w-full" required>
                    @foreach ($accountTypeLabels as $accountTypeValue => $accountTypeLabel)
                        <option value="{{ $accountTypeValue }}" @selected($selectedAccountType === $accountTypeValue)>{{ $accountTypeLabel }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="account_type" value="{{ $selectedAccountType }}">
                <input type="text" value="{{ $accountTypeLabels->get($selectedAccountType, ucfirst($selectedAccountType)) }}" class="input input-bordered w-full" readonly>
            @endif
            @error('account_type')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control">
            <label for="email" class="label">
                <span class="label-text font-semibold">Email</span>
            </label>
            <input type="email" name="email" id="email" value="{{ old('email', $user->email ?? '') }}" class="input input-bordered w-full">
            <span class="text-xs text-base-content/60">{{ __('Facoltativa in creazione. L\'utente potrà completare l\'onboarding tramite codice fiscale.') }}</span>
            @error('email')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control">
            <label for="name" class="label">
                <span class="label-text font-semibold">Nome <span class="text-error">*</span></span>
            </label>
            <input type="text" name="name" id="name" value="{{ old('name', $user->name ?? '') }}" class="input input-bordered w-full" required>
            @error('name')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control">
            <label for="surname" class="label">
                <span class="label-text font-semibold">Cognome <span class="text-error">*</span></span>
            </label>
            <input type="text" name="surname" id="surname" value="{{ old('surname', $user->surname ?? '') }}" class="input input-bordered w-full" required>
            @error('surname')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control">
            <label for="fiscal_code" class="label">
                <span class="label-text font-semibold">Codice Fiscale <span class="text-error">*</span></span>
            </label>
            <input type="text" name="fiscal_code" id="fiscal_code" value="{{ old('fiscal_code', $user->fiscal_code ?? '') }}" class="input input-bordered w-full" required>
            @error('fiscal_code')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control">
            <label for="phone" class="label">
                <span class="label-text font-semibold">Telefono</span>
            </label>
            <div class="flex gap-2">
                <input type="text" name="phone_prefix" id="phone_prefix" class="input input-bordered w-fit flex-0" placeholder="+39" value="{{ old('phone_prefix', $user->phone_prefix ?? '+39') }}">
                <input type="text" name="phone" id="phone" class="input input-bordered flex-1" placeholder="{{ __('forms.phone_number_placeholder') }}" value="{{ old('phone', $user->phone ?? '') }}">
            </div>
            @error('phone_prefix')<span class="text-error text-sm">{{ $message }}</span>@enderror
            @error('phone')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>
    </div>

    <div data-user-only-block>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="form-control">
                <label for="birth_date" class="label">
                    <span class="label-text font-semibold">Data di nascita</span>
                </label>
                <input type="date" name="birth_date" id="birth_date" class="input input-bordered w-full" value="{{ old('birth_date', isset($user) && $user->birth_date ? $user->birth_date->format('Y-m-d') : '') }}">
                @error('birth_date')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="birth_place" class="label">
                    <span class="label-text font-semibold">Luogo di nascita</span>
                </label>
                <input type="text" name="birth_place" id="birth_place" class="input input-bordered w-full" value="{{ old('birth_place', $user->birth_place ?? '') }}">
                @error('birth_place')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="gender" class="label">
                    <span class="label-text font-semibold">Genere</span>
                </label>
                <select name="gender" id="gender" class="select select-bordered w-full">
                    <option value="">{{ __('profile.options.unspecified') }}</option>
                    <option value="M" @selected(old('gender', $user->gender ?? '') == 'M')>{{ __('Maschio') }}</option>
                    <option value="F" @selected(old('gender', $user->gender ?? '') == 'F')>{{ __('Femmina') }}</option>
                </select>
                @error('gender')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="is_foreigner_or_immigrant" class="label">
                    <span class="label-text font-semibold">Straniero/Immigrato <span class="text-error">*</span></span>
                </label>
                <select name="is_foreigner_or_immigrant" id="is_foreigner_or_immigrant" class="select select-bordered w-full" data-required="true" required>
                    <option value="" disabled {{ ! isset($user) && old('is_foreigner_or_immigrant', null) === null ? 'selected' : '' }} hidden>{{ __('forms.select_placeholder') }}</option>
                    <option value="0" @selected(isset($user) ? (string) ($user->is_foreigner_or_immigrant) === '0' : old('is_foreigner_or_immigrant', null) === '0')>{{ __('profile.options.no') }}</option>
                    <option value="1" @selected(isset($user) ? (string) ($user->is_foreigner_or_immigrant) === '1' : old('is_foreigner_or_immigrant', null) === '1')>{{ __('profile.options.yes') }}</option>
                </select>
                @error('is_foreigner_or_immigrant')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="declared_language_level_id" class="label">
                    <span class="label-text font-semibold">{{ __('Livello lingua dichiarato') }}</span>
                </label>
                <select name="declared_language_level_id" id="declared_language_level_id" class="select select-bordered w-full">
                    <option value="">{{ __('Non dichiarato') }}</option>
                    @foreach ($languageLevels as $languageLevel)
                        <option value="{{ $languageLevel->id }}" @selected((string) old('declared_language_level_id', $user?->declared_language_level_id) === (string) $languageLevel->id)>
                            {{ strtoupper($languageLevel->name) }}
                        </option>
                    @endforeach
                </select>
                @error('declared_language_level_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="verified_language_level_id" class="label">
                    <span class="label-text font-semibold">{{ __('Livello lingua verificato') }}</span>
                </label>
                <select name="verified_language_level_id" id="verified_language_level_id" class="select select-bordered w-full">
                    <option value="">{{ __('Non verificato') }}</option>
                    @foreach ($languageLevels as $languageLevel)
                        <option value="{{ $languageLevel->id }}" @selected((string) old('verified_language_level_id', $user?->verified_language_level_id) === (string) $languageLevel->id)>
                            {{ strtoupper($languageLevel->name) }}
                        </option>
                    @endforeach
                </select>
                @error('verified_language_level_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            @if (isset($user))
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">{{ __('Richiede verifica lingua') }}</span>
                    </label>
                    <input type="text" class="input input-bordered w-full" value="{{ $user->needs_language_level_verification ? __('Si') : __('No') }}" readonly>
                </div>
            @endif
        </div>
    </div>
</div>
