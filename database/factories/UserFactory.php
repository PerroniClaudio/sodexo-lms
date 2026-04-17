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

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jobUnit = JobUnit::inRandomOrder()->first() ?? JobUnit::factory()->create();

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

            // Indirizzo residenza (opzionale)
            'nation' => fake()->optional()->countryCode(),
            'region' => fake()->optional()->state(),
            'province' => fake()->optional()->stateAbbr(),
            'city' => fake()->optional()->city(),
            'address' => fake()->optional()->streetAddress(),
            'postal_code' => fake()->optional()->postcode(),

            // Job relations
            'job_unit_id' => $jobUnit->id,
            'job_country' => $jobUnit->country,
            'job_region' => $jobUnit->region,
            'job_province' => $jobUnit->province,
            'job_category_id' => JobCategory::inRandomOrder()->first()?->id,
            'job_level_id' => JobLevel::inRandomOrder()->first()?->id,
            'job_title_id' => JobTitle::inRandomOrder()->first()?->id ?? JobTitle::factory(),
            'job_role_id' => JobRole::inRandomOrder()->first()?->id ?? JobRole::factory(),
            'job_sector_id' => JobSector::inRandomOrder()->first()?->id ?? JobSector::factory(),

            'is_foreigner_or_immigrant' => fake()->boolean(20),
            'notes' => fake()->optional()->paragraph(),
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
}
