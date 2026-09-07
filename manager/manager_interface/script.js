// ==========================================================================
// LifeLineConnect - Manager Dashboard Script (Pure Vanilla JS)
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Render Chart.js Blood Inventory
    const ctx = document.getElementById('inventoryChart');
    if (ctx && typeof chartLabels !== 'undefined' && typeof chartData !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Available Units in Blood Inventory',
                    data: chartData,
                    backgroundColor: [
                        'rgba(183, 28, 28, 0.85)',
                        'rgba(211, 47, 47, 0.85)',
                        'rgba(229, 57, 53, 0.85)',
                        'rgba(136, 14, 79, 0.85)',
                        'rgba(173, 20, 87, 0.85)',
                        'rgba(194, 24, 91, 0.85)',
                        'rgba(198, 40, 40, 0.85)',
                        'rgba(239, 83, 80, 0.85)'
                    ],
                    borderColor: '#780B1E',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: { family: "'Segoe UI', sans-serif", size: 13, weight: '600' },
                            color: '#334155'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 5,
                            font: { family: "'Segoe UI', sans-serif" }
                        },
                        grid: {
                            color: '#E2E8F0'
                        }
                    },
                    x: {
                        ticks: {
                            font: { family: "'Segoe UI', sans-serif", weight: 'bold' }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // Sidebar active navigation on click
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function () {
            navLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });
});

function closeAlert() {
    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
        alertBox.style.display = 'none';
    }
}