<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('scorm_tracking')
            ->whereNull('course_user_id')
            ->whereNotNull('session_id')
            ->orderBy('id')
            ->chunkById(500, function ($trackings): void {
                $sessionIds = collect($trackings)
                    ->pluck('session_id')
                    ->filter()
                    ->unique()
                    ->values();

                if ($sessionIds->isEmpty()) {
                    return;
                }

                $courseUserIdsBySessionId = DB::table('scorm_sessions')
                    ->whereIn('session_id', $sessionIds->all())
                    ->pluck('course_user_id', 'session_id');

                foreach ($trackings as $tracking) {
                    $courseUserId = $courseUserIdsBySessionId[$tracking->session_id] ?? null;

                    if ($courseUserId === null) {
                        continue;
                    }

                    DB::table('scorm_tracking')
                        ->where('id', $tracking->id)
                        ->update(['course_user_id' => $courseUserId]);
                }
            });
    }

    public function down(): void
    {
        DB::table('scorm_tracking')->update(['course_user_id' => null]);
    }
};
