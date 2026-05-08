import test from 'node:test';
import assert from 'node:assert/strict';

import {
    findRemoteParticipantByIdentity,
    getRemoteVideoTrackByIdentity,
    getRemoteVideoTrackPublicationByIdentity,
    getRemoteVideoTrackSignature,
    getTeacherStageVideoPublication,
    isScreenSharePublication,
    isParticipantIdentityHighlighted,
    resolveParticipantSpeakingState,
} from '../../resources/js/live-stream/participant-utils.mjs';

test('finds a remote participant by identity when the room map is keyed by participant sid', () => {
    const teacherParticipant = {
        sid: 'PA-teacher',
        identity: 'sodexo:teacher:4',
        videoTracks: new Map(),
    };

    const studentParticipant = {
        sid: 'PA-student',
        identity: 'sodexo:user:9',
        videoTracks: new Map(),
    };

    const room = {
        participants: new Map([
            [teacherParticipant.sid, teacherParticipant],
            [studentParticipant.sid, studentParticipant],
        ]),
    };

    assert.equal(findRemoteParticipantByIdentity(room, 'sodexo:teacher:4'), teacherParticipant);
    assert.equal(findRemoteParticipantByIdentity(room, 'missing'), null);
});

test('returns the first subscribed remote video track for a participant identity', () => {
    const remoteTrack = { sid: 'MT-video-1', kind: 'video' };

    const room = {
        participants: new Map([
            ['PA-student', {
                sid: 'PA-student',
                identity: 'sodexo:user:9',
                videoTracks: new Map([
                    ['MT-video-1', { track: remoteTrack }],
                    ['MT-video-2', { track: null }],
                ]),
            }],
        ]),
    };

    assert.equal(getRemoteVideoTrackByIdentity(room, 'sodexo:user:9'), remoteTrack);
    assert.equal(getRemoteVideoTrackByIdentity(room, 'sodexo:user:10'), null);
});

test('prefers the requested track name when multiple remote video tracks are available', () => {
    const cameraTrack = { sid: 'MT-camera', kind: 'video', name: 'camera' };
    const screenTrack = { sid: 'MT-screen', kind: 'video', name: 'screen' };

    const room = {
        participants: new Map([
            ['PA-teacher', {
                sid: 'PA-teacher',
                identity: 'sodexo:teacher:4',
                videoTracks: new Map([
                    ['MT-camera', { track: cameraTrack, trackName: 'camera' }],
                    ['MT-screen', { track: screenTrack, trackName: 'screen' }],
                ]),
            }],
        ]),
    };

    assert.equal(
        getRemoteVideoTrackByIdentity(room, 'sodexo:teacher:4', ['screen', 'camera']),
        screenTrack,
    );
    assert.equal(
        getRemoteVideoTrackPublicationByIdentity(room, 'sodexo:teacher:4', ['screen'])?.track,
        screenTrack,
    );
});

test('teacher stage publication prefers screen share over camera when both are published', () => {
    const cameraPublication = {
        trackName: 'camera',
        track: { sid: 'MT-camera', kind: 'video', name: 'camera' },
    };
    const screenPublication = {
        trackName: 'screen',
        track: { sid: 'MT-screen', kind: 'video', name: 'screen' },
    };

    const room = {
        participants: new Map([
            ['PA-teacher', {
                sid: 'PA-teacher',
                identity: 'sodexo:teacher:4',
                videoTracks: new Map([
                    ['MT-camera', cameraPublication],
                    ['MT-screen', screenPublication],
                ]),
            }],
        ]),
    };

    assert.equal(getTeacherStageVideoPublication(room, 'sodexo:teacher:4'), screenPublication);
    assert.equal(isScreenSharePublication(screenPublication), true);
    assert.equal(isScreenSharePublication(cameraPublication), false);
});

test('remote video track signature remains stable for the same participant track', () => {
    const publication = {
        trackName: 'camera',
        track: { sid: 'MT-camera', kind: 'video', name: 'camera' },
    };

    assert.equal(
        getRemoteVideoTrackSignature('sodexo:teacher:4', publication),
        'sodexo:teacher:4:MT-camera',
    );
    assert.equal(getRemoteVideoTrackSignature('sodexo:teacher:4', { track: null }), null);
});

test('prefers dominant speaker identity when deciding which participant to highlight', () => {
    const speakingParticipantIdentities = new Set(['sodexo:user:11']);

    assert.equal(
        isParticipantIdentityHighlighted('sodexo:user:11', speakingParticipantIdentities, 'sodexo:user:11'),
        true,
    );
    assert.equal(
        isParticipantIdentityHighlighted('sodexo:user:9', speakingParticipantIdentities, 'sodexo:user:11'),
        false,
    );
});

test('falls back to speaking identities when there is no dominant speaker', () => {
    const speakingParticipantIdentities = new Set(['sodexo:user:9']);

    assert.equal(isParticipantIdentityHighlighted('sodexo:user:9', speakingParticipantIdentities, null), true);
    assert.equal(isParticipantIdentityHighlighted('sodexo:user:11', speakingParticipantIdentities, null), false);
});

test('speaking state does not activate for background noise below the activation threshold', () => {
    const nextState = resolveParticipantSpeakingState({
        rms: 0.03,
        isSpeaking: false,
        lastHeardAt: 0,
        now: 1_000,
    });

    assert.deepEqual(nextState, {
        isSpeaking: false,
        lastHeardAt: 0,
    });
});

test('speaking state activates for audible voice above the activation threshold', () => {
    const nextState = resolveParticipantSpeakingState({
        rms: 0.06,
        isSpeaking: false,
        lastHeardAt: 0,
        now: 1_000,
    });

    assert.deepEqual(nextState, {
        isSpeaking: true,
        lastHeardAt: 1_000,
    });
});

test('speaking state releases after the hold window when audio drops below the release threshold', () => {
    const nextState = resolveParticipantSpeakingState({
        rms: 0.01,
        isSpeaking: true,
        lastHeardAt: 1_000,
        now: 1_400,
    });

    assert.deepEqual(nextState, {
        isSpeaking: false,
        lastHeardAt: 1_000,
    });
});
