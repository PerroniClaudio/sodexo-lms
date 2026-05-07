<?php

use App\Services\TwilioApiCredentialService;

test('it prints generated twilio api credentials', function () {
    $service = Mockery::mock(TwilioApiCredentialService::class);
    $service->shouldReceive('create')
        ->once()
        ->with('AC123', 'auth-token-123', 'live-platform')
        ->andReturn([
            'api_key' => 'SK123',
            'api_secret' => 'secret123',
            'friendly_name' => 'live-platform',
        ]);

    $this->app->instance(TwilioApiCredentialService::class, $service);

    $this->artisan('app:twilio-generate-api-credentials', [
        'account_sid' => 'AC123',
        'auth_token' => 'auth-token-123',
        '--name' => 'live-platform',
    ])
        ->expectsOutput('Twilio API credentials generated successfully.')
        ->expectsOutput('TWILIO_API_KEY=SK123')
        ->expectsOutput('TWILIO_API_SECRET=secret123')
        ->expectsOutput('TWILIO_API_KEY_FRIENDLY_NAME=live-platform')
        ->assertSuccessful();
});

test('it returns failure when twilio generation fails', function () {
    $service = Mockery::mock(TwilioApiCredentialService::class);
    $service->shouldReceive('create')
        ->once()
        ->with('AC123', 'bad-token', Mockery::type('string'))
        ->andThrow(new RuntimeException('Unauthorized'));

    $this->app->instance(TwilioApiCredentialService::class, $service);

    $this->artisan('app:twilio-generate-api-credentials', [
        'account_sid' => 'AC123',
        'auth_token' => 'bad-token',
    ])
        ->expectsOutputToContain('Twilio API credential generation failed: Unauthorized')
        ->assertFailed();
});
