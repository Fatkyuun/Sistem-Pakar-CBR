/* ============================================================
   public/js/dashboard.js — grafik distribusi penyakit (FR-A1).
   Data dibaca dari <script type="application/json" id="dataDistribusi">
   yang diisi server, sehingga tidak ada data mentah di dalam JavaScript.
   ============================================================ */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var kanvas = document.getElementById('grafikPenyakit');
        var dataEl = document.getElementById('dataDistribusi');
        if (!kanvas || !dataEl || !window.Chart) { return; }

        var data;
        try {
            data = JSON.parse(dataEl.textContent || '{}');
        } catch (e) {
            return;
        }
        if (!Array.isArray(data.labels) || !Array.isArray(data.values)) { return; }

        new window.Chart(kanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Jumlah diagnosa',
                    data: data.values,
                    backgroundColor: 'rgba(2, 132, 199, 0.75)',
                    hoverBackgroundColor: 'rgba(3, 105, 161, 0.9)',
                    borderColor: 'rgba(2, 132, 199, 1)',
                    borderWidth: 0,
                    borderRadius: 6,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { family: "'Plus Jakarta Sans', system-ui, sans-serif", size: 13, weight: '600' },
                        bodyFont: { family: "'Plus Jakarta Sans', system-ui, sans-serif", size: 12 },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.parsed.y + ' diagnosa';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { family: "'Plus Jakarta Sans', system-ui, sans-serif", size: 11 },
                            color: '#94a3b8'
                        },
                        title: {
                            display: true,
                            text: 'Jumlah diagnosa',
                            font: { family: "'Plus Jakarta Sans', system-ui, sans-serif", size: 12, weight: '500' },
                            color: '#64748b'
                        },
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 0,
                            font: { family: "'Plus Jakarta Sans', system-ui, sans-serif", size: 11 },
                            color: '#64748b'
                        },
                        grid: {
                            display: false
                        },
                        border: {
                            color: '#e2e8f0'
                        }
                    }
                }
            }
        });
    });
})();
