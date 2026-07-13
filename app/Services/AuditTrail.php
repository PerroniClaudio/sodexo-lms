<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class AuditTrail
{
    private const EXCLUDED_ATTRIBUTES = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'updated_at', 'created_at'];

    public function recordModel(string $action, Model $subject, array $before = [], array $metadata = []): AuditEvent
    {
        $after = $subject->getAttributes();

        return $this->record(
            action: $action,
            subjectType: class_basename($subject),
            subjectId: $subject->getKey(),
            subjectLabel: $this->labelFor($subject),
            changes: $this->changes($before, $after),
            metadata: $metadata,
        );
    }

    public function record(string $action, string $subjectType, ?int $subjectId = null, ?string $subjectLabel = null, array $changes = [], array $metadata = []): AuditEvent
    {
        /** @var Request|null $request */
        $request = app()->bound('request') ? request() : null;
        /** @var User|null $actor */
        $actor = $request?->user() ?? auth()->user();

        return AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_user_id' => $actor?->getKey(),
            'actor_label' => $actor ? trim($actor->name.' '.$actor->surname) : null,
            'company_division_id' => $this->activeCompanyDivisionId($request, $actor),
            'origin' => Context::get('audit_origin', 'admin_ui'),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'correlation_id' => Context::get('audit_correlation_id', (string) Str::uuid()),
            'changes' => $changes === [] ? null : $changes,
            'metadata' => Arr::except($metadata, ['password', 'token', 'secret']),
        ]);
    }

    public function changes(array $before, array $after): array
    {
        $changes = [];

        foreach (array_diff(array_unique(array_merge(array_keys($before), array_keys($after))), self::EXCLUDED_ATTRIBUTES) as $attribute) {
            $old = $before[$attribute] ?? null;
            $new = $after[$attribute] ?? null;

            if ($old !== $new) {
                $changes[$attribute] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    private function activeCompanyDivisionId(?Request $request, ?User $actor): ?int
    {
        if ($actor?->hasRole('superadmin')) {
            $divisionId = $request?->hasSession()
                ? $request->session()->get('active_company_division_id')
                : null;

            return $divisionId === null ? null : (int) $divisionId;
        }

        return $actor?->company_division_id;
    }

    private function labelFor(Model $subject): string
    {
        return (string) ($subject->getAttribute('title') ?? $subject->getAttribute('name') ?? $subject->getAttribute('email') ?? '#'.$subject->getKey());
    }
}
