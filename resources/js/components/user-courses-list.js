const roots = document.querySelectorAll('[data-courses-list-root]');

const filters = {
    all: () => true,
    in_progress: (item) => item.dataset.courseGroup === 'in_progress',
    completed: (item) => item.dataset.courseStatus === 'completed',
};

for (const root of roots) {
    const tabs = root.querySelectorAll('[data-courses-tab]');
    const items = root.querySelectorAll('[data-course-item]');
    const emptyState = root.querySelector('[data-courses-filter-empty]');

    if (tabs.length === 0 || items.length === 0 || emptyState === null) {
        continue;
    }

    const applyFilter = (filterName) => {
        const matcher = filters[filterName] ?? filters.all;
        let visibleItems = 0;

        tabs.forEach((tab) => {
            tab.classList.toggle('tab-active', tab.dataset.coursesTab === filterName);
        });

        items.forEach((item) => {
            const isVisible = matcher(item);
            item.classList.toggle('hidden', ! isVisible);

            if (isVisible) {
                visibleItems += 1;
            }
        });

        emptyState.classList.toggle('hidden', visibleItems > 0);
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            applyFilter(tab.dataset.coursesTab ?? 'all');
        });
    });

    applyFilter('all');
}
