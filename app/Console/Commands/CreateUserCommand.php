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

#[Signature('user:create {email?} {name?} {surname?} {fiscal_code?} {role=user} {--active : Crea utente già attivo (per test/setup)} {--password= : Password personalizzata (solo con --active)} {--job-unit= : ID Unità lavorativa} {--job-title= : ID Mansione} {--job-role= : ID Ruolo} {--job-sector= : ID Settore}')]
#[Description('Crea un nuovo utente e invia email di attivazione')]
class CreateUserCommand extends Command
{
    /**
     * Password di default per utenti di test/setup (usata solo con --active)
     */
    private const DEFAULT_ACTIVE_PASSWORD = 'Sodexo@Learning.26';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Email utente');
        $name = $this->argument('name') ?? $this->ask('Nome');
        $surname = $this->argument('surname') ?? $this->ask('Cognome');
        $fiscalCode = $this->argument('fiscal_code') ?? $this->ask('Codice fiscale');
        $role = $this->argument('role') ?? $this->choice(
            'Ruolo',
            ['superadmin', 'admin', 'docente', 'tutor', 'user'],
            4
        );

        $isActive = $this->option('active');

        // Gestione password
        if ($isActive) {
            // Utente attivo (test/setup): usa password specificata o quella di default
            $password = $this->option('password') ?? self::DEFAULT_ACTIVE_PASSWORD;
            $this->info('🔧 Modalità test/setup: utente sarà creato già attivo con password impostata.');
        } else {
            // Utente normale: password casuale temporanea (non verrà mai usata)
            $password = Str::random(32);
            $this->info('📧 Modalità normale: verrà inviata email di attivazione all\'utente.');
        }

        // Validazione
        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'surname' => $surname,
            'fiscal_code' => $fiscalCode,
            'role' => $role,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'fiscal_code' => ['required', 'string', 'size:16', 'unique:users,fiscal_code'],
            'role' => ['required', 'in:superadmin,admin,docente,tutor,user'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            $this->error('Errore di validazione:');
            foreach ($validator->errors()->all() as $error) {
                $this->error("  • $error");
            }

            return Command::FAILURE;
        }

        try {
            DB::beginTransaction();

            // Prepara dati utente
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

            // Aggiungi campi job se specificati
            if ($jobUnitId = $this->option('job-unit')) {
                $userData['job_unit_id'] = $jobUnitId;
            }
            if ($jobTitleId = $this->option('job-title')) {
                $userData['job_title_id'] = $jobTitleId;
            }
            if ($jobRoleId = $this->option('job-role')) {
                $userData['job_role_id'] = $jobRoleId;
            }
            if ($jobSectorId = $this->option('job-sector')) {
                $userData['job_sector_id'] = $jobSectorId;
            }

            // Crea utente
            $user = User::create($userData);

            // Assegna ruolo
            $user->assignRole($role);

            // Invia email di verifica/attivazione solo se utente non è già attivo
            if (! $isActive) {
                $user->sendEmailVerificationNotification();
            }

            DB::commit();

            $this->info('✓ Utente creato con successo!');
            $this->line('');
            $this->table(
                ['Campo', 'Valore'],
                [
                    ['ID', $user->id],
                    ['Email', $user->email],
                    ['Nome Completo', $user->full_name],
                    ['Codice Fiscale', $user->fiscal_code],
                    ['Ruolo', $role],
                    ['Stato', $user->account_state->label()],
                ]
            );
            $this->line('');

            if ($isActive) {
                $this->info('🔐 Password impostata: '.$password);
                $this->warn('⚠ Questo utente è già attivo e può effettuare subito il login.');
            } else {
                $this->info('✉ Email di attivazione inviata a: '.$user->email);
                $this->line('L\'utente dovrà verificare l\'email e impostare la password per accedere.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Errore durante la creazione dell\'utente: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
