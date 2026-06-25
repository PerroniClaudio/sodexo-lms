<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Importazione;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\LanguageLevel;
use App\Models\Province;
use App\Models\User;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

class UserImportService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'email' => ['email'],
        'account_types' => ['tipo_di_account', 'tipo_account', 'account_type', 'tipi_di_account'],
        'name' => ['nome'],
        'surname' => ['cognome'],
        'phone_prefix' => ['prefisso_nazionale', 'prefisso'],
        'phone' => ['numero_di_telefono', 'telefono', 'numero_telefono'],
        'fiscal_code' => ['codice_fiscale', 'cf'],
        'country' => ['nazione_di_residenza_domicilio', 'nazione'],
        'region' => ['regione_di_residenza_domicilio', 'regione'],
        'province' => ['provincia_di_residenza_domicilio', 'provincia'],
        'address' => ['indirizzo_di_residenza_domicilio', 'indirizzo'],
        'postal_code' => ['codice_postale_di_residenza_domicilio', 'cap', 'codice_postale'],
        'birth_date' => ['data_di_nascita'],
        'birth_place' => ['luogo_di_nascita'],
        'gender' => ['genere'],
        'job_sector' => ['settore'],
        'job_category' => ['categoria_di_lavoro'],
        'job_level' => ['livello_di_inquadramento'],
        'job_role' => ['ruolo'],
        'job_task_code' => ['mansione_codice', 'mansione', 'mansione_codice_o_id_separa_con'],
        'job_unit_code' => ['unita_lavorativa_codice', 'unita_lavorativa'],
        'is_foreigner' => ['straniero'],
        'employment_start_date' => ['data_di_assunzione'],
        'employment_end_date' => ['data_di_cessazione'],
        'language_level' => ['livello_conoscenza_lingua_di_lavoro', 'livello_lingua_di_lavoro'],
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $jobSectors = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $jobCategories = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $jobLevels = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $jobRoles = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $jobTasks = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $jobUnits = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $countries = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $regions = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $provinces = null;

    /**
     * @var array<string, int>|null
     */
    private ?array $languageLevels = null;

    public function __construct(
        private readonly UserJobAssignmentService $userJobAssignmentService,
    ) {}

    public function import(Importazione $importazione, string $localFilePath): void
    {
        $rows = $this->rowsFromSpreadsheet($localFilePath);
        $seenFiscalCodes = [];

        DB::transaction(function () use ($rows, &$seenFiscalCodes): void {
            foreach ($rows as $rowNumber => $row) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $payload = $this->buildUserPayload($row, $rowNumber);

                if (isset($seenFiscalCodes[$payload['fiscal_code']])) {
                    $this->fail($rowNumber, __('codice fiscale duplicato nel file.'));
                }

                $seenFiscalCodes[$payload['fiscal_code']] = true;

                $this->upsertUser($payload, $rowNumber);
            }
        });
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function rowsFromSpreadsheet(string $localFilePath): array
    {
        $spreadsheet = IOFactory::load($localFilePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $headerRow = $rows[1] ?? [];
        $headerMap = $this->resolveHeaderMap($headerRow);

        foreach (['account_types', 'name', 'surname', 'fiscal_code'] as $requiredHeader) {
            if (! array_key_exists($requiredHeader, $headerMap)) {
                throw ValidationException::withMessages([
                    'file' => __('Colonna obbligatoria mancante: :column', ['column' => $requiredHeader]),
                ]);
            }
        }

        $mappedRows = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                continue;
            }

            $mappedRow = [];

            foreach ($headerMap as $field => $column) {
                $mappedRow[$field] = $this->nullableString($row[$column] ?? null);
            }

            $mappedRows[$rowNumber] = $mappedRow;
        }

        return $mappedRows;
    }

    /**
     * @param  array<string, mixed>  $headerRow
     * @return array<string, string>
     */
    private function resolveHeaderMap(array $headerRow): array
    {
        $normalizedHeaders = collect($headerRow)
            ->filter(fn (mixed $value): bool => $this->nullableString($value) !== null)
            ->mapWithKeys(fn (mixed $value, string $column): array => [$this->normalizeKey((string) $value) => $column])
            ->all();

        $resolved = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($normalizedHeaders[$alias])) {
                    $resolved[$field] = $normalizedHeaders[$alias];
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function buildUserPayload(array $row, int $rowNumber): array
    {
        $roles = $this->resolveRoles($row['account_types'] ?? null, $rowNumber);
        $isWorker = in_array('user', $roles, true);

        $email = $this->nullableString($row['email'] ?? null);
        $fiscalCode = Str::upper((string) $this->requireValue($row['fiscal_code'] ?? null, $rowNumber, __('codice fiscale obbligatorio.')));

        $payload = [
            'roles' => $roles,
            'email' => $email !== null ? Str::lower($email) : null,
            'name' => $this->requireValue($row['name'] ?? null, $rowNumber, __('nome obbligatorio.')),
            'surname' => $this->requireValue($row['surname'] ?? null, $rowNumber, __('cognome obbligatorio.')),
            'fiscal_code' => $fiscalCode,
            'phone_prefix' => $this->nullableString($row['phone_prefix'] ?? null) ?? '+39',
            'phone' => $this->nullableString($row['phone'] ?? null),
            'birth_date' => $this->parseDate($row['birth_date'] ?? null, $rowNumber, 'data di nascita'),
            'birth_place' => $this->nullableString($row['birth_place'] ?? null),
            'gender' => $this->normalizeGender($row['gender'] ?? null),
            'home_country_id' => $this->resolveCountryId($row['country'] ?? null, $rowNumber),
            'home_region_id' => $this->resolveRegionId($row['region'] ?? null, $rowNumber),
            'home_province_id' => $this->resolveProvinceId($row['province'] ?? null, $rowNumber),
            'address' => $this->nullableString($row['address'] ?? null),
            'postal_code' => $this->nullableString($row['postal_code'] ?? null),
            'job_sector_id' => $this->resolveLookupId($this->jobSectors(), $row['job_sector'] ?? null, $rowNumber, 'settore', $isWorker),
            'job_category_id' => $this->resolveLookupId($this->jobCategories(), $row['job_category'] ?? null, $rowNumber, 'categoria di lavoro', false),
            'job_level_id' => $this->resolveLookupId($this->jobLevels(), $row['job_level'] ?? null, $rowNumber, 'livello di inquadramento', false),
            'job_role_id' => $this->resolveLookupId($this->jobRoles(), $row['job_role'] ?? null, $rowNumber, 'ruolo', $isWorker),
            'job_unit_id' => $this->resolveLookupId($this->jobUnits(), $row['job_unit_code'] ?? null, $rowNumber, 'unità lavorativa', $isWorker),
            'employment_start_date' => $this->parseDate($row['employment_start_date'] ?? null, $rowNumber, 'data di assunzione', $isWorker),
            'employment_end_date' => $this->parseDate($row['employment_end_date'] ?? null, $rowNumber, 'data di cessazione'),
            'is_foreigner_or_immigrant' => $this->parseBoolean($row['is_foreigner'] ?? null, $rowNumber, 'straniero', $isWorker) ?? false,
            'declared_language_level_id' => $this->resolveLookupId($this->languageLevels(), $row['language_level'] ?? null, $rowNumber, 'livello conoscenza lingua di lavoro', $isWorker),
        ];

        $jobTaskIds = $this->resolveLookupIds($this->jobTasks(), $row['job_task_code'] ?? null, $rowNumber, 'mansione', $isWorker);

        if ($payload['employment_start_date'] !== null && $payload['employment_end_date'] !== null && $payload['employment_end_date'] < $payload['employment_start_date']) {
            $this->fail($rowNumber, __('data di cessazione precedente alla data di assunzione.'));
        }

        $payload['job_task_assignments'] = $isWorker && $jobTaskIds !== [] && $payload['employment_start_date'] !== null
            ? collect($jobTaskIds)
                ->map(fn (int $jobTaskId): array => [
                    'job_task_id' => $jobTaskId,
                    'starts_at' => $payload['employment_start_date'],
                    'ends_at' => $payload['employment_end_date'],
                ])
                ->all()
            : [];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertUser(array $payload, int $rowNumber): void
    {
        $roles = $payload['roles'];
        $isWorker = in_array('user', $roles, true);

        $existingUser = User::withTrashed()
            ->where('fiscal_code', $payload['fiscal_code'])
            ->first();

        $email = $payload['email'];

        if ($email !== null) {
            $emailOwner = User::withTrashed()
                ->where('email', $email)
                ->when($existingUser !== null, fn ($query) => $query->whereKeyNot($existingUser->getKey()))
                ->first();

            if ($emailOwner !== null) {
                $this->fail($rowNumber, __('email già assegnata a un altro utente.'));
            }
        }

        $userData = collect($payload)
            ->except(['roles', 'job_task_assignments'])
            ->all();

        if ($existingUser !== null) {
            if (! $isWorker) {
                $userData['account_state'] = UserStatus::ACTIVE;
                $userData['profile_completed_at'] = $existingUser->profile_completed_at ?? now();
            }

            $existingUser->fill($userData);
            $existingUser->save();
            $existingUser->syncRoles($roles);

            if ($existingUser->trashed()) {
                $existingUser->restore();
            }

            $this->syncAssignments($existingUser, $payload['job_task_assignments'], $isWorker);

            return;
        }

        $userData['account_state'] = $isWorker ? UserStatus::PENDING : UserStatus::ACTIVE;

        if (! $isWorker) {
            $userData['profile_completed_at'] = now();
        }

        $user = User::query()->create($userData);
        $user->syncRoles($roles);
        $this->syncAssignments($user, $payload['job_task_assignments'], $isWorker);
    }

    /**
     * @param  array<int, array{job_task_id: int, starts_at: string, ends_at: ?string}>  $assignments
     */
    private function syncAssignments(User $user, array $assignments, bool $isWorker): void
    {
        if (! $isWorker) {
            $user->jobTasks()->detach();
            $user->forceFill(['job_task_id' => null])->saveQuietly();

            return;
        }

        $this->userJobAssignmentService->syncAssignments($user, $assignments, true);
    }

    /**
     * @return array<int, string>
     */
    private function resolveRoles(?string $accountTypes, int $rowNumber): array
    {
        $rawRoles = collect(explode(';', (string) $accountTypes))
            ->map(fn (string $role): string => trim($role))
            ->filter()
            ->values();

        if ($rawRoles->isEmpty()) {
            $this->fail($rowNumber, __('tipo di account obbligatorio.'));
        }

        $availableTeacherRoles = Role::query()
            ->whereIn('name', ['teacher', 'docente'])
            ->pluck('name');

        $roles = $rawRoles
            ->map(function (string $role) use ($availableTeacherRoles, $rowNumber): string {
                $normalized = $this->normalizeKey($role);

                return match ($normalized) {
                    'user', 'utente' => 'user',
                    'admin', 'amministratore' => 'admin',
                    'tutor' => 'tutor',
                    'teacher', 'docente' => $availableTeacherRoles->contains('docente') ? 'docente' : 'teacher',
                    default => $this->invalidRole($rowNumber, $role),
                };
            })
            ->unique()
            ->values()
            ->all();

        return $roles;
    }

    private function invalidRole(int $rowNumber, string $role): never
    {
        $this->fail($rowNumber, __('tipo di account non valido: :role', ['role' => $role]));
    }

    /**
     * @param  array<string, int>  $lookup
     */
    private function resolveLookupId(array $lookup, ?string $value, int $rowNumber, string $fieldLabel, bool $required): ?int
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            if ($required) {
                $this->fail($rowNumber, __(':field obbligatorio.', ['field' => $fieldLabel]));
            }

            return null;
        }

        $normalized = $this->normalizeKey($cleanValue);
        $resolved = $lookup[$normalized] ?? null;

        if ($resolved === null) {
            $this->fail($rowNumber, __(':field non valido: :value', ['field' => $fieldLabel, 'value' => $cleanValue]));
        }

        return $resolved;
    }

    /**
     * @param  array<string, int>  $lookup
     * @return array<int, int>
     */
    private function resolveLookupIds(array $lookup, ?string $value, int $rowNumber, string $fieldLabel, bool $required): array
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            if ($required) {
                $this->fail($rowNumber, __(':field obbligatorio.', ['field' => $fieldLabel]));
            }

            return [];
        }

        return collect(explode(';', $cleanValue))
            ->map(fn (string $item): ?int => $this->resolveLookupId($lookup, $item, $rowNumber, $fieldLabel, true))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCountryId(?string $value, int $rowNumber): ?int
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            return null;
        }

        $resolved = $this->countries()[$this->normalizeKey($cleanValue)] ?? null;

        if ($resolved === null) {
            $this->fail($rowNumber, __('nazione non valida: :value', ['value' => $cleanValue]));
        }

        return $resolved;
    }

    private function resolveRegionId(?string $value, int $rowNumber): ?int
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            return null;
        }

        $resolved = $this->regions()[$this->normalizeKey($cleanValue)] ?? null;

        if ($resolved === null) {
            $this->fail($rowNumber, __('regione non valida: :value', ['value' => $cleanValue]));
        }

        return $resolved;
    }

    private function resolveProvinceId(?string $value, int $rowNumber): ?int
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            return null;
        }

        $resolved = $this->provinces()[$this->normalizeKey($cleanValue)] ?? null;

        if ($resolved === null) {
            $this->fail($rowNumber, __('provincia non valida: :value', ['value' => $cleanValue]));
        }

        return $resolved;
    }

    private function parseBoolean(?string $value, int $rowNumber, string $fieldLabel, bool $required): ?bool
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            if ($required) {
                $this->fail($rowNumber, __(':field obbligatorio.', ['field' => $fieldLabel]));
            }

            return null;
        }

        return match ($this->normalizeKey($cleanValue)) {
            'si', 's', 'yes', 'y', 'true', '1' => true,
            'no', 'false', '0' => false,
            default => $this->invalidBoolean($rowNumber, $fieldLabel, $cleanValue),
        };
    }

    private function invalidBoolean(int $rowNumber, string $fieldLabel, string $value): never
    {
        $this->fail($rowNumber, __(':field deve essere SI o NO. Valore ricevuto: :value', ['field' => $fieldLabel, 'value' => $value]));
    }

    private function normalizeGender(?string $value): ?string
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            return null;
        }

        return match ($this->normalizeKey($cleanValue)) {
            'm', 'maschio', 'male' => 'M',
            'f', 'femmina', 'female' => 'F',
            default => Str::upper(Str::substr($cleanValue, 0, 1)),
        };
    }

    private function parseDate(?string $value, int $rowNumber, string $fieldLabel, bool $required = false): ?string
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            if ($required) {
                $this->fail($rowNumber, __(':field obbligatoria.', ['field' => $fieldLabel]));
            }

            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $cleanValue);

            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return CarbonImmutable::parse($cleanValue)->format('Y-m-d');
        } catch (\Throwable) {
            $this->fail($rowNumber, __(':field non valida: :value', ['field' => $fieldLabel, 'value' => $cleanValue]));
        }
    }

    private function requireValue(?string $value, int $rowNumber, string $message): string
    {
        $cleanValue = $this->nullableString($value);

        if ($cleanValue === null) {
            $this->fail($rowNumber, $message);
        }

        return $cleanValue;
    }

    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn (mixed $value): bool => $this->nullableString($value) === null);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '' || strtoupper($stringValue) === 'NULL') {
            return null;
        }

        return $stringValue;
    }

    private function normalizeKey(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    private function fail(int $rowNumber, string $message): never
    {
        throw ValidationException::withMessages([
            "row_{$rowNumber}" => __('Riga :row: :message', ['row' => $rowNumber, 'message' => $message]),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function jobSectors(): array
    {
        return $this->jobSectors ??= $this->nameLookup(JobSector::query()->get(['id', 'name'])->all());
    }

    /**
     * @return array<string, int>
     */
    private function jobCategories(): array
    {
        return $this->jobCategories ??= $this->nameLookup(JobCategory::query()->get(['id', 'name'])->all());
    }

    /**
     * @return array<string, int>
     */
    private function jobLevels(): array
    {
        return $this->jobLevels ??= $this->nameLookup(JobLevel::query()->get(['id', 'name'])->all());
    }

    /**
     * @return array<string, int>
     */
    private function jobRoles(): array
    {
        return $this->jobRoles ??= $this->nameLookup(JobRole::query()->get(['id', 'name'])->all());
    }

    /**
     * @return array<string, int>
     */
    private function jobTasks(): array
    {
        if ($this->jobTasks !== null) {
            return $this->jobTasks;
        }

        $lookup = [];

        foreach (JobTask::query()->get(['id', 'code']) as $jobTask) {
            $lookup[(string) $jobTask->getKey()] = $jobTask->getKey();

            $code = $this->nullableString($jobTask->code);

            if ($code !== null) {
                $lookup[$this->normalizeKey($code)] = $jobTask->getKey();
            }
        }

        return $this->jobTasks = $lookup;
    }

    /**
     * @return array<string, int>
     */
    private function jobUnits(): array
    {
        return $this->jobUnits ??= collect(JobUnit::query()->get(['id', 'unit_code']))
            ->filter(fn (JobUnit $jobUnit): bool => $this->nullableString($jobUnit->unit_code) !== null)
            ->mapWithKeys(fn (JobUnit $jobUnit): array => [$this->normalizeKey((string) $jobUnit->unit_code) => $jobUnit->getKey()])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function countries(): array
    {
        return $this->countries ??= $this->codeLookup(WorldCountry::query()->get(['id', 'code'])->all());
    }

    /**
     * @return array<string, int>
     */
    private function regions(): array
    {
        if ($this->regions !== null) {
            return $this->regions;
        }

        $lookup = [];

        foreach (WorldDivision::query()->get(['id', 'name', 'full_name', 'code']) as $region) {
            foreach ([$region->name, $region->full_name, $region->code] as $candidate) {
                $candidate = $this->nullableString($candidate);

                if ($candidate !== null) {
                    $lookup[$this->normalizeKey($candidate)] = $region->getKey();
                }
            }
        }

        return $this->regions = $lookup;
    }

    /**
     * @return array<string, int>
     */
    private function provinces(): array
    {
        if ($this->provinces !== null) {
            return $this->provinces;
        }

        $lookup = [];

        foreach (Province::query()->get(['id', 'name', 'code']) as $province) {
            foreach ([$province->name, $province->code] as $candidate) {
                $candidate = $this->nullableString($candidate);

                if ($candidate !== null) {
                    $lookup[$this->normalizeKey($candidate)] = $province->getKey();
                }
            }
        }

        return $this->provinces = $lookup;
    }

    /**
     * @return array<string, int>
     */
    private function languageLevels(): array
    {
        return $this->languageLevels ??= $this->nameLookup(LanguageLevel::query()->get(['id', 'name'])->all());
    }

    /**
     * @param  array<int, object{id:int, name:?string}>  $records
     * @return array<string, int>
     */
    private function nameLookup(array $records): array
    {
        return collect($records)
            ->filter(fn (object $record): bool => $this->nullableString($record->name) !== null)
            ->mapWithKeys(fn (object $record): array => [$this->normalizeKey((string) $record->name) => $record->id])
            ->all();
    }

    /**
     * @param  array<int, object{id:int, code:?string}>  $records
     * @return array<string, int>
     */
    private function codeLookup(array $records): array
    {
        return collect($records)
            ->filter(fn (object $record): bool => $this->nullableString($record->code) !== null)
            ->mapWithKeys(fn (object $record): array => [$this->normalizeKey((string) $record->code) => $record->id])
            ->all();
    }
}
