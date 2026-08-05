import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../../resources/js/admin-module-video.js', import.meta.url), 'utf8');

test('uses the shared video uploader and refreshes the module library after upload', () => {
    assert.doesNotMatch(source, /uploadForm\.onsubmit/);
    assert.match(source, /window\.refreshVideoSelect = function\(\)/);
});

test('does not send the video file through Laravel before uploading to Mux', async () => {
    const uploadForm = await readFile(new URL('../../resources/views/components/admin/videos/upload-form.blade.php', import.meta.url), 'utf8');

    assert.doesNotMatch(uploadForm, /formData\.append\('video_file'/);
    assert.match(uploadForm, /formData\.append\('video_filename'/);
});

test('places the compact modules column after the active column', async () => {
    const videoTable = await readFile(new URL('../../resources/views/components/admin/module/video-table.blade.php', import.meta.url), 'utf8');

    assert.match(videoTable, /data-sort="status">\{\{ __\('Attivo'\) \}\}<\/th>\s*<th[^>]+data-sort="modules_count">\{\{ __\('Moduli'\) \}\}/);
});
