<?php

namespace App\Services;

use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;
use Twilio\Rest\Client;

class TwilioVideoService
{
    public function __construct(
        private readonly ?string $accountSid = null,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null,
        private readonly ?string $authToken = null,
    ) {}

    public function createRoom(Module $module): array
    {
        $roomName = sprintf('live-module-%s-%s', $module->getKey(), Str::uuid());

        try {
            $room = $this->client()->video->v1->rooms->create([
                'type' => config('services.twilio.video.room_type', 'group'),
                'uniqueName' => $roomName,
                'maxParticipantDuration' => 86_400,
            ]);
        } catch (Throwable $exception) {
            Log::error('Twilio live stream room creation failed.', [
                'module_id' => $module->getKey(),
                'room_name' => $roomName,
                'room_type' => config('services.twilio.video.room_type', 'group'),
                ...$this->diagnosticsContext(),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Twilio live stream room created.', [
            'module_id' => $module->getKey(),
            'room_name' => $room->uniqueName,
            'room_sid' => $this->maskValue($room->sid),
            'room_type' => config('services.twilio.video.room_type', 'group'),
            ...$this->diagnosticsContext(),
        ]);

        return [
            'sid' => $room->sid,
            'name' => $room->uniqueName,
        ];
    }

    public function completeRoom(string $roomSidOrName): void
    {
        try {
            $this->client()->video->v1->rooms($roomSidOrName)->update('completed');
        } catch (Throwable $exception) {
            Log::error('Twilio live stream room completion failed.', [
                'room_reference' => $this->maskValue($roomSidOrName),
                ...$this->diagnosticsContext(),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function createAccessToken(User $user, string $identity, string $roomName): string
    {
        $accountSid = $this->requireValue($this->accountSid ?? config('services.twilio.account_sid'), 'services.twilio.account_sid');
        $apiKey = $this->requireValue($this->apiKey ?? config('services.twilio.api_key'), 'services.twilio.api_key');
        $apiSecret = $this->requireValue($this->apiSecret ?? config('services.twilio.api_secret'), 'services.twilio.api_secret');

        try {
            $token = new AccessToken(
                $accountSid,
                $apiKey,
                $apiSecret,
                (int) config('services.twilio.video.token_ttl', 21_600),
                $identity,
            );

            $token->addGrant((new VideoGrant)->setRoom($roomName));
            $token->addClaim('friendly_name', $user->full_name);

            $jwt = $token->toJWT();
        } catch (Throwable $exception) {
            Log::error('Twilio access token generation failed.', [
                'user_id' => $user->getKey(),
                'identity' => $identity,
                'room_name' => $roomName,
                ...$this->diagnosticsContext($accountSid, $apiKey),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Twilio access token generated.', [
            'user_id' => $user->getKey(),
            'identity' => $identity,
            'room_name' => $roomName,
            ...$this->diagnosticsContext($accountSid, $apiKey),
            'token_ttl' => (int) config('services.twilio.video.token_ttl', 21_600),
            'friendly_name_present' => $user->full_name !== null && $user->full_name !== '',
        ]);

        return $jwt;
    }

    public static function identityFor(string $role, int $userId): string
    {
        return sprintf('sodexo:%s:%s', $role, $userId);
    }

    private function client(): Client
    {
        return new Client(
            $this->requireValue($this->accountSid ?? config('services.twilio.account_sid'), 'services.twilio.account_sid'),
            $this->requireValue($this->authToken ?? config('services.twilio.auth_token'), 'services.twilio.auth_token'),
        );
    }

    private function requireValue(?string $value, string $configKey): string
    {
        if ($value === null || $value === '') {
            throw new RuntimeException(sprintf('Missing Twilio configuration value [%s].', $configKey));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnosticsContext(?string $accountSid = null, ?string $apiKey = null): array
    {
        $resolvedAccountSid = $accountSid ?? $this->accountSid ?? config('services.twilio.account_sid');
        $resolvedApiKey = $apiKey ?? $this->apiKey ?? config('services.twilio.api_key');

        return [
            'twilio_account_sid_masked' => $this->maskValue($resolvedAccountSid),
            'twilio_api_key_masked' => $this->maskValue($resolvedApiKey),
        ];
    }

    private function maskValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strlen($value) <= 8
            ? str_repeat('*', strlen($value))
            : substr($value, 0, 4).'...'.substr($value, -4);
    }
}
