/**
 * assets/js/perf-chart.js
 * Performance Overview: Privacy-First Analytics Dashboard
 * Renders daily combo chart (Bookings volume bars by verified status + confirmed revenue line)
 * with dual axes, interactive tooltips, interval toggle, and outcome status donut chart.
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
                    'Interactive charts could not be loaded. Please refer to the detailed daily breakdown table below.' +
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
        var totalBookings = chartData.total_bookings || 0;
        var totalConfirmed = chartData.total_confirmed || 0;
        var totalPending = chartData.total_pending || 0;
        var totalCancelled = chartData.total_cancelled || 0;
        var totalExpired = chartData.total_expired || 0;
        var cancelRate = chartData.cancel_rate || 0.0;
        var revenue = chartData.revenue || [];
        var currency = chartData.currency || '₹';
        var windowDays = chartData.window_days || 30;

        // Daily series broken down by verified status
        var dailyConfirmed = chartData.confirmed || [];
        var dailyPending = chartData.pending || [];
        var dailyCancelled = chartData.cancelled || [];
        var dailyExpired = chartData.expired || [];

        // Check and toggle empty state display
        var emptyMsg = document.getElementById('perfTrendsEmpty');
        if (emptyMsg) {
            var hasActivity = totalBookings > 0 || revenue.some(function(v) { return v > 0; });
            emptyMsg.style.display = hasActivity ? 'none' : 'flex';
        }

        // Date formatting helpers
        function formatDateLabel(dStr) {
            if (!dStr) return '';
            var parts = dStr.split('-');
            if (parts.length < 3) return dStr;
            var dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            if (windowDays === 7) {
                return dt.toLocaleDateString('en-US', { weekday: 'short', day: '2-digit' });
            }
            return dt.toLocaleDateString('en-US', { day: '2-digit', month: 'short' });
        }

        function formatFullDate(dStr) {
            if (!dStr) return '';
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
                confirmed: isDark ? '#22c55e' : '#16a34a',
                pending: isDark ? '#f59e0b' : '#d97706',
                cancelled: isDark ? '#ef4444' : '#dc2626',
                expired: isDark ? '#94a3b8' : '#64748b',
                barBookings: isDark ? 'rgba(59, 130, 246, 0.72)' : 'rgba(59, 130, 246, 0.75)',
                revenueLine: isDark ? '#38bdf8' : '#1d4ed8',
                text: cs.getPropertyValue('--c-text').trim() || (isDark ? '#f8fafc' : '#0f172a'),
                textMuted: cs.getPropertyValue('--c-text-muted').trim() || (isDark ? '#94a3b8' : '#64748b'),
                border: cs.getPropertyValue('--c-border').trim() || (isDark ? '#334155' : '#e2e8f0'),
                grid: isDark ? 'rgba(255, 255, 255, 0.07)' : 'rgba(0, 0, 0, 0.06)',
            };
        }

        var isReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var animDuration = isReducedMotion ? 0 : 350;

        var formattedLabels = labels.map(formatDateLabel);
        var colors = getThemeColors();

        // 1. Combo Chart (Daily Stacked Volume Bars by Status + Confirmed Revenue Line)
        var comboDatasets = [
            {
                type: 'bar',
                label: 'Confirmed',
                data: dailyConfirmed,
                backgroundColor: colors.confirmed,
                borderRadius: { topLeft: 3, topRight: 3 },
                borderSkipped: false,
                stack: 'bookings',
                order: 2,
            },
            {
                type: 'bar',
                label: 'Pending',
                data: dailyPending,
                backgroundColor: colors.pending,
                borderRadius: { topLeft: 3, topRight: 3 },
                borderSkipped: false,
                stack: 'bookings',
                order: 3,
            },
            {
                type: 'bar',
                label: 'Cancelled',
                data: dailyCancelled,
                backgroundColor: colors.cancelled,
                borderRadius: { topLeft: 3, topRight: 3 },
                borderSkipped: false,
                stack: 'bookings',
                order: 4,
            }
        ];

        // Include expired if any exist in the dataset
        if (totalExpired > 0) {
            comboDatasets.push({
                type: 'bar',
                label: 'Expired',
                data: dailyExpired,
                backgroundColor: colors.expired,
                borderRadius: { topLeft: 3, topRight: 3 },
                borderSkipped: false,
                stack: 'bookings',
                order: 5,
            });
        }

        // Confirmed revenue line on right Y-axis
        comboDatasets.push({
            type: 'line',
            label: 'Confirmed Revenue (' + currency + ')',
            data: revenue,
            borderColor: colors.revenueLine,
            backgroundColor: colors.revenueLine,
            borderWidth: 2.2,
            tension: 0.25,
            pointRadius: windowDays === 7 ? 4 : (windowDays <= 30 ? 3 : 1),
            pointHoverRadius: 6,
            pointBackgroundColor: colors.revenueLine,
            pointBorderColor: '#ffffff',
            pointBorderWidth: 1.5,
            yAxisID: 'y1',
            order: 1,
        });

        var comboChart = new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: formattedLabels,
                datasets: comboDatasets
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
                        display: false // Replaced by high-hierarchy card header custom legend
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        padding: 10,
                        cornerRadius: 8,
                        boxPadding: 4,
                        callbacks: {
                            title: function(items) {
                                var idx = items[0].dataIndex;
                                return formatFullDate(labels[idx]);
                            },
                            label: function(ctx) {
                                var idx = ctx.dataIndex;
                                var ds = ctx.dataset;
                                if (ds.type === 'line') {
                                    return ' Revenue: ' + currency + Number(revenue[idx] || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                var val = ds.data[idx] || 0;
                                return ' ' + ds.label + ': ' + val;
                            },
                            afterBody: function(items) {
                                var idx = items[0].dataIndex;
                                var b = (chartData.bookings && chartData.bookings[idx]) || 0;
                                var c = dailyCancelled[idx] || 0;
                                var rate = b > 0 ? ((c / b) * 100).toFixed(1) + '%' : '0.0%';
                                return [
                                    ' ─────────────────',
                                    ' Total Bookings: ' + b,
                                    ' Cancel Rate: ' + rate
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { 
                            color: colors.grid,
                            borderDash: [3, 3]
                        },
                        ticks: {
                            color: colors.textMuted,
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: windowDays === 7 ? 7 : (windowDays <= 30 ? 10 : 12),
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { 
                            color: colors.grid,
                            borderDash: [3, 3]
                        },
                        ticks: {
                            color: colors.textMuted,
                            precision: 0,
                            font: { size: 11 }
                        },
                        title: {
                            display: true,
                            text: 'Bookings',
                            color: colors.textMuted,
                            font: { size: 11, weight: '600' }
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
                            display: true,
                            text: 'Revenue (' + currency + ')',
                            color: colors.textMuted,
                            font: { size: 11, weight: '600' }
                        }
                    }
                }
            }
        });

        // Interval aggregation handler (Daily vs Weekly)
        var intervalSelect = document.getElementById('perfIntervalSelect');
        if (intervalSelect) {
            intervalSelect.addEventListener('change', function() {
                var interval = this.value;
                if (interval === 'weekly') {
                    var weekLabels = [];
                    var weekConfirmed = [];
                    var weekPending = [];
                    var weekCancelled = [];
                    var weekExpired = [];
                    var weekRevenue = [];

                    for (var i = 0; i < labels.length; i += 7) {
                        var chunkEnd = Math.min(i + 6, labels.length - 1);
                        var wLabel = 'W' + (Math.floor(i / 7) + 1) + ' (' + formatDateLabel(labels[i]) + '-' + formatDateLabel(labels[chunkEnd]) + ')';
                        weekLabels.push(wLabel);

                        var sumC = 0, sumP = 0, sumX = 0, sumE = 0, sumR = 0;
                        for (var j = i; j <= chunkEnd; j++) {
                            sumC += (dailyConfirmed[j] || 0);
                            sumP += (dailyPending[j] || 0);
                            sumX += (dailyCancelled[j] || 0);
                            sumE += (dailyExpired[j] || 0);
                            sumR += (revenue[j] || 0);
                        }
                        weekConfirmed.push(sumC);
                        weekPending.push(sumP);
                        weekCancelled.push(sumX);
                        weekExpired.push(sumE);
                        weekRevenue.push(sumR);
                    }

                    comboChart.data.labels = weekLabels;
                    comboChart.data.datasets[0].data = weekConfirmed;
                    comboChart.data.datasets[1].data = weekPending;
                    comboChart.data.datasets[2].data = weekCancelled;
                    if (totalExpired > 0 && comboChart.data.datasets[3]) {
                        comboChart.data.datasets[3].data = weekExpired;
                    }
                    var revIdx = comboChart.data.datasets.length - 1;
                    comboChart.data.datasets[revIdx].data = weekRevenue;
                    comboChart.update();
                } else {
                    comboChart.data.labels = formattedLabels;
                    comboChart.data.datasets[0].data = dailyConfirmed;
                    comboChart.data.datasets[1].data = dailyPending;
                    comboChart.data.datasets[2].data = dailyCancelled;
                    if (totalExpired > 0 && comboChart.data.datasets[3]) {
                        comboChart.data.datasets[3].data = dailyExpired;
                    }
                    var revIdx2 = comboChart.data.datasets.length - 1;
                    comboChart.data.datasets[revIdx2].data = revenue;
                    comboChart.update();
                }
            });
        }

        // 2. Outcome Status Donut Chart
        var donutChart = null;
        if (donutCanvas) {
            var donutLabels = ['Confirmed', 'Pending', 'Cancelled'];
            var donutData = totalBookings > 0
                ? [totalConfirmed, totalPending, totalCancelled]
                : [1, 0, 0];
            var donutPalette = totalBookings > 0
                ? [colors.confirmed, colors.pending, colors.cancelled]
                : [colors.border, colors.border, colors.border];

            if (totalExpired > 0) {
                donutLabels.push('Expired');
                donutData.push(totalExpired);
                donutPalette.push(colors.expired);
            }

            var centerTextPlugin = {
                id: 'perfCenterText',
                afterDraw: function(chart) {
                    var width = chart.width;
                    var height = chart.height;
                    var ctx = chart.ctx;
                    ctx.save();
                    var th = getThemeColors();

                    var bText = totalBookings > 0 ? totalBookings.toLocaleString('en-US') : '0';
                    var cPct = totalBookings > 0 ? ((totalConfirmed / totalBookings) * 100).toFixed(1) + '%' : '0.0%';

                    // Center plugin Cancel Rate threshold reference:
                    // Cancel Rate: cancelRate.toFixed(1) + '%'

                    // Primary count in center
                    ctx.font = 'bold 1.45rem -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                    ctx.fillStyle = th.text;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(bText, width / 2, height / 2 - 12);

                    // Subtitle
                    ctx.font = '600 0.72rem -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                    ctx.fillStyle = th.textMuted;
                    ctx.fillText('Bookings', width / 2, height / 2 + 5);

                    // Confirmation share percentage
                    ctx.font = '700 0.8rem -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                    ctx.fillStyle = th.confirmed;
                    ctx.fillText(cPct, width / 2, height / 2 + 21);

                    ctx.restore();
                }
            };

            donutChart = new Chart(donutCanvas, {
                type: 'doughnut',
                data: {
                    labels: donutLabels,
                    datasets: [{
                        data: donutData,
                        backgroundColor: donutPalette,
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
                            display: false // Using custom accessible legend HTML below the canvas
                        },
                        tooltip: {
                            enabled: totalBookings > 0,
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleColor: '#ffffff',
                            bodyColor: '#e2e8f0',
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(ctx) {
                                    var val = ctx.raw || 0;
                                    var pct = totalBookings > 0 ? ((val / totalBookings) * 100).toFixed(1) : 0;
                                    return ' ' + ctx.label + ': ' + val + ' (' + pct + '%)';
                                },
                                afterLabel: function(ctx) {
                                    if (ctx.label === 'Cancelled') {
                                        return ' Cancel Rate: ' + cancelRate.toFixed(1) + '%';
                                    }
                                    return '';
                                }
                            }
                        }
                    }
                },
                plugins: [centerTextPlugin]
            });
        }

        // Theme Toggle Observer: re-reads CSS variables and live-updates charts
        function updateChartsTheme() {
            var th = getThemeColors();
            if (comboChart) {
                if (comboChart.data.datasets[0]) comboChart.data.datasets[0].backgroundColor = th.confirmed;
                if (comboChart.data.datasets[1]) comboChart.data.datasets[1].backgroundColor = th.pending;
                if (comboChart.data.datasets[2]) comboChart.data.datasets[2].backgroundColor = th.cancelled;
                var revIndex = comboChart.data.datasets.length - 1;
                comboChart.data.datasets[revIndex].borderColor = th.revenueLine;
                comboChart.data.datasets[revIndex].backgroundColor = th.revenueLine;
                comboChart.data.datasets[revIndex].pointBackgroundColor = th.revenueLine;

                comboChart.options.scales.x.grid.color = th.grid;
                comboChart.options.scales.x.ticks.color = th.textMuted;
                comboChart.options.scales.y.grid.color = th.grid;
                comboChart.options.scales.y.ticks.color = th.textMuted;
                comboChart.options.scales.y.title.color = th.textMuted;
                comboChart.options.scales.y1.ticks.color = th.textMuted;
                comboChart.options.scales.y1.title.color = th.textMuted;
                comboChart.update('none');
            }
            if (donutChart) {
                var palette = totalBookings > 0
                    ? [th.confirmed, th.pending, th.cancelled]
                    : [th.border, th.border, th.border];
                if (totalExpired > 0) {
                    palette.push(th.expired);
                }
                donutChart.data.datasets[0].backgroundColor = palette;
                donutChart.data.datasets[0].borderColor = th.border;
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
