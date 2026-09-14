// ==================== CHARTS ====================
// Extraído de script.js pelo PLAN-20260703-013 (Fase 3.3) — carregado logo
// após script.js (e demais módulos) via <script> separado.

function initCharts() {
    const isLight = document.body.classList.contains('light-mode');
    Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.color = isLight ? '#94a3b8' : 'rgba(255, 255, 255, 0.3)';

    const gridColor = isLight ? 'rgba(0, 0, 0, 0.05)' : 'rgba(255, 255, 255, 0.05)';
    const primaryColor = '#3b82f6';

    // 1. Line Chart (Fluxo)
    const lineCtx = document.getElementById('lineChart');
    if (lineCtx) {
        const lineCtx2d = lineCtx.getContext('2d');
        const lineGradient = lineCtx2d.createLinearGradient(0, 0, 0, 300);
        lineGradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
        lineGradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

        DashState.lineChart = new Chart(lineCtx2d, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Tickets Criados',
                    data: [],
                    borderColor: primaryColor,
                    backgroundColor: lineGradient,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: primaryColor,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: Chart.defaults.color } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: gridColor },
                        ticks: { color: Chart.defaults.color, precision: 0 }
                    }
                }
            }
        });
    }

    // 2. Bar Chart (Categorias)
    const barCtx = document.getElementById('barChart');
    if (barCtx) {
        DashState.barChart = new Chart(barCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Quantidade',
                    data: [],
                    backgroundColor: primaryColor,
                    borderRadius: 8,
                    barThickness: 24
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { display: false }, ticks: { color: Chart.defaults.color } },
                    y: { grid: { display: false }, ticks: { color: Chart.defaults.color, autoSkip: false } }
                }
            }
        });
    }

    // 3. NOVO: Monthly Chart (Comparativo)
    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        DashState.monthlyChart = new Chart(monthlyCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Abertos',
                        data: [],
                        backgroundColor: 'rgba(59, 130, 246, 0.8)', // Azul (primary)
                        borderRadius: 4,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    },
                    {
                        label: 'Solucionados',
                        data: [],
                        backgroundColor: 'rgba(34, 197, 94, 0.8)', // Verde (success)
                        borderRadius: 4,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: { color: Chart.defaults.color }
                    },
                    tooltip: {
                        backgroundColor: isLight ? 'rgba(255, 255, 255, 0.95)' : 'rgba(0, 0, 0, 0.8)',
                        titleColor: isLight ? '#0f172a' : '#ffffff',
                        bodyColor: isLight ? '#64748b' : 'rgba(255, 255, 255, 0.7)',
                        borderColor: isLight ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: Chart.defaults.color }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: Chart.defaults.color }
                    }
                }
            }
        });
    }

    const notificationCtx = document.getElementById('notificationChart');
    if (notificationCtx) {
        const notificationCtx2d = notificationCtx.getContext('2d');
        const notificationGradient = notificationCtx2d.createLinearGradient(0, 0, 0, 280);
        notificationGradient.addColorStop(0, 'rgba(34, 197, 94, 0.28)');
        notificationGradient.addColorStop(1, 'rgba(34, 197, 94, 0)');

        DashState.notificationChart = new Chart(notificationCtx2d, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Enviadas',
                    data: [],
                    borderColor: '#22c55e',
                    backgroundColor: notificationGradient,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#22c55e',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: Chart.defaults.color }
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: gridColor },
                        ticks: {
                            color: Chart.defaults.color,
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    const healthQueueCtx = document.getElementById('healthQueueChart');
    if (healthQueueCtx) {
        const healthQueueCtx2d = healthQueueCtx.getContext('2d');
        const healthQueueGradient = healthQueueCtx2d.createLinearGradient(0, 0, 0, 280);
        healthQueueGradient.addColorStop(0, 'rgba(245, 158, 11, 0.26)');
        healthQueueGradient.addColorStop(1, 'rgba(245, 158, 11, 0)');

        DashState.healthQueueChart = new Chart(healthQueueCtx2d, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Processamento da fila',
                    data: [],
                    borderColor: '#f59e0b',
                    backgroundColor: healthQueueGradient,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: Chart.defaults.color }
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: gridColor },
                        ticks: {
                            color: Chart.defaults.color,
                            precision: 0
                        }
                    }
                }
            }
        });
    }
}

