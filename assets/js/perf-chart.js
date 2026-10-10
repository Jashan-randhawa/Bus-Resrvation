/**
 * assets/js/perf-chart.js
 * Performance Overview: Charts & Indicators (A12)
 * Renders daily combo chart (stacked volume + revenue line) and outcome donut.
 */
(function() {
    'use strict';

    function initPerfCharts() {
        var chartCanvas = document.getElementById('perfChart');
        if (!chartCanvas) return;

        var rawPayload = chartCanvas.getAttribute('data-chart');
        if (!rawPayload) return;

        var chartData;
        try {
            chartData = JSON.parse(rawPayload);
        } catch (e) {
            console.warn('[perf-chart] Invalid chart payload JSON');
            return;
        }

        // Graceful fallback if Chart.js is not loaded
        if (typeof Chart === 'undefined') {
            var container = chartCanvas.parentElement;
            if (container) {
                container.innerHTML = '<div class="alert alert-light border text-muted small p-4 text-center my-auto">' +
                    'Interactive charts could not be loaded. Please refer to the daily breakdown table below.' +
                    '</div>';
            }
            var donutEl = document.getElementById('perfDonut');
            if (donutEl && donutEl.parentElement) {
                donutEl.parentElement.style.display = 'none';
            }
            return;
        }

        var donutCanvas = document.getElementById('perfDonut');
        var labels = chartData.labels || [];
        var bookings = chartData.bookings || [];
        var cancelled = chartData.cancelled || [];
        var revenue = chartData.revenue || [];
        var currency = chartData.currency || '₹';
        var windowDays = chartData.window_days || 30;
        var totalBookings = chartData.total_bookings || 0;
        var totalCancelled = chartData.total_cancelled || 0;
        var cancelRate = chartData.cancel_rate || 0.0;

        // Active bookings = total minus cancelled
        var activeBookings = bookings.map(function(b, idx) {
            var c = cancelled[idx] || 0;
            return Math.max(0, b - c);
        });

        // Date formatting helpers
        function formatDateLabel(dStr) {
            var parts = dStr.split('-');
            if (parts.length < 3) return dStr;
            var dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            if (windowDays === 7) {
                return dt.toLocaleDateString('en-US', { weekday: 'short', day: '2-digit' });
            }
            return dt.toLocaleDateString('en-US', { day: '2-digit', month: 'short' });
        }

        function formatFullDate(dStr) {
            var parts = dStr.split('-');
            if (parts.length < 3) return dStr;
            var dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            return dt.toLocaleDateString('en-US', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
        }

        function compactNumber(val) {
            if (val >= 10000000) return (val / 10000000).toFixed(1) + 'Cr';
            if (val >= 100000) return (val / 100000).toFixed(1) + 'L';
            if (val >= 1000) return (val / 1000).toFixed(0) + 'k';
            return Math.round(val).toString();
        }

        function getThemeColors() {
            var cs = window.getComputedStyle(document.documentElement);
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            return {
                primary: cs.getPropertyValue('--c-primary').trim() || '#2563eb',
                primaryBg: isDark ? 'rgba(59, 130, 246, 0.85)' : 'rgba(37, 99, 235, 0.85)',
                success: cs.getPropertyValue('--c-success').trim() || '#16a34a',
                warning: cs.getPropertyValue('--c-warning').trim() || '#d97706',
                error: cs.getPropertyValue('--c-error').trim() || '#dc2626',
                text: cs.getPropertyValue('--c-text').trim() || (isDark ? '#f8fafc' : '#1e293b'),
                textMuted: cs.getPropertyValue('--c-text-muted').trim() || (isDark ? '#94a3b8' : '#64748b'),
                border: cs.getPropertyValue('--c-border').trim() || (isDark ? '#334155' : '#e2e8f0'),
                grid: isDark ? 'rgba(255, 255, 255, 0.07)' : 'rgba(0, 0, 0, 0.06)',
            };
        }

        var isReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var animDuration = isReducedMotion ? 0 : 400;

        var formattedLabels = labels.map(formatDateLabel);
        var colors = getThemeColors();

        // 1. Combo Chart (Daily Stacked Volume Bars + Revenue Line)
        var comboChart = new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: formattedLabels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Active Bookings',
                        data: activeBookings,
                        backgroundColor: colors.primaryBg,
                        borderRadius: { topLeft: 4, topRight: 4 },
                        borderSkipped: false,
                        stack: 'bookings',
                        order: 2,
                    },
                    {
                        type: 'bar',
                        label: 'Cancelled',
                        data: cancelled,
                        backgroundColor: colors.error,
                        borderRadius: { topLeft: 4, topRight: 4 },
                        borderSkipped: false,
                        stack: 'bookings',
                        order: 3,
                    },
                    {
                        type: 'line',
                        label: 'Confirmed Revenue (' + currency + ')',
                        data: revenue,
                        borderColor: colors.success,
                        backgroundColor: colors.success,
                        borderWidth: 2,
                        tension: 0.3,
                        pointRadius: windowDays === 7 ? 4 : 0,
                        pointHoverRadius: 6,
                        yAxisID: 'y1',
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: animDuration },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: window.innerWidth < 768 ? 'bottom' : 'top',
                        labels: {
                            color: colors.text,
                            boxWidth: 12,
                            boxHeight: 12,
                            padding: 12,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.92)',
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            title: function(items) {
                                var idx = items[0].dataIndex;
                                return formatFullDate(labels[idx]);
                            },
                            label: function(ctx) {
                                var idx = ctx.dataIndex;
                                var dsIndex = ctx.datasetIndex;
                                if (dsIndex === 0) {
                                    return ' Active: ' + activeBookings[idx] + ' bookings';
                                } else if (dsIndex === 1) {
                                    return ' Cancelled: ' + cancelled[idx] + ' bookings';
                                } else if (dsIndex === 2) {
                                    return ' Revenue: ' + currency + Number(revenue[idx]).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return ' ' + ctx.dataset.label + ': ' + ctx.formattedValue;
                            },
                            afterBody: function(items) {
                                var idx = items[0].dataIndex;
                                var b = bookings[idx] || 0;
                                return [' Total Bookings: ' + b];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: colors.grid },
                        ticks: {
                            color: colors.textMuted,
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: windowDays === 7 ? 7 : 10,
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: colors.grid },
                        ticks: {
                            color: colors.textMuted,
                            precision: 0,
                            font: { size: 11 }
                        },
                        title: {
                            display: false,
                            text: 'Bookings'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: colors.textMuted,
                            font: { size: 11 },
                            callback: function(val) {
                                return currency + compactNumber(val);
                            }
                        },
                        title: {
                            display: false,
                            text: 'Revenue'
                        }
                    }
                }
            }
        });

        // 2. Outcome Donut Chart
        var donutChart = null;
        if (donutCanvas) {
            var activeTotal = Math.max(0, totalBookings - totalCancelled);
            var donutData = totalBookings > 0
                ? [activeTotal, totalCancelled]
                : [1, 0];

            var donutColor = colors.success;
            if (cancelRate >= 20.0) {
                donutColor = colors.error;
            } else if (cancelRate >= 10.0) {
                donutColor = colors.warning;
            }

            var donutColors = totalBookings > 0
                ? [colors.primaryBg, donutColor]
                : [colors.border, colors.border];

            var centerTextPlugin = {
                id: 'perfCenterText',
                afterDraw: function(chart) {
                    var width = chart.width;
                    var height = chart.height;
                    var ctx = chart.ctx;
                    ctx.save();
                    var th = getThemeColors();

                    var rateText = totalBookings > 0 ? cancelRate.toFixed(1) + '%' : '0%';
                    ctx.font = 'bold 1.25rem -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                    ctx.fillStyle = totalBookings > 0 ? donutColor : th.textMuted;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(rateText, width / 2, height / 2 - 8);

                    ctx.font = '600 0.72rem -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                    ctx.fillStyle = th.textMuted;
                    ctx.fillText('Cancel Rate', width / 2, height / 2 + 14);
                    ctx.restore();
                }
            };

            donutChart = new Chart(donutCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Confirmed / Active', 'Cancelled'],
                    datasets: [{
                        data: donutData,
                        backgroundColor: donutColors,
                        borderWidth: 2,
                        borderColor: colors.border,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    animation: { duration: animDuration },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: colors.text,
                                boxWidth: 10,
                                boxHeight: 10,
                                padding: 10,
                                font: { size: 11 },
                                generateLabels: function() {
                                    return [
                                        {
                                            text: 'Active: ' + activeTotal,
                                            fillStyle: colors.primaryBg,
                                            strokeStyle: colors.border,
                                            lineWidth: 1
                                        },
                                        {
                                            text: 'Cancelled: ' + totalCancelled,
                                            fillStyle: donutColor,
                                            strokeStyle: colors.border,
                                            lineWidth: 1
                                        }
                                    ];
                                }
                            }
                        },
                        tooltip: {
                            enabled: totalBookings > 0,
                            callbacks: {
                                label: function(ctx) {
                                    var val = ctx.raw || 0;
                                    var pct = totalBookings > 0 ? Math.round((val / totalBookings) * 100) : 0;
                                    return ' ' + ctx.label + ': ' + val + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                },
                plugins: [centerTextPlugin]
            });
        }

        // Theme Toggle Observer: re-reads CSS variables and updates charts
        function updateChartsTheme() {
            var th = getThemeColors();
            if (comboChart) {
                comboChart.data.datasets[0].backgroundColor = th.primaryBg;
                comboChart.data.datasets[1].backgroundColor = th.error;
                comboChart.data.datasets[2].borderColor = th.success;
                comboChart.data.datasets[2].backgroundColor = th.success;
                if (comboChart.options.plugins.legend) {
                    comboChart.options.plugins.legend.labels.color = th.text;
                }
                comboChart.options.scales.x.grid.color = th.grid;
                comboChart.options.scales.x.ticks.color = th.textMuted;
                comboChart.options.scales.y.grid.color = th.grid;
                comboChart.options.scales.y.ticks.color = th.textMuted;
                comboChart.options.scales.y1.ticks.color = th.textMuted;
                comboChart.update('none');
            }
            if (donutChart) {
                var dColor = th.success;
                if (cancelRate >= 20.0) dColor = th.error;
                else if (cancelRate >= 10.0) dColor = th.warning;

                donutChart.data.datasets[0].backgroundColor = totalBookings > 0
                    ? [th.primaryBg, dColor]
                    : [th.border, th.border];
                donutChart.data.datasets[0].borderColor = th.border;
                if (donutChart.options.plugins.legend) {
                    donutChart.options.plugins.legend.labels.color = th.text;
                }
                donutChart.update('none');
            }
        }

        var themeObserver = new MutationObserver(function() {
            updateChartsTheme();
        });
        themeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPerfCharts);
    } else {
        initPerfCharts();
    }
})();
