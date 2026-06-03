<?php

namespace App\Observers;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserObserver
{
    public function creating(User $user): void
    {
        if (empty($user->password)) {
            $user->password = Hash::make(Str::random(12));
        }

        $this->validateJobFields($user);
    }

    public function created(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }

    public function updating(User $user): void
    {
        if ($user->isDirty(['job_unit_id', 'job_role_id', 'job_sector_id', 'employment_start_date', 'employment_end_date'])) {
            $this->validateJobFields($user);
        }
    }

    protected function validateJobFields(User $user): void
    {
        $isNormalUser = $user->account_state === UserStatus::PENDING
            || ($user->account_state === UserStatus::ONBOARDING && ! $user->email_verified_at);

        if (! $isNormalUser) {
            return;
        }

        $errors = [];

        if (! $user->job_unit_id) {
            $errors['job_unit_id'] = __('L\'unità lavorativa è obbligatoria per gli utenti.');
        }

        if (! $user->job_role_id) {
            $errors['job_role_id'] = __('Il ruolo lavorativo è obbligatorio per gli utenti.');
        }

        if (! $user->job_sector_id) {
            $errors['job_sector_id'] = __('Il settore è obbligatorio per gli utenti.');
        }

        if (! $user->employment_start_date) {
            $errors['employment_start_date'] = __('La data di assunzione è obbligatoria per gli utenti.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
