<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importazioni', function (Blueprint $table) {
            $table->id();
            $table->string('import_type')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->enum('status', ['pending', 'progress', 'finished', 'failed'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->string('file_path');
            $table->timestamps();

            $table->index(['import_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importazioni');
    }
};
