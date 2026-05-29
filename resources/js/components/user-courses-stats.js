import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
);

function buildCourseRow(course) {
    const wrapper = document.createElement('div');
    wrapper.className = 'flex flex-col gap-1';

    const header = document.createElement('div');
    header.className = 'flex justify-between gap-2';

    const title = document.createElement('span');
    title.className = 'text-sm font-medium';
    title.textContent = course.title;

    const progress = document.createElement('span');
    progress.className = 'text-xs text-gray-500';
    progress.textContent = `${course.progress}%`;

    const progressBar = document.createElement('progress');
    progressBar.className = 'progress progress-primary w-full';
    progressBar.max = 100;
    progressBar.value = course.progress;

    header.append(title, progress);
    wrapper.append(header, progressBar);

    return wrapper;
}

function renderCompletionChart(container, completed, remaining) {
    const canvas = container.querySelector('[data-completion-chart]');

    if (!(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    const context = canvas.getContext('2d');

    if (context === null) {
        return;
    }

    const existingChart = Chart.getChart(canvas);

    existingChart?.destroy();

    new Chart(context, {
        type: 'doughnut',
        data: {
            labels: ['Completato', 'Da completare'],
            datasets: [
                {
                    data: [completed, remaining],
                    backgroundColor: ['#2E348C', '#d1d5db'],
                    borderWidth: 0,
                    hoverOffset: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            animation: {
                duration: 600,
            },
            plugins: {
                legend: false,
                tooltip: false,
            },
        },
    });
}

function renderWeeklyChart(container, labels, hours) {
    const canvas = container.querySelector('[data-weekly-chart]');

    if (!(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    const context = canvas.getContext('2d');

    if (context === null) {
        return;
    }

    const existingChart = Chart.getChart(canvas);

    existingChart?.destroy();

    new Chart(context, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Ore',
                    data: hours,
                    backgroundColor: '#2E348C',
                    borderRadius: {
                        topLeft: 16,
                        topRight: 16,
                    },
                    borderSkipped: false,
                    maxBarThickness: 95,
                    categoryPercentage: 0.98,
                    barPercentage: 0.98,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 700,
            },
            plugins: {
                legend: false,
                tooltip: {
                    callbacks: {
                        label(context) {
                            return `${context.parsed.y} h`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 14,
                        },
                    },
                    border: {
                        display: false,
                    },
                },
                y: {
                    display: false,
                    beginAtZero: true,
                    grid: {
                        display: false,
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
    const statsRoots = document.querySelectorAll('[data-courses-stats-root]');

    statsRoots.forEach(async (root) => {
        if (!(root instanceof HTMLElement) || root.dataset.statsReady === 'true') {
            return;
        }

        const statsUrl = root.dataset.statsUrl;

        if (!statsUrl) {
            return;
        }

        const completionContainer = root.querySelector('[data-courses-stats-chart]');
        const weeklyContainer = root.querySelector('[data-weekly-activity-chart]');
        const overallProgress = root.querySelector('[data-overall-progress]');
        const coursesList = root.querySelector('[data-courses-list]');
        const emptyState = root.querySelector('[data-courses-empty]');

        if (
            !(completionContainer instanceof HTMLElement) ||
            !(weeklyContainer instanceof HTMLElement) ||
            !(overallProgress instanceof HTMLElement) ||
            !(coursesList instanceof HTMLElement)
        ) {
            return;
        }

        try {
            const response = await fetch(statsUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Unable to load courses stats: ${response.status}`);
            }

            const payload = await response.json();
            const courses = Array.isArray(payload.courses) ? payload.courses : [];
            const labels = Array.isArray(payload.weekly_activity?.labels) ? payload.weekly_activity.labels : [];
            const hours = Array.isArray(payload.weekly_activity?.hours) ? payload.weekly_activity.hours : [];
            const completed = Number.parseInt(`${payload.overall_progress ?? 0}`, 10);
            const remaining = Number.parseInt(`${payload.remaining_progress ?? 0}`, 10);

            overallProgress.textContent = `${completed}%`;
            coursesList.innerHTML = '';

            if (courses.length === 0) {
                const message = emptyState instanceof HTMLElement ? emptyState : document.createElement('p');
                message.className = 'text-sm text-base-content/60';
                message.textContent = 'Nessun corso assegnato.';
                coursesList.append(message);
            } else {
                courses.forEach((course) => {
                    coursesList.append(buildCourseRow({
                        title: course.title ?? 'Corso senza titolo',
                        progress: Number.parseInt(`${course.progress ?? 0}`, 10),
                    }));
                });
            }

            renderCompletionChart(completionContainer, completed, remaining);
            renderWeeklyChart(weeklyContainer, labels, hours);
            root.dataset.statsReady = 'true';
        } catch (error) {
            overallProgress.textContent = '0%';

            if (emptyState instanceof HTMLElement) {
                emptyState.textContent = 'Dati non disponibili.';
            }

            coursesList.innerHTML = '';

            if (emptyState instanceof HTMLElement) {
                coursesList.append(emptyState);
            }

            renderCompletionChart(completionContainer, 0, 100);
            renderWeeklyChart(weeklyContainer, ['-', '-', '-', '-', '-', '-', '-'], [0, 0, 0, 0, 0, 0, 0]);
            console.error(error);
        }
    });
});
