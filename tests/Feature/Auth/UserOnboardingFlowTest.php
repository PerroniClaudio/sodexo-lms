<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\WorldCountry;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RoleAndPermissionSeeder::class);
});

it('redirects to the email step when the fiscal code belongs to a user without email', function () {
    $user = User::factory()->asUser()->pending()->create([
        'email' => null,
        'email_verified_at' => null,
    ]);

    $response = $this->post(route('onboarding.lookup'), [
        'fiscal_code' => $user->fiscal_code,
    ]);

    $response->assertRedirect(route('onboarding.email.show'));

    $this->get(route('onboarding.email.show'))
        ->assertSuccessful()
        ->assertSee('Inserisci la tua email');
});

it('stores the email and sends a verification notification', function () {
    $user = User::factory()->asUser()->pending()->create([
        'email' => null,
        'email_verified_at' => null,
    ]);

    Notification::fake();

    $this->post(route('onboarding.lookup'), [
        'fiscal_code' => $user->fiscal_code,
    ]);

    $response = $this->post(route('onboarding.email.store'), [
        'email' => 'worker@example.test',
    ]);

    $response->assertRedirect(route('onboarding.email.show'));

    Notification::assertSentTo($user->fresh(), VerifyEmail::class);

    $this->get(route('onboarding.email.show'))
        ->assertSuccessful()
        ->assertSee($user->fresh()->maskedEmail())
        ->assertSee('Invia di nuovo il codice');
});

it('completes onboarding from the signed verification link', function () {
    $user = User::factory()->asUser()->pending()->create([
        'email' => 'verify-me@example.test',
        'email_verified_at' => null,
        'account_state' => UserStatus::PENDING,
        'profile_completed_at' => null,
    ]);
    $citizenshipCountry = WorldCountry::query()->firstOrFail();
    $verificationUrl = null;

    Notification::fake();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification, array $channels) use ($user, &$verificationUrl): bool {
        $verificationUrl = $notification->toMail($user)->actionUrl;

        return true;
    });

    expect($verificationUrl)->not->toBeNull();

    $this->get($verificationUrl)
        ->assertSuccessful()
        ->assertSee('Attiva il tuo account');

    $response = $this->post($verificationUrl, [
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'birth_date' => '1990-01-15',
        'birth_place' => 'Roma',
        'citizenship_country_id' => $citizenshipCountry->getKey(),
    ]);

    $response->assertRedirect(route('login'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->fresh()->account_state)->toBe(UserStatus::ACTIVE);
    expect($user->fresh()->birth_place)->toBe('Roma');
    expect($user->fresh()->citizenship_country_id)->toBe($citizenshipCountry->getKey());
});

it('requires only the password for non user roles during onboarding', function () {
    $user = User::factory()->pending()->create([
        'email' => 'teacher-verify@example.test',
        'email_verified_at' => null,
        'profile_completed_at' => null,
        'birth_date' => null,
        'birth_place' => null,
        'citizenship_country_id' => null,
    ]);
    $user->syncRoles(['teacher']);
    $verificationUrl = null;

    Notification::fake();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, &$verificationUrl): bool {
        $verificationUrl = $notification->toMail($user)->actionUrl;

        return true;
    });

    $this->get($verificationUrl)
        ->assertSuccessful()
        ->assertDontSee('Data di nascita')
        ->assertDontSee('Luogo di nascita')
        ->assertDontSee('Paese di cittadinanza');

    $response = $this->post($verificationUrl, [
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('login'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->fresh()->account_state)->toBe(UserStatus::ACTIVE);
    expect($user->fresh()->birth_date)->toBeNull();
    expect($user->fresh()->birth_place)->toBeNull();
    expect($user->fresh()->citizenship_country_id)->toBeNull();
});

it('offers password recovery for active accounts found by fiscal code', function () {
    $user = User::factory()->asUser()->create([
        'email' => 'active-user@example.test',
        'email_verified_at' => now(),
        'account_state' => UserStatus::ACTIVE,
        'profile_completed_at' => now(),
    ]);

    Notification::fake();

    $response = $this->post(route('onboarding.lookup'), [
        'fiscal_code' => $user->fiscal_code,
    ]);

    $response->assertRedirect(route('onboarding.email.show'));

    $this->get(route('onboarding.email.show'))
        ->assertSuccessful()
        ->assertSee('Invia recupero password');

    $this->post(route('onboarding.password-reset'))
        ->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('blocks platform access until a worker completes the profile', function () {
    $user = User::factory()->asUser()->create([
        'account_state' => UserStatus::ACTIVE,
        'profile_completed_at' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['active_role' => 'user'])
        ->get(route('user.dashboard'))
        ->assertRedirect(route('onboarding.profile.show'));
});
