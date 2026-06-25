import test from 'node:test';
import assert from 'node:assert/strict';

import { resolveVideoInterruptionReason } from '../../resources/js/modules/module-video.js';

test('returns muted interruption before focus checks', () => {
    assert.equal(
        resolveVideoInterruptionReason({
            muted: true,
            visibilityState: 'visible',
            hasWindowFocus: true,
        }),
        'muted',
    );
});

test('returns hidden interruption when document becomes hidden', () => {
    assert.equal(
        resolveVideoInterruptionReason({
            visibilityState: 'hidden',
            hasWindowFocus: true,
        }),
        'hidden',
    );
});

test('returns blur interruption when page loses focus but stays visible', () => {
    assert.equal(
        resolveVideoInterruptionReason({
            visibilityState: 'visible',
            hasWindowFocus: false,
        }),
        'blur',
    );
});

test('returns null when player stays audible and page focused', () => {
    assert.equal(
        resolveVideoInterruptionReason({
            visibilityState: 'visible',
            hasWindowFocus: true,
            muted: false,
        }),
        null,
    );
});
