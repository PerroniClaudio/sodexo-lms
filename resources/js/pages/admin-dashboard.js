import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(
    BarController,
    BarElement,
    CategoryScale,
    Legend,
    LinearScale,
    Tooltip,
);

function renderDistributionChart(canvas, distribution, question) {
    const context = canvas.getContext('2d');

    if (context === null) {
        return;
    }

    Chart.getChart(canvas)?.destroy();

    new Chart(context, {
        type: 'bar',
        data: {
            labels: distribution.map((item) => item.label),
            datasets: [
                {
                    label: question,
                    data: distribution.map((item) => item.count),
                    backgroundColor: '#2E348C',
                    borderRadius: 12,
                    borderSkipped: false,
                    maxBarThickness: 72,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 500,
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const item = distribution[context.dataIndex];

                            if (!item) {
                                return `${context.parsed.y}`;
                            }

                            return `${item.count} risposte (${item.percentage}%)`;
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
                        color: '#4b5563',
                    },
                    border: {
                        display: false,
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: '#4b5563',
                    },
                },
            },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-survey-summary-root]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const modal = document.querySelector('[data-survey-distribution-modal]');
    const title = document.querySelector('[data-survey-distribution-title]');
    const canvas = document.querySelector('[data-survey-distribution-canvas]');

    if (!(modal instanceof HTMLDialogElement) || !(title instanceof HTMLElement) || !(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    root.querySelectorAll('[data-survey-distribution-trigger]').forEach((trigger) => {
        if (!(trigger instanceof HTMLButtonElement)) {
            return;
        }

        trigger.addEventListener('click', () => {
            const question = trigger.dataset.question ?? '';
            const distribution = JSON.parse(trigger.dataset.distribution ?? '[]');

            if (!Array.isArray(distribution)) {
                return;
            }

            title.textContent = question;
            renderDistributionChart(canvas, distribution, question);
            modal.showModal();
        });
    });

    modal.addEventListener('close', () => {
        Chart.getChart(canvas)?.destroy();
    });
});
