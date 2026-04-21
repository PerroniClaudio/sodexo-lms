<!-- Altri campi facoltativi possono essere aggiunti qui -->

<div class="flex flex-col gap-4">

    <div>

        <!-- Titolo sezione utente -->
        <div class="mt-4 mb-2">
            <span class="font-bold text-primary text-lg">Utente</span>
        </div>
    
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Tipo account -->
            <div class="form-control">
                <label for="account_type" class="label">
                    <span class="label-text font-semibold">Tipo account <span class="text-error">*</span></span>
                </label>
                <select name="account_type" id="account_type" class="select select-bordered w-full" required>
                    <option value="user" @selected(old('account_type', $user->account_type ?? '') == 'user')>User</option>
                    <option value="admin" @selected(old('account_type', $user->account_type ?? '') == 'admin')>Admin</option>
                    <option value="docente" @selected(old('account_type', $user->account_type ?? '') == 'docente')>Docente</option>
                    <option value="tutor" @selected(old('account_type', $user->account_type ?? '') == 'tutor')>Tutor</option>
                </select>
                @error('account_type')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
    
            <!-- Email -->
            <div class="form-control">
                <label for="email" class="label">
                    <span class="label-text font-semibold">Email <span class="text-error">*</span></span>
                </label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email ?? '') }}" class="input input-bordered w-full" required>
                @error('email')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
    
            <!-- Nome -->
            <div class="form-control">
                <label for="name" class="label">
                    <span class="label-text font-semibold">Nome <span class="text-error">*</span></span>
                </label>
                <input type="text" name="name" id="name" value="{{ old('name', $user->name ?? '') }}" class="input input-bordered w-full" required>
                @error('name')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
    
            <!-- Cognome -->
            <div class="form-control">
                <label for="surname" class="label">
                    <span class="label-text font-semibold">Cognome <span class="text-error">*</span></span>
                </label>
                <input type="text" name="surname" id="surname" value="{{ old('surname', $user->surname ?? '') }}" class="input input-bordered w-full" required>
                @error('surname')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
    
            <!-- Codice Fiscale -->
            <div class="form-control">
                <label for="fiscal_code" class="label">
                    <span class="label-text font-semibold">Codice Fiscale <span class="text-error">*</span></span>
                </label>
                <input type="text" name="fiscal_code" id="fiscal_code" value="{{ old('fiscal_code', $user->fiscal_code ?? '') }}" class="input input-bordered w-full" required>
                @error('fiscal_code')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
    
            <!-- Telefono -->
            <div class="form-control">
                <label for="phone" class="label">
                    <span class="label-text font-semibold">Telefono</span>
                </label>
                <div class="flex gap-2">
                    <input type="text" name="phone_prefix" id="phone_prefix" class="input input-bordered w-fit flex-0" placeholder="+39" value="{{ old('phone_prefix', $user->phone_prefix ?? '+39') }}">
                    <input type="text" name="phone" id="phone" class="input input-bordered flex-1" placeholder="Numero di telefono" value="{{ old('phone', $user->phone ?? '') }}">
                </div>
                @error('phone_prefix')<span class="text-error text-sm">{{ $message }}</span>@enderror
                @error('phone')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    <!-- Campi extra solo se account_type=user -->
    <div class="user-extra-fields" data-user-only>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Data di nascita -->
            <div class="form-control">
                <label for="birth_date" class="label">
                    <span class="label-text font-semibold">Data di nascita</span>
                </label>
                <input type="date" name="birth_date" id="birth_date" class="input input-bordered w-full" value="{{ old('birth_date', isset($user) && $user->birth_date ? $user->birth_date->format('Y-m-d') : '') }}">
                @error('birth_date')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
            <!-- Luogo di nascita -->
            <div class="form-control">
                <label for="birth_place" class="label">
                    <span class="label-text font-semibold">Luogo di nascita</span>
                </label>
                <input type="text" name="birth_place" id="birth_place" class="input input-bordered w-full" value="{{ old('birth_place', $user->birth_place ?? '') }}">
                @error('birth_place')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
            <!-- Genere -->
            <div class="form-control">
                <label for="gender" class="label">
                    <span class="label-text font-semibold">Genere</span>
                </label>
                <select name="gender" id="gender" class="select select-bordered w-full">
                    <option value="">Non specificato</option>
                    <option value="M" @selected(old('gender', $user->gender ?? '') == 'M')>Maschio</option>
                    <option value="F" @selected(old('gender', $user->gender ?? '') == 'F')>Femmina</option>
                </select>
                @error('gender')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
            <!-- Straniero/Immigrato -->
            <div class="form-control user-extra-fields" data-user-only>
                <label for="is_foreigner_or_immigrant" class="label">
                    <span class="label-text font-semibold">Straniero/Immigrato <span class="text-error">*</span></span>
                </label>
                <select name="is_foreigner_or_immigrant" id="is_foreigner_or_immigrant" class="select select-bordered w-full" required>
                    <option value="" disabled {{ !isset($user) && old('is_foreigner_or_immigrant', null) === null ? 'selected' : '' }} hidden>Seleziona...</option>
                    <option value="0" @selected(isset($user) ? (string)($user->is_foreigner_or_immigrant) === '0' : old('is_foreigner_or_immigrant', null) === '0')>No</option>
                    <option value="1" @selected(isset($user) ? (string)($user->is_foreigner_or_immigrant) === '1' : old('is_foreigner_or_immigrant', null) === '1')>Sì</option>
                </select>
                @error('is_foreigner_or_immigrant')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
        </div>

        <!-- Titolo sezione residenza/domicilio -->
        <div class="mt-4 mb-2">
            <span class="font-bold text-primary text-lg">Residenza/Domicilio</span>
        </div>
        <x-address-selector-simple 
            :countryValue="old('country', $user->homeCountry?->code ?? 'it')"
            :regionValue="old('region', $user->homeRegion?->name ?? '')"
            :provinceValue="old('province', $user->homeProvince?->name ?? '')"
            :cityValue="old('city', $user->homeCity?->name ?? '')"
            :addressValue="old('address', $user->address ?? '')"
            :postalCodeValue="old('postal_code', $user->postal_code ?? '')"
            :required="false"
        />


        <!-- Titolo sezione lavoro -->
        <div class="mt-4 mb-2">
            <span class="font-bold text-primary text-lg">Lavoro</span>
        </div>
        <!-- Settore -->
        <div class="form-control" data-user-only>
            <label for="job_sector_id" class="label">
                <span class="label-text font-semibold">Settore <span class="text-error">*</span></span>
            </label>
            <select name="job_sector_id" id="job_sector_id" class="select select-bordered w-full" required>
                <option value="">Seleziona</option>
                @foreach($jobSectors as $sector)
                    <option value="{{ $sector->id }}" @selected(old('job_sector_id', $user->job_sector_id ?? '') == $sector->id)>{{ $sector->name }}</option>
                @endforeach
            </select>
            @error('job_sector_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <!-- Categoria (facoltativo) -->
        <div class="form-control" data-user-only>
            <label for="job_category_id" class="label">
                <span class="label-text font-semibold">Categoria</span>
            </label>
            <select name="job_category_id" id="job_category_id" class="select select-bordered w-full">
                <option value="">Seleziona</option>
                @foreach($jobCategories as $category)
                    <option value="{{ $category->id }}" @selected(old('job_category_id', $user->job_category_id ?? '') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('job_category_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <!-- Livello di inquadramento (facoltativo) -->
        <div class="form-control" data-user-only>
            <label for="job_level_id" class="label">
                <span class="label-text font-semibold">Livello di inquadramento</span>
            </label>
            <select name="job_level_id" id="job_level_id" class="select select-bordered w-full">
                <option value="">Seleziona</option>
                @foreach($jobLevels as $level)
                    <option value="{{ $level->id }}" @selected(old('job_level_id', $user->job_level_id ?? '') == $level->id)>{{ $level->name }}</option>
                @endforeach
            </select>
            @error('job_level_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <!-- Mansione -->
        <div class="form-control" data-user-only>
            <label for="job_title_id" class="label">
                <span class="label-text font-semibold">Mansione <span class="text-error">*</span></span>
            </label>
            <select name="job_title_id" id="job_title_id" class="select select-bordered w-full" required>
                <option value="">Seleziona</option>
                @foreach($jobTitles as $title)
                    <option value="{{ $title->id }}" @selected(old('job_title_id', $user->job_title_id ?? '') == $title->id)>{{ $title->name }}</option>
                @endforeach
            </select>
            @error('job_title_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <!-- Ruolo -->
        <div class="form-control" data-user-only>
            <label for="job_role_id" class="label">
                <span class="label-text font-semibold">Ruolo <span class="text-error">*</span></span>
            </label>
            <select name="job_role_id" id="job_role_id" class="select select-bordered w-full" required>
                <option value="">Seleziona</option>
                @foreach($jobRoles as $role)
                    <option value="{{ $role->id }}" @selected(old('job_role_id', $user->job_role_id ?? '') == $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('job_role_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <!-- Unità Produttiva -->
        <div class="form-control" data-user-only>
            <label for="job_unit_id" class="label">
                <span class="label-text font-semibold">Unità Produttiva <span class="text-error">*</span></span>
            </label>
            <select name="job_unit_id" id="job_unit_id" class="select select-bordered w-full" required>
                <option value="">Seleziona</option>
                @foreach($jobUnits as $unit)
                    <option value="{{ $unit->id }}" @selected(old('job_unit_id', $user->job_unit_id ?? '') == $unit->id)>{{ $unit->name }}</option>
                @endforeach
            </select>
            @error('job_unit_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>
    </div>


    <!-- Campo password rimosso: la password viene generata automaticamente -->
</div>
<!-- Altri campi facoltativi possono essere aggiunti qui -->
