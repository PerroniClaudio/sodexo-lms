import { initTeacherPage } from './live-stream/teacher-page';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTeacherPage);
} else {
    initTeacherPage();
}
