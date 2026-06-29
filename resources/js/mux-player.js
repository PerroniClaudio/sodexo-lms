export function applyMuxPlayerRestrictions(muxPlayer) {
    if (!(muxPlayer instanceof HTMLElement)) {
        return;
    }

    muxPlayer.style.setProperty('--pip-button', 'none');
    muxPlayer.style.setProperty('--playback-rate-button', 'none');
    muxPlayer.setAttribute('playbackrates', '1');
    muxPlayer.setAttribute('controlslist', 'nopictureinpicture');
    muxPlayer.setAttribute('disablepictureinpicture', '');

    if ('disablePictureInPicture' in muxPlayer) {
        muxPlayer.disablePictureInPicture = true;
    }
}
