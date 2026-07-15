<?php

use App\Models\JobSector;
use App\Models\JobTask;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JobDataSeeder;
use Database\Seeders\NaceAtecoSeeder;

it('does not seed default sectors or tasks when disabled', function () {
    config()->set('app.use_default_sectors', false);

    $this->seed(JobDataSeeder::class);

    expect(JobSector::query()->count())->toBe(0)
        ->and(JobTask::query()->count())->toBe(0);
});

it('does not run the ATECO seeder when default sectors are disabled', function () {
    config()->set('app.use_default_sectors', false);

    $seeder = new class extends DatabaseSeeder
    {
        public array $calledSeeders = [];

        public function call($class, $silent = false, array $parameters = []): void
        {
            $this->calledSeeders = $class;
        }
    };

    $seeder->run();

    expect($seeder->calledSeeders)->not->toContain(NaceAtecoSeeder::class);
});

it('seeds default sectors and tasks when enabled', function () {
    config()->set('app.use_default_sectors', true);

    $this->seed(JobDataSeeder::class);

    expect(JobSector::query()->count())->toBeGreaterThan(0)
        ->and(JobTask::query()->count())->toBeGreaterThan(0);
});
