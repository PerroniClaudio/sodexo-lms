<?php

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SuspendExpiredUsersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('suspend-expired-users'))
                ->releaseAfter(60)
                ->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        User::query()
            ->whereHas('roles', fn (Builder $builder): Builder => $builder->where('name', 'user'))
            ->whereNotNull('employment_end_date')
            ->whereDate('employment_end_date', '<', today())
            ->where('account_state', '!=', UserStatus::SUSPENDED->value)
            ->update([
                'account_state' => UserStatus::SUSPENDED,
                'updated_at' => now(),
            ]);
    }
}
