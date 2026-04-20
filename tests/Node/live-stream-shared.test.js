import test from 'node:test';
import assert from 'node:assert/strict';

import {
    getLiveStreamIconButtonContent,
    getLiveStreamIconSvg,
    getParticipantAudioStatusMarkup,
    getParticipantInitialsBadgeClassNames,
} from '../../resources/js/live-stream/shared.js';

test('participant initials badge uses a green border when highlighted', () => {
    const classNames = getParticipantInitialsBadgeClassNames(true);

    assert.match(classNames, /\bborder-success\b/);
    assert.match(classNames, /\bring-2\b/);
    assert.match(classNames, /\bbg-success\/10\b/);
});

test('participant initials badge remains transparent when not highlighted', () => {
    const classNames = getParticipantInitialsBadgeClassNames(false);

    assert.match(classNames, /\bborder-transparent\b/);
    assert.doesNotMatch(classNames, /\bborder-success\b/);
});

test('live stream icon svg renders the requested microphone icon', () => {
    const svgMarkup = getLiveStreamIconSvg('mic-off');

    assert.match(svgMarkup, /<svg/);
    assert.match(svgMarkup, /stroke="currentColor"/);
    assert.match(svgMarkup, /viewBox="0 0 24 24"/);
});

test('live stream icon button content includes an accessible label', () => {
    const buttonContent = getLiveStreamIconButtonContent('pin', 'Fissa');

    assert.match(buttonContent, /<svg/);
    assert.match(buttonContent, /<span class="sr-only">Fissa<\/span>/);
});

test('participant audio status markup exposes the microphone state through an icon label', () => {
    const mutedStatus = getParticipantAudioStatusMarkup(false);
    const activeStatus = getParticipantAudioStatusMarkup(true);

    assert.match(mutedStatus, /aria-label="Audio moderato"/);
    assert.match(mutedStatus, /title="Audio moderato"/);
    assert.match(activeStatus, /aria-label="Audio attivo"/);
    assert.match(activeStatus, /title="Audio attivo"/);
});
