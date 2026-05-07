<?php

namespace App\Services;

use RuntimeException;
use Twilio\Rest\Client;

class TwilioApiCredentialService
{
    /**
     * @return array{api_key: string, api_secret: string, friendly_name: string}
     */
    public function create(string $accountSid, string $authToken, string $friendlyName): array
    {
        $newKey = (new Client($accountSid, $authToken))
            ->api
            ->v2010
            ->account
            ->newKeys
            ->create([
                'friendlyName' => $friendlyName,
            ]);

        if (! is_string($newKey->sid) || $newKey->sid === '') {
            throw new RuntimeException('Twilio did not return a valid API key SID.');
        }

        if (! is_string($newKey->secret) || $newKey->secret === '') {
            throw new RuntimeException('Twilio did not return a valid API key secret.');
        }

        return [
            'api_key' => $newKey->sid,
            'api_secret' => $newKey->secret,
            'friendly_name' => $friendlyName,
        ];
    }
}
