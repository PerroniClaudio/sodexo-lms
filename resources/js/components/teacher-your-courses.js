const themesByType = {
    fad: {
        cover: 'bg-info text-info-content',
        text: 'text-info',
        progress: 'progress-info',
    },
    async: {
        cover: 'bg-primary text-primary-content',
        text: 'text-primary',
        progress: 'progress-primary',
    },
    res: {
        cover: 'bg-secondary text-secondary-content',
        text: 'text-secondary',
        progress: 'progress-secondary',
    },
    blended: {
        cover: 'bg-warning text-warning-content',
        text: 'text-warning',
        progress: 'progress-warning',
    },
    fsc: {
        cover: 'bg-secondary text-secondary-content',
        text: 'text-secondary',
        progress: 'progress-secondary',
    },
    unknown: {
        cover: 'bg-neutral text-neutral-content',
        text: 'text-neutral',
        progress: 'progress-neutral',
    },
};

function createIcon(className, path) {
    const namespace = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(namespace, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('class', className);

    path.forEach((definition) => {
        const node = document.createElementNS(namespace, definition.tag);

        Object.entries(definition.attributes).forEach(([attribute, value]) => {
            node.setAttribute(attribute, value);
        });

        svg.append(node);
    });

    return svg;
}

function usersIcon() {
    return createIcon('h-4 w-4', [
        { tag: 'path', attributes: { d: 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' } },
        { tag: 'circle', attributes: { cx: '8.5', cy: '7', r: '4' } },
        { tag: 'path', attributes: { d: 'M20 8v6' } },
        { tag: 'path', attributes: { d: 'M23 11h-6' } },
    ]);
}

function badgeIcon() {
    return createIcon('h-4 w-4', [
        { tag: 'path', attributes: { d: 'M12 3H5a2 2 0 0 0-2 2v14l4-3 4 3 4-3 4 3V9' } },
        { tag: 'path', attributes: { d: 'M17 3h4a2 2 0 0 1 2 2v4' } },
        { tag: 'path', attributes: { d: 'm16 8 2 2 4-4' } },
    ]);
}

function buildCourseCard(course, capacityLabel, progressLabel, cardTemplate) {
    const theme = themesByType[course.type] ?? themesByType.unknown;
    const fragment = cardTemplate.content.cloneNode(true);
    const wrapper = fragment.firstElementChild;

    if (!(wrapper instanceof HTMLElement)) {
        throw new Error('Teacher courses card template is invalid.');
    }

    const cover = wrapper.querySelector('[data-course-cover]');
    const classBadge = wrapper.querySelector('[data-course-class-badge]');
    const typeLabel = wrapper.querySelector('[data-course-type-label]');
    const title = wrapper.querySelector('[data-course-title]');
    const participantsIcon = wrapper.querySelector('[data-course-participants-icon]');
    const participantsText = wrapper.querySelector('[data-course-participants-text]');
    const capacityIcon = wrapper.querySelector('[data-course-capacity-icon]');
    const capacityText = wrapper.querySelector('[data-course-capacity-text]');
    const progress = wrapper.querySelector('[data-course-progress-bar]');
    const progressText = wrapper.querySelector('[data-course-progress-text]');

    if (
        !(cover instanceof HTMLElement) ||
        !(classBadge instanceof HTMLElement) ||
        !(typeLabel instanceof HTMLElement) ||
        !(title instanceof HTMLElement) ||
        !(participantsIcon instanceof HTMLElement) ||
        !(participantsText instanceof HTMLElement) ||
        !(capacityIcon instanceof HTMLElement) ||
        !(capacityText instanceof HTMLElement) ||
        !(progress instanceof HTMLProgressElement) ||
        !(progressText instanceof HTMLElement)
    ) {
        throw new Error('Teacher courses card template fields are missing.');
    }

    const completionPercentage = Number.parseInt(`${course.completion_percentage ?? 0}`, 10) || 0;

    cover.classList.add(...theme.cover.split(' '));
    typeLabel.classList.add(...theme.text.split(' '));
    progress.classList.add(theme.progress);


    classBadge.textContent = course.class_name ?? 'Classe';
    typeLabel.textContent = course.type_label ?? 'Corso';
    title.textContent = course.title ?? 'Corso senza titolo';
    participantsIcon.replaceChildren(usersIcon());
    participantsText.textContent = course.participants_label ?? '0 partecipanti';
    capacityIcon.replaceChildren(badgeIcon());
    capacityText.textContent = `${capacityLabel}: ${course.occupancy_label ?? '0/30 posti'}`;
    progress.value = completionPercentage;
    progressText.textContent = `${progressLabel}: ${completionPercentage}%`;

    return wrapper;
}

document.addEventListener('DOMContentLoaded', () => {
    const roots = document.querySelectorAll('[data-teacher-your-courses-root]');

    roots.forEach(async (root) => {
        if (!(root instanceof HTMLElement) || root.dataset.ready === 'true') {
            return;
        }

        const coursesUrl = root.dataset.coursesUrl;
        const list = root.querySelector('[data-teacher-your-courses-list]');
        const emptyState = root.querySelector('[data-teacher-your-courses-empty]');
        const countBadge = root.querySelector('[data-teacher-your-courses-count]');
        const cardTemplate = root.querySelector('[data-teacher-your-courses-card-template]');

        if (
            !coursesUrl ||
            !(list instanceof HTMLElement) ||
            !(emptyState instanceof HTMLElement) ||
            !(countBadge instanceof HTMLElement) ||
            !(cardTemplate instanceof HTMLTemplateElement)
        ) {
            return;
        }

        const emptyLabel = root.dataset.emptyLabel ?? 'Nessuna classe assegnata al momento.';
        const errorLabel = root.dataset.errorLabel ?? 'Impossibile caricare i corsi del docente.';
        const countLabel = root.dataset.countLabel ?? 'classi';
        const capacityLabel = root.dataset.capacityLabel ?? 'Capienza classe';
        const progressLabel = root.dataset.progressLabel ?? 'Completamento corso';

        try {
            const response = await fetch(coursesUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Unable to load teacher courses: ${response.status}`);
            }

            const payload = await response.json();
            const courses = Array.isArray(payload.courses) ? payload.courses : [];

            list.innerHTML = '';
            countBadge.textContent = `${courses.length} ${countLabel}`;

            if (courses.length === 0) {
                emptyState.textContent = emptyLabel;
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
                courses.forEach((course) => {
                    list.append(buildCourseCard(course, capacityLabel, progressLabel, cardTemplate));
                });
            }

            root.dataset.ready = 'true';
        } catch (error) {
            list.innerHTML = '';
            countBadge.textContent = `0 ${countLabel}`;
            emptyState.textContent = errorLabel;
            emptyState.classList.remove('hidden');
            console.error(error);
        }
    });
});
