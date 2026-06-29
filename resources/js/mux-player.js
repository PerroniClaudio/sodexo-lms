export function applyMuxPlayerRestrictions(muxPlayer) {
    if (!(muxPlayer instanceof HTMLElement)) {
        return;
    }

    muxPlayer.setAttribute('playbackrates', '1');
    muxPlayer.setAttribute('controlslist', 'nopictureinpicture');
    muxPlayer.setAttribute('disablepictureinpicture', '');

    if ('disablePictureInPicture' in muxPlayer) {
        muxPlayer.disablePictureInPicture = true;
    }
}
