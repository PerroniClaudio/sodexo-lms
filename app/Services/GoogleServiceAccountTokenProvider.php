<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleServiceAccountTokenProvider
{
    private const CLOUD_PLATFORM_SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    public function cloudPlatformAccessToken(): string
    {
        $credentials = $this->credentials();
        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $privateKey = (string) ($credentials['private_key'] ?? '');
        $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Google service account credentials are incomplete.');
        }

        return Cache::remember('google-service-account-access-token-'.md5($clientEmail), now()->addMinutes(45), function () use ($clientEmail, $privateKey, $tokenUri): string {
            $issuedAt = now()->timestamp;
            $expiresAt = $issuedAt + 3600;

            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $payload = $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => self::CLOUD_PLATFORM_SCOPE,
                'aud' => $tokenUri,
                'exp' => $expiresAt,
                'iat' => $issuedAt,
            ], JSON_THROW_ON_ERROR));

            $signatureInput = $header.'.'.$payload;

            if (! openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign Google service account assertion.');
            }

            $assertion = $signatureInput.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()
                ->connectTimeout((int) config('services.google.timeout.connect', 5))
                ->timeout((int) config('services.google.timeout.request', 30))
                ->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ])
                ->throw()
                ->json();

            $accessToken = (string) ($response['access_token'] ?? '');

            if ($accessToken === '') {
                throw new RuntimeException('Google OAuth token response is missing access_token.');
            }

            return $accessToken;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $encodedCredentials = $this->requireValue(
            config('services.google.service_account_json_base64'),
            'services.google.service_account_json_base64'
        );

        $decodedCredentials = base64_decode($encodedCredentials, true);

        if ($decodedCredentials === false) {
            throw new RuntimeException('Google service account credentials are not valid base64.');
        }

        /** @var array<string, mixed> $credentials */
        $credentials = json_decode($decodedCredentials, true, flags: JSON_THROW_ON_ERROR);

        return $credentials;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function requireValue(mixed $value, string $configKey): string
    {
        if (! is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Missing configuration value [%s].', $configKey));
        }

        return $value;
    }
}
