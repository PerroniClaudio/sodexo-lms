import {
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    CategoryScale,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

function colorWithAlpha(color, alpha) {
    if (!color.startsWith('#')) {
        return color;
    }

    let hex = color.slice(1);

    if (hex.length === 3) {
        hex = hex.split('').map((character) => character + character).join('');
    }

    if (hex.length !== 6) {
        return color;
    }

    const red = Number.parseInt(hex.slice(0, 2), 16);
    const green = Number.parseInt(hex.slice(2, 4), 16);
    const blue = Number.parseInt(hex.slice(4, 6), 16);

    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}

function seriesPeak(...series) {
    return Math.max(0, ...series.flat().map((value) => Number.parseInt(`${value ?? 0}`, 10)));
}

function buildSummaryCard({ iconClassName, iconSvg, value, label }) {
    const article = document.createElement('article');
    article.className = 'rounded-box border border-base-300 bg-base-100 p-4 shadow-sm';

    const icon = document.createElement('div');
    icon.className = `mb-4 inline-flex h-11 w-11 items-center justify-center rounded-2xl ${iconClassName}`;
    icon.innerHTML = iconSvg;

    const valueNode = document.createElement('p');
    valueNode.className = 'text-3xl font-semibold text-base-content';
    valueNode.textContent = `${value}`;

    const labelNode = document.createElement('p');
    labelNode.className = 'mt-1 text-sm text-base-content/60';
    labelNode.textContent = label;

    article.append(icon, valueNode, labelNode);

    return article;
}

function summaryCards(root, totals) {
    const container = root.querySelector('[data-teacher-user-engagement-summary]');

    if (!(container instanceof HTMLElement)) {
        return;
    }

    container.innerHTML = '';

    const cards = [
        {
            iconClassName: 'bg-primary/12 text-primary',
            iconSvg: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12S5.25 6.75 12 6.75 21.75 12 21.75 12 18.75 17.25 12 17.25 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 14.25A2.25 2.25 0 1 0 12 9.75a2.25 2.25 0 0 0 0 4.5Z" /></svg>',
            value: totals.active_week ?? 0,
            label: root.dataset.activeWeekLabel ?? 'Attivi settimana',
        },
        {
            iconClassName: 'bg-accent/12 text-accent',
            iconSvg: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>',
            value: totals.completed_week ?? 0,
            label: root.dataset.completedWeekLabel ?? 'Completati settimana',
        },
        {
            iconClassName: 'bg-primary/12 text-primary',
            iconSvg: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>',
            value: totals.active_today ?? 0,
            label: root.dataset.activeTodayLabel ?? 'Attivi oggi',
        },
        {
            iconClassName: 'bg-accent/12 text-accent',
            iconSvg: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
            value: totals.completed_today ?? 0,
            label: root.dataset.completedTodayLabel ?? 'Completati oggi',
        },
    ];

    cards.forEach((card) => {
        container.append(buildSummaryCard(card));
    });
}

function externalTooltipHandler(root) {
    const tooltip = root.querySelector('[data-teacher-user-engagement-tooltip]');
    const tooltipLabel = root.querySelector('[data-teacher-user-engagement-tooltip-label]');
    const activeValue = root.querySelector('[data-teacher-user-engagement-tooltip-active-value]');
    const completedValue = root.querySelector('[data-teacher-user-engagement-tooltip-completed-value]');

    if (
        !(tooltip instanceof HTMLElement) ||
        !(tooltipLabel instanceof HTMLElement) ||
        !(activeValue instanceof HTMLElement) ||
        !(completedValue instanceof HTMLElement)
    ) {
        return () => {};
    }

    return ({ tooltip: chartTooltip }) => {
        if (chartTooltip.opacity === 0) {
            tooltip.classList.add('hidden');

            return;
        }

        const [activePoint, completedPoint] = chartTooltip.dataPoints;

        tooltipLabel.textContent = chartTooltip.title[0] ?? '';
        activeValue.textContent = `${activePoint?.formattedValue ?? '0'}`;
        completedValue.textContent = `${completedPoint?.formattedValue ?? '0'}`;

        const offsetLeft = chartTooltip.caretX + 24;
        const offsetTop = Math.max(12, chartTooltip.caretY - 56);

        tooltip.style.left = `${offsetLeft}px`;
        tooltip.style.top = `${offsetTop}px`;
        tooltip.classList.remove('hidden');
    };
}

function renderChart(root, labels, activeUsers, completedUsers) {
    const wrapper = root.querySelector('[data-teacher-user-engagement-chart]');
    const canvas = root.querySelector('[data-teacher-user-engagement-canvas]');

    if (!(wrapper instanceof HTMLElement) || !(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    const context = canvas.getContext('2d');

    if (context === null) {
        return;
    }

    const style = getComputedStyle(root);
    const primary = style.getPropertyValue('--color-primary').trim() || '#2E348C';
    const accent = style.getPropertyValue('--color-accent').trim() || '#DA2020';
    const tickColor = style.getPropertyValue('--color-base-content').trim() || '#1f2937';
    const gridColor = colorWithAlpha(tickColor, 0.1);
    const primaryGradient = context.createLinearGradient(0, 0, 0, canvas.height || 320);
    const accentGradient = context.createLinearGradient(0, 0, 0, canvas.height || 320);

    primaryGradient.addColorStop(0, colorWithAlpha(primary, 0.24));
    primaryGradient.addColorStop(1, colorWithAlpha(primary, 0));
    accentGradient.addColorStop(0, colorWithAlpha(accent, 0.18));
    accentGradient.addColorStop(1, colorWithAlpha(accent, 0));

    const existingChart = Chart.getChart(canvas);

    existingChart?.destroy();

    const maxValue = seriesPeak(activeUsers, completedUsers);

    new Chart(context, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: root.dataset.activeLabel ?? 'Utenti attivi',
                    data: activeUsers,
                    borderColor: primary,
                    backgroundColor: primaryGradient,
                    fill: true,
                    tension: 0.42,
                    borderWidth: 3,
                    pointRadius: 0,
                    pointHoverRadius: 7,
                    pointHoverBorderWidth: 3,
                    pointHoverBackgroundColor: primary,
                    pointHoverBorderColor: '#ffffff',
                },
                {
                    label: root.dataset.completedLabel ?? 'Completamenti',
                    data: completedUsers,
                    borderColor: accent,
                    backgroundColor: accentGradient,
                    fill: true,
                    tension: 0.42,
                    borderWidth: 3,
                    pointRadius: 0,
                    pointHoverRadius: 7,
                    pointHoverBorderWidth: 3,
                    pointHoverBackgroundColor: accent,
                    pointHoverBorderColor: '#ffffff',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: false,
                tooltip: {
                    enabled: false,
                    external: externalTooltipHandler(root),
                },
            },
            animation: {
                duration: 700,
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: colorWithAlpha(tickColor, 0.58),
                        font: {
                            size: 14,
                            weight: '500',
                        },
                    },
                    border: {
                        display: false,
                    },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: maxValue > 0 ? Math.ceil(maxValue * 1.2) : 10,
                    ticks: {
                        precision: 0,
                        color: colorWithAlpha(tickColor, 0.5),
                        font: {
                            size: 13,
                        },
                    },
                    grid: {
                        color: gridColor,
                        drawTicks: false,
                    },
                    border: {
                        display: false,
                    },
                },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const roots = document.querySelectorAll('[data-teacher-user-engagement-root]');

    roots.forEach(async (root) => {
        if (!(root instanceof HTMLElement) || root.dataset.statsReady === 'true') {
            return;
        }

        const statsUrl = root.dataset.statsUrl;

        if (!statsUrl) {
            return;
        }

        const emptyState = root.querySelector('[data-teacher-user-engagement-empty]');
        const summary = root.querySelector('[data-teacher-user-engagement-summary]');

        try {
            const response = await fetch(statsUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Unable to load teacher engagement: ${response.status}`);
            }

            const payload = await response.json();
            const labels = Array.isArray(payload.labels) ? payload.labels : ['-', '-', '-', '-', '-', '-', '-'];
            const activeUsers = Array.isArray(payload.active_users) ? payload.active_users : [0, 0, 0, 0, 0, 0, 0];
            const completedUsers = Array.isArray(payload.completed_users) ? payload.completed_users : [0, 0, 0, 0, 0, 0, 0];
            const hasData = [...activeUsers, ...completedUsers].some((value) => Number.parseInt(`${value ?? 0}`, 10) > 0);

            if (emptyState instanceof HTMLElement) {
                emptyState.textContent = root.dataset.emptyLabel ?? 'Nessun dato disponibile per l\'ultima settimana.';
                emptyState.classList.toggle('hidden', hasData);
            }

            summary?.classList.remove('hidden');
            renderChart(root, labels, activeUsers, completedUsers);
            summaryCards(root, payload.totals ?? {});
            root.dataset.statsReady = 'true';
        } catch (error) {
            if (emptyState instanceof HTMLElement) {
                emptyState.textContent = root.dataset.errorLabel ?? 'Impossibile caricare il coinvolgimento utenti.';
                emptyState.classList.remove('hidden');
            }

            summary?.classList.add('hidden');
            renderChart(root, ['-', '-', '-', '-', '-', '-', '-'], [0, 0, 0, 0, 0, 0, 0], [0, 0, 0, 0, 0, 0, 0]);
            console.error(error);
        }
    });
});
