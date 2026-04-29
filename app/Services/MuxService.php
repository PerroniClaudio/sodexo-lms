<?php

namespace App\Services;

use GuzzleHttp\Client;

class MuxService
{    protected $client;

    protected $tokenId;

    protected $tokenSecret;

    protected $signingKeyId;

    protected $signingPrivateKey;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.mux.com/video/v1/',
            'auth' => [
                config('services.mux.token_id'),
                config('services.mux.token_secret'),
            ],
        ]);
        $this->tokenId = config('services.mux.token_id');
        $this->tokenSecret = config('services.mux.token_secret');
            $this->signingKeyId = config('services.mux.signing_key_id');
            // Carica la chiave privata dal file mux-signing-key.pem nella root del progetto
            $pemPath = base_path('mux-signing-key.pem');
            if (file_exists($pemPath)) {
                $key = file_get_contents($pemPath);
            } else {
                $key = config('services.mux.signing_private_key');
            }
            // Se la chiave è base64, decodificala
            if ($key && strpos($key, '-----BEGIN') === false) {
                $key = base64_decode($key);
            }
            $this->signingPrivateKey = $key;
    }
    /**
     * Genera un signed URL per la thumbnail Mux
     */
    public function getSignedThumbnailUrl(string $playbackId, int $expiresInSeconds = 3600): string
    {
        $baseUrl = "https://image.mux.com/{$playbackId}/thumbnail.jpg";
        $expiration = time() + $expiresInSeconds;
        $token = $this->generateJwtToken($playbackId, $expiration, 't'); // aud = 't' per thumbnail
        return $baseUrl.'?token='.$token;
    }
    /**
     * Recupera asset_id e playback_id da upload_id Mux
     * Restituisce array: ['asset_id' => ..., 'playback_id' => ...]
     */
    public function getUploadData(string $uploadId): array
    {
        try {
            $response = $this->client->get("uploads/{$uploadId}");
            $data = json_decode($response->getBody()->getContents(), true);
            $assetId = $data['data']['asset_id'] ?? null;
            $playbackId = null;
            if ($assetId) {
                // Recupera anche il playback_id dall'asset
                $assetResponse = $this->client->get("assets/{$assetId}");
                $assetData = json_decode($assetResponse->getBody()->getContents(), true);
                $playbacks = $assetData['data']['playback_ids'] ?? [];
                if (!empty($playbacks)) {
                    $playbackId = $playbacks[0]['id'] ?? null;
                }
            }
            return [
                'asset_id' => $assetId,
                'playback_id' => $playbackId,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
    /**
     * Recupera l'asset_id associato a un upload_id Mux
     */
    public function getAssetIdFromUpload(string $uploadId): ?string
    {
        try {
            $response = $this->client->get("uploads/{$uploadId}");
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data']['asset_id'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Crea un direct upload Mux e restituisce l'URL e l'upload_id
     */
    public function createDirectUpload(string $filename): array
    {
        $response = $this->client->post('uploads', [
            'json' => [
                'new_asset_settings' => [
                    'playback_policy' => ['signed'],
                ],
                'cors_origin' => '*',
                'timeout' => 3600,
            ],
        ]);
        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'url' => $data['data']['url'] ?? null,
            'upload_id' => $data['data']['id'] ?? null,
        ];
    }

    /**
     * Genera un signed playback URL per un asset Mux
     */
    public function getSignedPlaybackUrl(string $playbackId, int $expiresInSeconds = 3600): string
    {
        $baseUrl = "https://stream.mux.com/{$playbackId}.m3u8";
        $expiration = time() + $expiresInSeconds;
        $token = $this->generateJwtToken($playbackId, $expiration, 'v'); // aud = 'v' per video playback

        return $baseUrl.'?token='.$token;
    }

    /**
     * Genera JWT per signed playback
     */
    public function generateJwtToken(string $playbackId, int $expiration, string $audience = 'v'): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'RS256',
            'kid' => $this->signingKeyId,
        ];
        $payload = [
            'exp' => $expiration,
            'sub' => $playbackId,
            'aud' => $audience,
        ];
        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];
        $signingInput = implode('.', $segments);
        $signature = '';
        openssl_sign($signingInput, $signature, $this->signingPrivateKey, 'sha256');
        $segments[] = $this->base64UrlEncode($signature);
        
        $jwt = implode('.', $segments);
        // Debug log
        \Log::debug('[MUX JWT]', [
            'playback_id' => $playbackId,
            'header' => $header,
            'payload' => $payload,
            'jwt' => $jwt,
            'key_snippet' => is_string($this->signingPrivateKey) ? substr($this->signingPrivateKey, 0, 40) : null,
        ]);
            return $jwt;

        return implode('.', $segments);
    }

    protected function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    /**
     * Recupera lo stato di un asset Mux dato l'asset_id
     */
    public function getAssetStatus(string $assetId): ?string
    {
        try {
            $response = $this->client->get("assets/{$assetId}");
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data']['status'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Restituisce la durata (in secondi) di un asset Mux dato l'asset_id
     */
    public function getAssetDuration(string $assetId): ?float
    {
        try {
            $response = $this->client->get("assets/{$assetId}");
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data']['duration'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
