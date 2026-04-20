<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwilioVideoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('app:twilio-jwt-diagnostic {--identity=sodexo:teacher:1 : Twilio identity da inserire nel token} {--room=diagnostic-room : Nome room da inserire nel grant video} {--name=Twilio Diagnostic : Friendly name da inserire nel token}')]
#[Description('Genera e decodifica un JWT Twilio in modo sicuro per diagnosticare issuer e subject')]
class TwilioJwtDiagnostic extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TwilioVideoService $twilioVideoService): int
    {
        $identity = (string) $this->option('identity');
        $roomName = (string) $this->option('room');
        $friendlyName = (string) $this->option('name');

        $user = new User([
            'full_name' => $friendlyName,
        ]);

        try {
            $jwt = $twilioVideoService->createAccessToken($user, $identity, $roomName);
            ['header' => $header, 'payload' => $payload] = $this->decodeJwt($jwt);
        } catch (\Throwable $exception) {
            $this->error('Twilio JWT diagnostic failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Twilio JWT diagnostic');
        $this->newLine();
        $this->line('account_sid_masked: '.$this->maskValue((string) config('services.twilio.account_sid')));
        $this->line('api_key_masked: '.$this->maskValue((string) config('services.twilio.api_key')));
        $this->line('identity: '.$identity);
        $this->line('room: '.$roomName);
        $this->line('jwt_header_alg: '.($header['alg'] ?? 'n/a'));
        $this->line('jwt_header_typ: '.($header['typ'] ?? 'n/a'));
        $this->line('token_issuer: '.($payload['iss'] ?? 'n/a'));
        $this->line('token_subject: '.($payload['sub'] ?? 'n/a'));
        $this->line('token_identity: '.data_get($payload, 'grants.identity', 'n/a'));
        $this->line('video_room: '.data_get($payload, 'grants.video.room', 'n/a'));
        $this->line('friendly_name: '.data_get($payload, 'grants.friendly_name', 'n/a'));
        $this->line('issued_at: '.($payload['iat'] ?? 'n/a'));
        $this->line('expires_at: '.($payload['exp'] ?? 'n/a'));

        return self::SUCCESS;
    }

    /**
     * @return array{header: array<string, mixed>, payload: array<string, mixed>}
     */
    private function decodeJwt(string $jwt): array
    {
        $segments = explode('.', $jwt);

        if (count($segments) !== 3) {
            throw new RuntimeException('The generated JWT does not contain 3 segments.');
        }

        return [
            'header' => $this->decodeJwtSegment($segments[0]),
            'payload' => $this->decodeJwtSegment($segments[1]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtSegment(string $segment): array
    {
        $normalized = strtr($segment, '-_', '+/');
        $paddingLength = strlen($normalized) % 4;

        if ($paddingLength > 0) {
            $normalized .= str_repeat('=', 4 - $paddingLength);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            throw new RuntimeException('Unable to base64 decode JWT segment.');
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function maskValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'missing';
        }

        return strlen($value) <= 8
            ? str_repeat('*', strlen($value))
            : substr($value, 0, 4).'...'.substr($value, -4);
    }
}
