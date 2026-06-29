<?php

it('sets a default s3 region for s3-compatible cloud storage', function () {
    expect(config('filesystems.disks.s3.region'))->toBe('auto');
});
