<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Autenticazione e sicurezza
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('account_type')->default('user'); // superadmin, admin, docente, tutor, user
            $table->string('account_state')->default('pending'); // pending, onboarding, active, update_required, suspended
            $table->timestamp('datapsw')->nullable();
            $table->timestamp('data_richiesta_mail')->nullable();
            $table->rememberToken();

            // Onboarding e completamento profilo
            $table->timestamp('profile_completed_at')->nullable();
            $table->timestamp('last_data_update_request')->nullable();
            $table->string('onboarding_step')->nullable(); // password_setup, profile_completion

            // Dati anagrafici (obbligatori all'import)
            $table->string('name');
            $table->string('surname');
            $table->string('fiscal_code', 16)->unique();

            // Dati anagrafici opzionali
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('gender', 1)->nullable();
            $table->string('phone_prefix', 5)->nullable()->default('+39'); // Prefisso internazionale
            $table->string('phone', 20)->nullable();

            // Indirizzo di residenza (facoltativo)
            $table->string('nation', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('province', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();

            // Job Unit e dati geografici derivati (opzionali)
            $table->foreignId('job_unit_id')->nullable()->constrained('job_units');
            $table->string('job_country', 2)->nullable();
            $table->string('job_region')->nullable();
            $table->string('job_province', 2)->nullable();

            // Relazioni job (tutti opzionali)
            $table->foreignId('job_category_id')->nullable()->constrained('job_categories');
            $table->foreignId('job_level_id')->nullable()->constrained('job_levels');
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles');
            $table->foreignId('job_role_id')->nullable()->constrained('job_roles');
            $table->foreignId('job_sector_id')->nullable()->constrained('job_sectors');

            // Flag straniero/immigrato
            $table->boolean('is_foreigner_or_immigrant')->default(false);

            // Note aggiuntive
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indici per performance
            $table->index('account_state');
            $table->index(['job_sector_id', 'job_role_id']);
            $table->index(['job_country', 'job_region', 'job_province']);
            $table->index('is_foreigner_or_immigrant');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
