const activityThemes = {
    module_completed: {
        dot: 'bg-sky-500',
        label: 'text-base-content',
    },
    course_completed: {
        dot: 'bg-emerald-500',
        label: 'text-base-content',
    },
    default: {
        dot: 'bg-base-content/40',
        label: 'text-base-content',
    },
};

function buildActivityItem(activity, template) {
    const theme = activityThemes[activity.type] ?? activityThemes.default;
    const fragment = template.content.cloneNode(true);
    const article = fragment.firstElementChild;

    if (!(article instanceof HTMLElement)) {
        throw new Error('Teacher user activity template is invalid.');
    }

    const dot = article.querySelector('[data-activity-dot]');
    const label = article.querySelector('[data-activity-label]');
    const message = article.querySelector('[data-activity-message]');
    const context = article.querySelector('[data-activity-context]');
    const time = article.querySelector('[data-activity-time]');

    if (
        !(dot instanceof HTMLElement) ||
        !(label instanceof HTMLElement) ||
        !(message instanceof HTMLElement) ||
        !(context instanceof HTMLElement) ||
        !(time instanceof HTMLElement)
    ) {
        throw new Error('Teacher user activity template fields are missing.');
    }

    dot.classList.add(...theme.dot.split(' '));
    label.classList.add(...theme.label.split(' '));
    label.textContent = activity.label ?? '';
    message.textContent = activity.message ?? '';
    time.textContent = activity.occurred_at_label ?? '';

    if (activity.context) {
        context.textContent = activity.context;
        context.classList.remove('hidden');
    } else {
        context.textContent = '';
        context.classList.add('hidden');
    }

    return article;
}

document.addEventListener('DOMContentLoaded', () => {
    const roots = document.querySelectorAll('[data-teacher-user-activity-root]');

    roots.forEach(async (root) => {
        if (!(root instanceof HTMLElement) || root.dataset.ready === 'true') {
            return;
        }

        const activitiesUrl = root.dataset.activitiesUrl;
        const list = root.querySelector('[data-teacher-user-activity-list]');
        const emptyState = root.querySelector('[data-teacher-user-activity-empty]');
        const itemTemplate = root.querySelector('[data-teacher-user-activity-item-template]');

        if (
            !activitiesUrl ||
            !(list instanceof HTMLElement) ||
            !(emptyState instanceof HTMLElement) ||
            !(itemTemplate instanceof HTMLTemplateElement)
        ) {
            return;
        }

        const emptyLabel = root.dataset.emptyLabel ?? 'Nessuna attività recente.';
        const errorLabel = root.dataset.errorLabel ?? 'Impossibile caricare le attività recenti.';

        try {
            const response = await fetch(activitiesUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Unable to load teacher user activity: ${response.status}`);
            }

            const payload = await response.json();
            const activities = Array.isArray(payload.activities) ? payload.activities : [];

            list.innerHTML = '';

            if (activities.length === 0) {
                emptyState.textContent = emptyLabel;
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
                activities.forEach((activity) => {
                    list.append(buildActivityItem(activity, itemTemplate));
                });
            }

            root.dataset.ready = 'true';
        } catch (error) {
            list.innerHTML = '';
            emptyState.textContent = errorLabel;
            emptyState.classList.remove('hidden');
            console.error(error);
        }
    });
});
