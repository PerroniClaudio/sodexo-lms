<?php

namespace App\Observers;

use App\Enums\UserStatus;
use App\Models\JobUnit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        // Genera password randomica se non presente
        if (empty($user->password)) {
            $user->password = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12));
        }

        // Valida campi job obbligatori per utenti normali (non admin/test)
        $this->validateJobFields($user);
    }
    
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Invia mail di verifica se non già verificato
        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        // Valida campi job se modificati e utente è normale
        if ($user->isDirty(['job_unit_id', 'job_title_id', 'job_role_id', 'job_sector_id'])) {
            $this->validateJobFields($user);
        }
    }

    /**
     * Valida che i campi job siano presenti per utenti normali (role: user)
     *
     * Logica: Gli utenti PENDING (che devono fare onboarding) sono utenti normali
     * e richiedono i dati job. Gli utenti ACTIVE creati direttamente (admin/test)
     * sono esentati da questo requisito.
     */
    protected function validateJobFields(User $user): void
    {
        // Utenti admin/test vengono creati già ACTIVE con email_verified_at
        // Utenti normali vengono creati PENDING senza email_verified_at
        $isNormalUser = $user->account_state === UserStatus::PENDING
                     || ($user->account_state === UserStatus::ONBOARDING && ! $user->email_verified_at);

        if ($isNormalUser) {
            $errors = [];

            if (! $user->job_unit_id) {
                $errors['job_unit_id'] = __('L\'unità lavorativa è obbligatoria per gli utenti.');
            }

            if (! $user->job_title_id) {
                $errors['job_title_id'] = __('La mansione è obbligatoria per gli utenti.');
            }

            if (! $user->job_role_id) {
                $errors['job_role_id'] = __('Il ruolo lavorativo è obbligatorio per gli utenti.');
            }

            if (! $user->job_sector_id) {
                $errors['job_sector_id'] = __('Il settore è obbligatorio per gli utenti.');
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }
        }
    }
}
