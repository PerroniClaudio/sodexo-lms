import {
    LIVE_STREAM_CAMERA_TRACK_NAME,
    LIVE_STREAM_SCREEN_TRACK_NAME,
} from './track-names.mjs';

export function findRemoteParticipantByIdentity(room, identity) {
    if (!room || !identity) {
        return null;
    }

    for (const participant of room.participants.values()) {
        if (participant.identity === identity) {
            return participant;
        }
    }

    return null;
}

export function resolveParticipantSpeakingState({
    rms,
    isSpeaking,
    lastHeardAt,
    now,
    activateThreshold = 0.05,
    releaseThreshold = 0.02,
    holdMs = 300,
}) {
    if (rms >= activateThreshold) {
        return {
            isSpeaking: true,
            lastHeardAt: now,
        };
    }

    if (!isSpeaking) {
        return {
            isSpeaking: false,
            lastHeardAt,
        };
    }

    if (rms >= releaseThreshold || now - lastHeardAt <= holdMs) {
        return {
            isSpeaking: true,
            lastHeardAt,
        };
    }

    return {
        isSpeaking: false,
        lastHeardAt,
    };
}

export function getTrackPublicationName(publication) {
    return publication?.trackName ?? publication?.track?.name ?? null;
}

export function getRemoteVideoTrackSignature(identity, publication) {
    const track = publication?.track ?? null;

    if (!identity || !track) {
        return null;
    }

    return [
        identity,
        track.sid ?? track.id ?? getTrackPublicationName(publication) ?? 'video',
    ].join(':');
}

export function getRemoteVideoTrackPublicationByIdentity(room, identity, preferredTrackNames = []) {
    const participant = findRemoteParticipantByIdentity(room, identity);

    if (!participant) {
        return null;
    }

    const publications = [...participant.videoTracks.values()].filter((publication) => publication.track);

    for (const preferredTrackName of preferredTrackNames) {
        const matchingPublication = publications.find(
            (publication) => getTrackPublicationName(publication) === preferredTrackName,
        );

        if (matchingPublication) {
            return matchingPublication;
        }
    }

    return publications[0] ?? null;
}

export function getRemoteVideoTrackByIdentity(room, identity, preferredTrackNames = []) {
    return getRemoteVideoTrackPublicationByIdentity(room, identity, preferredTrackNames)?.track ?? null;
}

export function getTeacherStageVideoPublication(room, identity) {
    return getRemoteVideoTrackPublicationByIdentity(room, identity, [
        LIVE_STREAM_SCREEN_TRACK_NAME,
        LIVE_STREAM_CAMERA_TRACK_NAME,
    ]);
}

export function isScreenSharePublication(publication) {
    return getTrackPublicationName(publication) === LIVE_STREAM_SCREEN_TRACK_NAME;
}

export function isParticipantIdentityHighlighted(identity, speakingParticipantIdentities, dominantSpeakerIdentity = null) {
    if (!identity) {
        return false;
    }

    if (dominantSpeakerIdentity) {
        return identity === dominantSpeakerIdentity;
    }

    return speakingParticipantIdentities.has(identity);
}

export function shouldShowLiveJoinPrompt(status, joined, alreadyShown) {
    return status === 'live' && !joined && !alreadyShown;
}
