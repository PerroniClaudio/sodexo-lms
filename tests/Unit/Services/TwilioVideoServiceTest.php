<?php

use App\Models\User;
use App\Services\TwilioVideoService;

it('uses Ireland for video media while keeping the Video API on its supported endpoint', function () {
    config()->set([
        'services.twilio.account_sid' => 'AC'.str_repeat('1', 32),
        'services.twilio.api_key' => 'SK'.str_repeat('2', 32),
        'services.twilio.api_secret' => str_repeat('3', 32),
        'services.twilio.video.media_region' => 'ie1',
    ]);

    $service = app(TwilioVideoService::class);
    $clientMethod = new ReflectionMethod($service, 'client');
    $client = $clientMethod->invoke($service);

    $service->createAccessToken(new User(['full_name' => 'Test User']), 'sodexo:teacher:1', 'test-room');

    expect($client->getAccountSid())->toBe('AC'.str_repeat('1', 32))
        ->and($client->getUsername())->toBe('SK'.str_repeat('2', 32))
        ->and($client->buildUri('https://video.twilio.com/v1/Rooms'))->toBe('https://video.twilio.com/v1/Rooms')
        ->and(config('services.twilio.video.media_region'))->toBe('ie1');
});
