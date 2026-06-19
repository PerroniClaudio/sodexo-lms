<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

#[Signature('user:create {email?} {name?} {surname?} {fiscal_code?} {role=user} {--active : Crea utente gia attivo (per test/setup)} {--password= : Password personalizzata (solo con --active)} {--job-unit= : ID unita lavorativa} {--job-task= : ID mansione} {--job-title= : Alias legacy di --job-task} {--job-role= : ID ruolo} {--job-sector= : ID settore}')]
#[Description('Crea un nuovo utente e avvia il suo onboarding')]
class CreateUserCommand extends Command
{
    private const DEFAULT_ACTIVE_PASSWORD = 'Sodexo@Learning.26';

    public function handle(): int
    {
        $email = $this->argument('email');

        if ($email === null) {
            $email = $this->ask('Email utente (opzionale)');
        }

        $email = is_string($email) && trim($email) !== '' ? strtolower(trim($email)) : null;
        $name = $this->argument('name') ?? $this->ask('Nome');
        $surname = $this->argument('surname') ?? $this->ask('Cognome');
        $fiscalCode = $this->argument('fiscal_code') ?? $this->ask('Codice fiscale');
        $role = $this->argument('role') ?? $this->choice('Ruolo', ['superadmin', 'admin', 'teacher', 'tutor', 'user'], 4);
        $isActive = $this->option('active');

        $password = $isActive
            ? ($this->option('password') ?? self::DEFAULT_ACTIVE_PASSWORD)
            : Str::random(32);

        $this->info($isActive
            ? 'Modalita test/setup: utente creato gia attivo con password impostata.'
            : ($email !== null
                ? 'Modalita normale: verra inviata email di attivazione.'
                : 'Modalita onboarding: l\'utente usera il codice fiscale per iniziare il flusso.'));

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'surname' => $surname,
            'fiscal_code' => $fiscalCode,
            'role' => $role,
            'password' => $password,
        ], [
            'email' => ['nullable', 'email', Rule::unique('users', 'email')],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'fiscal_code' => ['required', 'string', 'size:16', 'unique:users,fiscal_code'],
            'role' => ['required', 'in:superadmin,admin,teacher,tutor,user'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            $this->error('Errore di validazione:');
            foreach ($validator->errors()->all() as $error) {
                $this->error(" - $error");
            }

            return Command::FAILURE;
        }

        try {
            DB::beginTransaction();

            $userData = [
                'email' => $email,
                'name' => $name,
                'surname' => $surname,
                'fiscal_code' => strtoupper($fiscalCode),
                'account_state' => $isActive ? UserStatus::ACTIVE : UserStatus::PENDING,
                'email_verified_at' => $isActive ? now() : null,
                'profile_completed_at' => $isActive ? now() : null,
                'password' => bcrypt($password),
            ];

            if ($jobUnitId = $this->option('job-unit')) {
                $userData['job_unit_id'] = $jobUnitId;
            }
            if ($jobTaskId = $this->option('job-task') ?: $this->option('job-title')) {
                $userData['job_task_id'] = $jobTaskId;
            }
            if ($jobRoleId = $this->option('job-role')) {
                $userData['job_role_id'] = $jobRoleId;
            }
            if ($jobSectorId = $this->option('job-sector')) {
                $userData['job_sector_id'] = $jobSectorId;
            }

            $user = User::create($userData);
            $user->assignRole($role);

            if (! $isActive && $user->email) {
                $user->sendEmailVerificationNotification();
            }

            DB::commit();

            $this->info('Utente creato con successo.');
            $this->newLine();
            $this->table(['Campo', 'Valore'], [
                ['ID', $user->id],
                ['Email', $user->email ?? 'Non impostata'],
                ['Nome completo', $user->full_name],
                ['Codice fiscale', $user->fiscal_code],
                ['Ruolo', $role],
                ['Stato', $user->account_state->label()],
            ]);
            $this->newLine();

            if ($isActive) {
                $this->info('Password impostata: '.$password);
            } elseif ($user->email) {
                $this->info('Email di attivazione inviata a: '.$user->email);
            } else {
                $this->info('Nessuna email impostata. L\'utente dovra usare il codice fiscale per avviare l\'onboarding.');
            }

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            DB::rollBack();
            $this->error('Errore durante la creazione dell\'utente: '.$throwable->getMessage());

            return Command::FAILURE;
        }
    }
}
