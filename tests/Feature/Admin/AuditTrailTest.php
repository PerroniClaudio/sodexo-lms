<?php

use App\Jobs\ArchiveAuditEvents;
use App\Jobs\GenerateAuditExport;
use App\Models\AuditArchive;
use App\Models\AuditEvent;
use App\Models\Course;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('records only permitted model changes with a correlation id', function () {
    $admin = actingAsRole('superadmin');
    $course = Course::factory()->create(['title' => 'Prima versione']);

    $before = $course->getAttributes();
    $course->update(['title' => 'Seconda versione']);
    app(AuditTrail::class)->recordModel('updated', $course->fresh(), $before);

    $event = AuditEvent::query()->sole();

    expect($event->actor_user_id)->toBe($admin->getKey())
        ->and($event->changes['title'])->toBe(['old' => 'Prima versione', 'new' => 'Seconda versione'])
        ->and($event->changes)->not->toHaveKey('updated_at')
        ->and($event->correlation_id)->not->toBeNull();
});

it('allows only superadmins to view and queue audit exports', function () {
    Queue::fake();
    actingAsRole('superadmin');
    AuditEvent::query()->create(['occurred_at' => now(), 'origin' => 'admin_ui', 'action' => 'updated', 'subject_type' => 'Course', 'subject_id' => 10, 'subject_label' => 'Corso']);

    $this->get(route('admin.audit-events.index'))->assertOk()->assertSeeText('Audit amministrativo')->assertSeeText('Corso');
    $this->post(route('admin.audit-events.exports.store'), ['subject_type' => 'Course'])
        ->assertRedirect(route('admin.audit-events.index'));

    Queue::assertPushed(GenerateAuditExport::class);
});

it('does not allow admins to view the administrative audit trail', function () {
    actingAsRole('admin');

    $this->get(route('admin.audit-events.index'))->assertRedirect(route('dashboard'));
});

it('archives a completed monthly period once', function () {
    Storage::fake('audit');
    $date = now()->subMonths(25)->startOfMonth()->addDay();
    AuditEvent::query()->create(['occurred_at' => $date, 'origin' => 'admin_ui', 'action' => 'updated', 'subject_type' => 'Course', 'subject_id' => 10, 'subject_label' => 'Corso']);

    app(ArchiveAuditEvents::class)->handle();

    expect(AuditEvent::query()->count())->toBe(0);
    Storage::disk('audit')->assertExists('audit-archives/'.$date->format('Y/m').'.jsonl.gz');

    app(ArchiveAuditEvents::class)->handle();
    expect(AuditArchive::query()->count())->toBe(1);
});
