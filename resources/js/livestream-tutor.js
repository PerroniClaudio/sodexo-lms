import { initViewerPage } from './live-stream/viewer-page';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initViewerPage);
} else {
    initViewerPage();
}
