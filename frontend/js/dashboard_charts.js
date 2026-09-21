/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Dashboard Charts & Analytics Visualizer (Chart.js)
 */

let stockChartInstance = null;
let demandSupplyChartInstance = null;

async function initDashboardCharts() {
    const stockCanvas = document.getElementById('stockChartCanvas');
    const balanceCanvas = document.getElementById('demandSupplyCanvas');

    if (!stockCanvas && !balanceCanvas) return;

    try {
        const [radarRes, matrixRes] = await Promise.all([
            apiRequest('radar.php'),
            apiRequest('requests.php?action=matrix')
        ]);

        if (stockCanvas && radarRes.success) {
            const labels = radarRes.data.radar.map(item => item.group_name);
            const units = radarRes.data.radar.map(item => parseInt(item.available_units, 10));
            const colors = radarRes.data.radar.map(item => {
                if (item.stock_status === 'CRITICAL') return 'rgba(239, 68, 68, 0.8)';
                if (item.stock_status === 'LOW') return 'rgba(245, 158, 11, 0.8)';
                return 'rgba(16, 185, 129, 0.8)';
            });

            if (stockChartInstance) stockChartInstance.destroy();

            stockChartInstance = new Chart(stockCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Available Units (Bags)',
                        data: units,
                        backgroundColor: colors,
                        borderColor: colors.map(c => c.replace('0.8', '1')),
                        borderWidth: 1.5,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Available: ${context.parsed.y} units`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        }

        if (balanceCanvas && matrixRes.success) {
            const labels = matrixRes.data.map(item => item.group_name);
            const supply = matrixRes.data.map(item => parseInt(item.available_units, 10));
            const demand = matrixRes.data.map(item => parseInt(item.requested_units, 10));

            if (demandSupplyChartInstance) demandSupplyChartInstance.destroy();

            demandSupplyChartInstance = new Chart(balanceCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Available Supply',
                            data: supply,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderRadius: 4
                        },
                        {
                            label: 'Pending Demand',
                            data: demand,
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        }
    } catch (e) {
        console.error('Error rendering dashboard charts:', e);
    }
}
