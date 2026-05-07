import test from 'node:test';
import assert from 'node:assert/strict';

import {
    filterAudioOutputDevices,
    formatAudioOutputDeviceLabel,
    getLiveStreamIconButtonContent,
    getLiveStreamIconSvg,
    isBackgroundProcessorBenchmarkSlow,
    isAudioOutputSelectionSupported,
    isHardwareLikelySufficient,
    getParticipantAudioStatusMarkup,
    getParticipantInitialsBadgeClassNames,
    shouldRetryLiveStreamConnectWithoutCamera,
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

test('audio output selection support requires enumerateDevices and setSinkId', () => {
    assert.equal(
        isAudioOutputSelectionSupported({
            navigator: {
                mediaDevices: {
                    enumerateDevices: () => Promise.resolve([]),
                },
            },
            HTMLMediaElement: {
                prototype: {
                    setSinkId: async () => {},
                },
            },
        }),
        true,
    );

    assert.equal(
        isAudioOutputSelectionSupported({
            navigator: {
                mediaDevices: {},
            },
            HTMLMediaElement: {
                prototype: {},
            },
        }),
        false,
    );
});

test('audio output helpers keep only output devices and provide fallback labels', () => {
    const devices = filterAudioOutputDevices([
        { kind: 'audioinput', deviceId: 'mic-1', label: 'Mic' },
        { kind: 'audiooutput', deviceId: 'default', label: '' },
        { kind: 'audiooutput', deviceId: 'speaker-1', label: 'Desk speakers' },
    ]);

    assert.equal(devices.length, 2);
    assert.equal(formatAudioOutputDeviceLabel(devices[0], 0), 'Predefinito di sistema');
    assert.equal(formatAudioOutputDeviceLabel(devices[1], 1), 'Desk speakers');
});

test('live stream connect retries without camera when the video device is missing', () => {
    assert.equal(
        shouldRetryLiveStreamConnectWithoutCamera({
            name: 'NotFoundError',
            message: 'Requested device not found',
        }),
        true,
    );

    assert.equal(
        shouldRetryLiveStreamConnectWithoutCamera({
            name: 'TwilioError',
            message: 'Could not start video source',
        }),
        true,
    );
});

test('live stream connect does not retry without camera for unrelated errors', () => {
    assert.equal(
        shouldRetryLiveStreamConnectWithoutCamera({
            name: 'NotAllowedError',
            message: 'Permission denied',
        }),
        false,
    );
});

test('hardware heuristic disables background processors on low-end devices', () => {
    assert.equal(
        isHardwareLikelySufficient({
            deviceMemory: 4,
            hardwareConcurrency: 8,
        }),
        false,
    );

    assert.equal(
        isHardwareLikelySufficient({
            deviceMemory: 8,
            hardwareConcurrency: 2,
        }),
        false,
    );
});

test('hardware heuristic allows blur on minimum supported hardware', () => {
    assert.equal(
        isHardwareLikelySufficient({
            deviceMemory: 8,
            hardwareConcurrency: 4,
        }),
        true,
    );
});

test('hardware heuristic allows background processors when browser APIs are unavailable', () => {
    assert.equal(
        isHardwareLikelySufficient({}),
        true,
    );
});

test('background benchmark flags slow processing intervals', () => {
    assert.equal(
        isBackgroundProcessorBenchmarkSlow([160, 158, 166, 162, 170, 168, 171, 169, 164, 161, 167, 165]),
        true,
    );

    assert.equal(
        isBackgroundProcessorBenchmarkSlow([45, 44, 46, 43, 45, 42, 44, 46, 43, 45, 44, 42]),
        false,
    );
});

test('background benchmark ignores brief warmup spikes', () => {
    assert.equal(
        isBackgroundProcessorBenchmarkSlow([210, 180, 92, 88, 90, 86, 89, 91, 87, 90, 88, 89]),
        false,
    );
});
