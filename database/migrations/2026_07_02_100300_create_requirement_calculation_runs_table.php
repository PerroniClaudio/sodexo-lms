<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirement_calculation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->string('status', 20);
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['scope', 'status']);
            $table->index('finished_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_calculation_runs');
    }
};
