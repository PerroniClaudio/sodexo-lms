<?php

namespace App\Console\Commands;

use App\Services\TwilioApiCredentialService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:twilio-generate-api-credentials
    {account_sid : Twilio Account SID}
    {auth_token : Twilio Auth Token}
    {--name= : Friendly name for the generated API key}')]
#[Description('Genera una Twilio API Key one-shot e restituisce TWILIO_API_KEY e TWILIO_API_SECRET')]
class GenerateTwilioApiCredentialsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TwilioApiCredentialService $twilioApiCredentialService): int
    {
        $accountSid = (string) $this->argument('account_sid');
        $authToken = (string) $this->argument('auth_token');
        $friendlyName = (string) ($this->option('name') ?: 'sodexo-lms-'.now()->format('Ymd-His'));

        try {
            $credentials = $twilioApiCredentialService->create($accountSid, $authToken, $friendlyName);
        } catch (Throwable $exception) {
            $this->error('Twilio API credential generation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Twilio API credentials generated successfully.');
        $this->newLine();
        $this->line('TWILIO_API_KEY='.$credentials['api_key']);
        $this->line('TWILIO_API_SECRET='.$credentials['api_secret']);
        $this->line('TWILIO_API_KEY_FRIENDLY_NAME='.$credentials['friendly_name']);

        return self::SUCCESS;
    }
}
