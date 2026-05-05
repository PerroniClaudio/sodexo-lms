<?php

namespace Database\Factories;

use App\Enums\OnboardingStep;
use App\Enums\UserStatus;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTitle;
use App\Models\JobUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected const AVAILABLE_ACCOUNT_ROLES = [
        'superadmin',
        'admin',
        'docente',
        'tutor',
        'user',
    ];

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    protected string $accountRole = 'user';

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $roleName = $this->resolveRoleName($this->accountRole);

            $user->syncRoles([$roleName]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roleSpecificAttributes = $this->accountRole === 'user'
            ? $this->defaultUserRoleAttributes()
            : $this->defaultNonUserRoleAttributes();

        return [
            // Autenticazione
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'email_verified_at' => now(),
            'account_state' => UserStatus::ACTIVE,
            'profile_completed_at' => now(),
            'remember_token' => Str::random(10),

            // Dati anagrafici obbligatori
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'fiscal_code' => $this->generateFiscalCode(),

            // Dati anagrafici opzionali
            'birth_date' => fake()->dateTimeBetween('-65 years', '-18 years'),
            'birth_place' => fake()->city(),
            'gender' => fake()->randomElement(['M', 'F']),
            'phone_prefix' => fake()->randomElement(['+39', '+1', '+44', '+33', '+49']),
            'phone' => fake()->optional()->numerify('##########'),
            'notes' => fake()->optional()->paragraph(),
            ...$roleSpecificAttributes,
        ];
    }

    /**
     * Attributi di default per utenza finale con ruolo user.
     *
     * @return array<string, mixed>
     */
    protected function defaultUserRoleAttributes(): array
    {
        $jobUnit = JobUnit::query()->inRandomOrder()->first() ?? JobUnit::factory()->create();
        $jobTitle = JobTitle::query()->inRandomOrder()->first() ?? JobTitle::factory()->create();
        $jobRole = JobRole::query()->inRandomOrder()->first() ?? JobRole::factory()->create();
        $jobSector = JobSector::query()->inRandomOrder()->first() ?? JobSector::factory()->create();

        return [
            'home_country_id' => $jobUnit->country_id,
            'home_region_id' => $jobUnit->region_id,
            'home_province_id' => $jobUnit->province_id,
            'home_city_id' => $jobUnit->city_id,
            'address' => fake()->optional()->streetAddress(),
            'postal_code' => fake()->optional()->postcode(),
            'job_unit_id' => $jobUnit->id,
            'job_category_id' => null,
            'job_level_id' => null,
            'job_title_id' => $jobTitle->id,
            'job_role_id' => $jobRole->id,
            'job_sector_id' => $jobSector->id,
            'is_foreigner_or_immigrant' => fake()->boolean(20),
        ];
    }

    /**
     * Attributi di default per ruoli diversi da user.
     *
     * @return array<string, mixed>
     */
    protected function defaultNonUserRoleAttributes(): array
    {
        return [
            'home_country_id' => null,
            'home_region_id' => null,
            'home_province_id' => null,
            'home_city_id' => null,
            'address' => fake()->optional()->streetAddress(),
            'postal_code' => fake()->optional()->postcode(),
            'job_unit_id' => null,
            'job_category_id' => null,
            'job_level_id' => null,
            'job_title_id' => null,
            'job_role_id' => null,
            'job_sector_id' => null,
            'is_foreigner_or_immigrant' => null,
        ];
    }

    /**
     * Generate a fake Italian fiscal code
     */
    protected function generateFiscalCode(): string
    {
        $consonants = 'BCDFGHJKLMNPQRSTVWXYZ';
        $vowels = 'AEIOU';

        $code = '';

        // 6 caratteri per nome e cognome
        $code .= Str::upper(Str::random(3));
        $code .= Str::upper(Str::random(3));

        // 5 caratteri per data nascita e sesso
        $year = fake()->numberBetween(40, 99);
        $month = ['A', 'B', 'C', 'D', 'E', 'H', 'L', 'M', 'P', 'R', 'S', 'T'][fake()->numberBetween(0, 11)];
        $day = fake()->numberBetween(1, 31);
        if (fake()->boolean()) {
            $day += 40; // Donne
        }

        $code .= str_pad($year, 2, '0', STR_PAD_LEFT);
        $code .= $month;
        $code .= str_pad($day, 2, '0', STR_PAD_LEFT);

        // 4 caratteri per comune e carattere di controllo
        $code .= Str::upper(Str::random(4));
        $code .= fake()->randomElement(str_split($consonants.$vowels));

        return strtoupper($code);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'account_state' => 'pending',
        ]);
    }

    /**
     * User straniero o immigrato
     */
    public function foreignerOrImmigrant(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_foreigner_or_immigrant' => true,
        ]);
    }

    /**
     * User con dati job completi (obbligatori per utenti normali con ruolo 'user')
     */
    public function withJobData(): static
    {
        return $this->state(function (array $attributes) {
            $jobUnit = JobUnit::query()->inRandomOrder()->first() ?? JobUnit::factory()->create();
            $jobTitle = JobTitle::query()->inRandomOrder()->first() ?? JobTitle::factory()->create();
            $jobRole = JobRole::query()->inRandomOrder()->first() ?? JobRole::factory()->create();
            $jobSector = JobSector::query()->inRandomOrder()->first() ?? JobSector::factory()->create();

            return [
                // Job relations obbligatori
                'job_unit_id' => $jobUnit->id,
                'job_title_id' => $jobTitle->id,
                'job_role_id' => $jobRole->id,
                'job_sector_id' => $jobSector->id,
                'home_country_id' => $jobUnit->country_id,
                'home_region_id' => $jobUnit->region_id,
                'home_province_id' => $jobUnit->province_id,
                'home_city_id' => $jobUnit->city_id,

                // Job relations opzionali
                'job_category_id' => JobCategory::query()->inRandomOrder()->first()?->id,
                'job_level_id' => JobLevel::query()->inRandomOrder()->first()?->id,
            ];
        });
    }

    /**
     * User in stato pending (appena creato)
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_state' => UserStatus::PENDING,
            'email_verified_at' => null,
            'profile_completed_at' => null,
            'onboarding_step' => null,
        ]);
    }

    /**
     * User in fase di onboarding
     */
    public function onboarding(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_state' => UserStatus::ONBOARDING,
            'email_verified_at' => now(),
            'profile_completed_at' => null,
            'onboarding_step' => OnboardingStep::PROFILE_COMPLETION,
        ]);
    }

    /**
     * User che richiede aggiornamento dati
     */
    public function updateRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_state' => UserStatus::UPDATE_REQUIRED,
            'last_data_update_request' => now(),
        ]);
    }

    /**
     * User sospeso
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_state' => UserStatus::SUSPENDED,
        ]);
    }

    public function withRole(string $role): static
    {
        $normalizedRole = Str::lower($role);

        if (! in_array($normalizedRole, self::AVAILABLE_ACCOUNT_ROLES, true)) {
            throw new InvalidArgumentException("Unsupported account role [{$role}].");
        }

        $factory = clone $this;
        $factory->accountRole = $normalizedRole;

        return $factory;
    }

    public function asSuperadmin(): static
    {
        return $this->withRole('superadmin');
    }

    public function asAdmin(): static
    {
        return $this->withRole('admin');
    }

    public function asDocente(): static
    {
        return $this->withRole('docente');
    }

    public function asTutor(): static
    {
        return $this->withRole('tutor');
    }

    public function asUser(): static
    {
        return $this->withRole('user');
    }

    protected function resolveRoleName(string $role): string
    {
        $existingRole = Role::query()->where('name', $role)->first();

        if ($existingRole === null) {
            throw new InvalidArgumentException("Role [{$role}] does not exist. Seed roles before using UserFactory role helpers.");
        }

        return $existingRole->name;
    }
}
