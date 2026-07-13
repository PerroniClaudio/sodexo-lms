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
        Schema::create('audit_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->json('filters');
            $table->string('output_disk')->nullable();
            $table->string('output_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_exports');
    }
};
