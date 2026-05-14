<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_teacher_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'module_id', 'deleted_at']);
            $table->index(['module_id', 'deleted_at']);
        });

        $assignments = DB::table('course_teacher_enrollments as teacher_enrollments')
            ->join('modules', 'modules.belongsTo', '=', 'teacher_enrollments.course_id')
            ->where('modules.type', 'live')
            ->whereNull('modules.deleted_at')
            ->whereNull('teacher_enrollments.deleted_at')
            ->select([
                'teacher_enrollments.user_id',
                DB::raw('modules.id as module_id'),
                'teacher_enrollments.assigned_at',
                'teacher_enrollments.created_at',
                'teacher_enrollments.updated_at',
            ])
            ->get()
            ->map(fn (object $assignment): array => [
                'user_id' => $assignment->user_id,
                'module_id' => $assignment->module_id,
                'assigned_at' => $assignment->assigned_at,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ])
            ->all();

        if ($assignments !== []) {
            DB::table('module_teacher_enrollments')->insert($assignments);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_teacher_enrollments');
    }
};
