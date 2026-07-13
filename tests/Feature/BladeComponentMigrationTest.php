<?php

use Illuminate\Support\Facades\File;

test('blade views use components instead of includes', function () {
    foreach (File::allFiles(resource_path('views')) as $view) {
        expect($view->getContents())->not->toContain('@include');
    }
});
