const courseElement = document.querySelector('[data-course-access-states-url]');

if (courseElement) {
    const unlockCard = (card) => {
        card.dataset.moduleAccessGateActive = 'false';
        card.classList.remove('opacity-60');

        const icon = card.querySelector('[data-module-card-icon]');
        icon.className = 'flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-info/20 bg-info/15 text-info';
        icon.textContent = card.dataset.moduleOrder;

        const action = card.querySelector('[data-module-access-action]');
        const link = document.createElement('a');
        link.href = card.dataset.modulePlayerUrl;
        link.className = card.dataset.moduleIsCurrent === 'true' ? 'btn btn-primary gap-2' : 'btn btn-outline btn-primary';
        link.textContent = card.dataset.moduleIsCurrent === 'true' ? 'Inizia' : 'Apri';
        action.replaceChildren(link);
    };

    const refresh = async () => {
        const response = await fetch(courseElement.dataset.courseAccessStatesUrl, {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });

        if (! response.ok) {
            return;
        }

        const { modules } = await response.json();

        modules.filter((module) => ! module.access_gate_active).forEach((module) => {
            const card = document.querySelector(`[data-module-access-card="${module.id}"][data-module-access-gate-active="true"]`);

            if (card) {
                unlockCard(card);
            }
        });
    };

    window.setInterval(() => refresh().catch(() => {}), 30000);
}
