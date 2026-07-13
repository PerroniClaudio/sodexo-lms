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
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable();
            $table->foreignId('company_division_id')->nullable()->constrained('company_divisions')->nullOnDelete();
            $table->string('origin', 32);
            $table->string('action', 64);
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_division_id', 'occurred_at'], 'audit_events_division_occurred_index');
            $table->index(['actor_user_id', 'occurred_at'], 'audit_events_actor_occurred_index');
            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'audit_events_subject_occurred_index');
            $table->index(['action', 'occurred_at'], 'audit_events_action_occurred_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
