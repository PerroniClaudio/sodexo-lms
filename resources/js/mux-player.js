export function applyMuxPlayerRestrictions(muxPlayer) {
    if (!(muxPlayer instanceof HTMLElement)) {
        return;
    }

    muxPlayer.setAttribute('playbackrates', '1');
    muxPlayer.setAttribute('disablepictureinpicture', '');

    if ('disablePictureInPicture' in muxPlayer) {
        muxPlayer.disablePictureInPicture = true;
    }

    let resettingPlaybackRate = false;

    muxPlayer.addEventListener('ratechange', () => {
        if (resettingPlaybackRate || muxPlayer.playbackRate === 1) {
            return;
        }

        resettingPlaybackRate = true;
        muxPlayer.playbackRate = 1;

        queueMicrotask(() => {
            resettingPlaybackRate = false;
        });
    });
}
