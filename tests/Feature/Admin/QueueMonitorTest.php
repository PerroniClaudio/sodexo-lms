<?php

use App\Http\Middleware\EnsureDevelopmentEnvironment;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(EnsureDevelopmentEnvironment::class);
});

it('shows the queue monitor to superadmins', function () {
    actingAsRole('superadmin');

    DB::table('queue_worker_heartbeats')->updateOrInsert(
        ['worker_id' => 'test-worker'],
        [
            'connection' => 'database',
            'queue' => 'default',
            'last_seen_at' => now(),
        ],
    );

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\ImportUsersJob']),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    $this->get(route('admin.development-tools.queue.index'))
        ->assertOk()
        ->assertSeeText('Worker attivi')
        ->assertSeeText('test-worker')
        ->assertSeeText('ImportUsersJob');
});

it('does not show the queue monitor to admins', function () {
    actingAsRole('admin');

    $this->withSession(['active_role' => 'admin'])
        ->get(route('admin.development-tools.queue.index'))
        ->assertRedirect(route('dashboard'));
});
