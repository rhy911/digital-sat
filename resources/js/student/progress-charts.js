import Chart from 'chart.js/auto';
import annotationPlugin from 'chartjs-plugin-annotation';

Chart.register(annotationPlugin);

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Theme tokens aligned with DESIGN.md & home page
const THEME = {
    brand: '#3A52EE',
    brandHover: '#24367B',
    brandSoft: 'rgba(58, 82, 238, 0.12)',
    teal: '#0d9488',
    tealSoft: 'rgba(13, 148, 136, 0.16)',
    orange: '#ea580c',
    orangeSoft: 'rgba(234, 88, 12, 0.16)',
    emerald: '#059669',
    rose: '#e11d48',
    grid: 'rgba(226, 232, 240, 0.8)',
    textPrimary: '#0f172a',
    textMuted: '#64748b',
    tooltipBg: '#0f172a',
};

Chart.defaults.font.family = 'Roboto, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
Chart.defaults.color = THEME.textMuted;
Chart.defaults.scale.grid.color = THEME.grid;
Chart.defaults.animation = prefersReducedMotion ? false : { duration: 350, easing: 'easeOutQuart' };

let trendChartInstance = null;
let doughnutInstance = null;
let histogramInstance = null;

// Custom Chart.js Plugin: Draw vertical crosshair guide line on hover
const verticalGuidelinePlugin = {
    id: 'verticalGuideline',
    afterDraw(chart) {
        if (chart.tooltip?._active && chart.tooltip._active.length) {
            const activePoint = chart.tooltip._active[0];
            const { ctx } = chart;
            const { top, bottom } = chart.chartArea;
            const x = activePoint.element.x;

            ctx.save();
            ctx.beginPath();
            ctx.setLineDash([4, 4]);
            ctx.moveTo(x, top);
            ctx.lineTo(x, bottom);
            ctx.lineWidth = 1.5;
            ctx.strokeStyle = 'rgba(58, 82, 238, 0.3)';
            ctx.stroke();
            ctx.restore();
        }
    },
};

// Custom Chart.js Plugin: Draw score pill badges directly above Total Score points
const scoreDataLabelsPlugin = {
    id: 'scoreDataLabels',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        const totalDataset = chart.data.datasets[0];
        if (!totalDataset || !totalDataset.data || totalDataset.data.length === 0) return;

        const meta = chart.getDatasetMeta(0);
        if (!meta || !meta.data) return;

        const isManyPoints = totalDataset.data.length > 12;

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        meta.data.forEach((element, index) => {
            const val = totalDataset.data[index];
            if (val === null || val === undefined) return;

            const isHovered = chart.tooltip?._active?.some(a => a.index === index);

            // If many points, only render badge on hover to avoid clutter
            if (isManyPoints && !isHovered) return;

            const text = `${val}`;
            ctx.font = isHovered
                ? 'bold 12px "Plus Jakarta Sans", sans-serif'
                : '600 11px "Plus Jakarta Sans", sans-serif';

            const textMetrics = ctx.measureText(text);
            const badgeW = textMetrics.width + (isHovered ? 14 : 10);
            const badgeH = isHovered ? 22 : 18;
            const badgeX = element.x - badgeW / 2;
            const badgeY = element.y - (isHovered ? 26 : 22);

            // Pill Background
            ctx.fillStyle = isHovered ? '#0f172a' : 'rgba(255, 255, 255, 0.95)';
            ctx.strokeStyle = isHovered ? THEME.brand : '#cbd5e1';
            ctx.lineWidth = isHovered ? 2 : 1;

            ctx.beginPath();
            if (ctx.roundRect) {
                ctx.roundRect(badgeX, badgeY, badgeW, badgeH, 6);
            } else {
                ctx.rect(badgeX, badgeY, badgeW, badgeH);
            }
            ctx.fill();
            ctx.stroke();

            // Text
            ctx.fillStyle = isHovered ? '#ffffff' : '#0f172a';
            ctx.fillText(text, element.x, badgeY + badgeH / 2);
        });

        ctx.restore();
    },
};

export function initProgressCharts(chartConfig) {
    if (!chartConfig) return;

    initScoreTrendChart(chartConfig.trend);

    initRadarChart('chart-radar-rw', chartConfig.radar?.rw, {
        borderColor: THEME.teal,
        backgroundColor: THEME.tealSoft,
        pointBackgroundColor: THEME.teal,
    });

    initRadarChart('chart-radar-math', chartConfig.radar?.math, {
        borderColor: THEME.orange,
        backgroundColor: THEME.orangeSoft,
        pointBackgroundColor: THEME.orange,
    });

    initDoughnutChart(chartConfig.doughnut);
    initHistogramChart(chartConfig.histogram);
    initScrollSpy();
}

function initScoreTrendChart(trend) {
    const canvas = document.getElementById('chart-score-trend');
    if (!canvas || !trend || !trend.labels || trend.labels.length === 0) return;

    if (trendChartInstance) {
        trendChartInstance.destroy();
    }

    const isSinglePoint = trend.labels.length === 1;
    const annotations = {};

    if (trend.targetScore && trend.targetScore >= 400 && trend.targetScore <= 1600) {
        annotations.targetLine = {
            type: 'line',
            yMin: trend.targetScore,
            yMax: trend.targetScore,
            borderColor: THEME.rose,
            borderWidth: 2,
            borderDash: [5, 5],
            label: {
                display: true,
                content: `Target: ${trend.targetScore}`,
                position: 'end',
                backgroundColor: THEME.rose,
                color: '#ffffff',
                font: { size: 11, weight: '700' },
                padding: { top: 3, bottom: 3, left: 6, right: 6 },
                borderRadius: 4,
            },
        };
    }

    const ctx = canvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(58, 82, 238, 0.16)');
    gradient.addColorStop(1, 'rgba(58, 82, 238, 0.0)');

    trendChartInstance = new Chart(canvas, {
        type: 'line',
        data: {
            labels: trend.labels,
            datasets: [
                {
                    label: 'Total Score',
                    data: trend.total,
                    borderColor: THEME.brand,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.25,
                    fill: !isSinglePoint,
                    pointRadius: isSinglePoint ? 7 : 5,
                    pointHoverRadius: isSinglePoint ? 10 : 8,
                    pointHitRadius: 40,
                    pointBackgroundColor: THEME.brand,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointHoverBackgroundColor: THEME.brand,
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 3,
                },
                {
                    label: 'Reading & Writing',
                    data: trend.rw,
                    borderColor: THEME.teal,
                    borderWidth: 2,
                    borderDash: [4, 4],
                    tension: 0.25,
                    fill: false,
                    pointRadius: isSinglePoint ? 6 : 4,
                    pointHoverRadius: isSinglePoint ? 9 : 7,
                    pointHitRadius: 40,
                    pointBackgroundColor: THEME.teal,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    pointHoverBackgroundColor: THEME.teal,
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2.5,
                },
                {
                    label: 'Math',
                    data: trend.math,
                    borderColor: THEME.orange,
                    borderWidth: 2,
                    borderDash: [4, 4],
                    tension: 0.25,
                    fill: false,
                    pointRadius: isSinglePoint ? 6 : 4,
                    pointHoverRadius: isSinglePoint ? 9 : 7,
                    pointHitRadius: 40,
                    pointBackgroundColor: THEME.orange,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    pointHoverBackgroundColor: THEME.orange,
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2.5,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            // Axis 'x' + intersect false makes hover trigger effortlessly across the entire vertical slice
            interaction: {
                mode: 'index',
                intersect: false,
                axis: 'x',
            },
            hover: {
                mode: 'index',
                intersect: false,
                axis: 'x',
            },
            plugins: {
                // Disabled duplicate legend — HTML legend underneath is cleaner and richer
                legend: {
                    display: false,
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: THEME.tooltipBg,
                    titleColor: '#ffffff',
                    titleFont: { size: 12, weight: '750' },
                    bodyColor: '#f1f5f9',
                    bodyFont: { size: 12, weight: '500' },
                    padding: { top: 12, bottom: 12, left: 14, right: 14 },
                    cornerRadius: 10,
                    boxPadding: 5,
                    usePointStyle: true,
                    borderColor: 'rgba(255, 255, 255, 0.12)',
                    borderWidth: 1,
                    callbacks: {
                        title(items) {
                            const idx = items[0].dataIndex;
                            const pt = trend.points?.[idx];
                            const dateStr = pt?.date || items[0].label;
                            return pt?.title ? `${pt.title} · ${dateStr}` : `Test Attempt · ${dateStr}`;
                        },
                        label(context) {
                            const val = context.parsed.y;
                            if (val === null || val === undefined) return '';
                            const dsLabel = context.dataset.label;
                            if (dsLabel === 'Total Score') {
                                return `  Total Score: ${val} / 1600`;
                            }
                            if (dsLabel === 'Reading & Writing') {
                                return `  Reading & Writing: ${val} / 800`;
                            }
                            if (dsLabel === 'Math') {
                                return `  Math: ${val} / 800`;
                            }
                            return `  ${dsLabel}: ${val}`;
                        },
                        afterBody(items) {
                            const idx = items[0].dataIndex;
                            const total = trend.total[idx];
                            const target = trend.targetScore;
                            const lines = [];

                            // Delta vs previous attempt
                            if (idx > 0 && trend.total[idx - 1] !== null && total !== null) {
                                const diff = total - trend.total[idx - 1];
                                const sign = diff >= 0 ? '+' : '';
                                lines.push(`  vs Previous Test: ${sign}${diff} pts`);
                            }

                            // Delta vs target goal
                            if (target && total !== null) {
                                const targetDiff = total - target;
                                const sign = targetDiff >= 0 ? '+' : '';
                                lines.push(`  vs Target Goal (${target}): ${sign}${targetDiff} pts`);
                            }

                            return lines;
                        },
                    },
                },
                annotation: {
                    annotations,
                },
            },
            scales: {
                y: {
                    min: 400,
                    max: 1600,
                    ticks: {
                        stepSize: 200,
                        font: { size: 11 },
                    },
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } },
                },
            },
        },
        plugins: [verticalGuidelinePlugin, scoreDataLabelsPlugin],
    });
}

export function updateTrendRange(allData, rangeFilter, targetScore) {
    if (!allData || !allData.points || !trendChartInstance) return;

    let points = [...allData.points];
    const now = new Date();

    if (rangeFilter === 'last_5') {
        points = points.slice(-5);
    } else if (rangeFilter === 'last_10') {
        points = points.slice(-10);
    } else if (rangeFilter === 'this_month') {
        const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
        points = points.filter(p => p.date && new Date(p.date) >= startOfMonth);
        if (points.length === 0) {
            points = allData.points.slice(-5);
        }
    }

    const filtered = {
        labels: points.map(p => {
            if (!p.date) return '#';
            const d = new Date(p.date);
            return isNaN(d.getTime()) ? p.date : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        total: points.map(p => p.total),
        rw: points.map(p => p.rw),
        math: points.map(p => p.math),
        points: points,
        targetScore: targetScore ?? allData.targetScore,
    };

    initScoreTrendChart(filtered);
}

// Convert long domain strings into clean multi-line label arrays so radar labels don't collide
function formatRadarLabels(labels) {
    if (!labels) return [];
    return labels.map(label => {
        if (Array.isArray(label)) return label;
        if (label.includes('Information & Ideas') || label.includes('Information and Ideas')) {
            return ['Information &', 'Ideas'];
        }
        if (label.includes('Craft & Structure') || label.includes('Craft and Structure')) {
            return ['Craft &', 'Structure'];
        }
        if (label.includes('Standard English Conventions')) {
            return ['Standard English', 'Conventions'];
        }
        if (label.includes('Expression of Ideas')) {
            return ['Expression of', 'Ideas'];
        }
        if (label.includes('Problem-Solving')) {
            return ['Problem-Solving', '& Data Analysis'];
        }
        if (label.includes('Advanced Math')) {
            return ['Advanced', 'Math'];
        }
        if (label.includes('Geometry') || label.includes('Trig')) {
            return ['Geometry &', 'Trigonometry'];
        }
        if (label.includes('Spatial')) {
            return ['Spatial &', 'Measurement'];
        }
        return label;
    });
}

function initRadarChart(canvasId, radarData, colors) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !radarData || !radarData.labels) return;

    const formattedLabels = formatRadarLabels(radarData.labels);

    new Chart(canvas, {
        type: 'radar',
        data: {
            labels: formattedLabels,
            datasets: [
                {
                    data: radarData.data,
                    borderColor: colors.borderColor,
                    backgroundColor: colors.backgroundColor,
                    pointBackgroundColor: colors.pointBackgroundColor,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 14,
                    bottom: 14,
                    left: 24,
                    right: 24,
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: THEME.tooltipBg,
                    padding: 8,
                    cornerRadius: 6,
                    callbacks: {
                        label: (ctx) => ` Accuracy: ${ctx.raw}%`,
                    },
                },
            },
            scales: {
                r: {
                    min: 0,
                    max: 100,
                    ticks: {
                        stepSize: 25,
                        backdropColor: 'transparent',
                        color: THEME.textMuted,
                        font: { size: 10 },
                    },
                    grid: {
                        color: THEME.grid,
                    },
                    angleLines: {
                        color: THEME.grid,
                    },
                    pointLabels: {
                        font: { size: 11, weight: '600' },
                        color: THEME.textPrimary,
                        padding: 8,
                    },
                },
            },
        },
    });
}

function initDoughnutChart(doughnut) {
    const canvas = document.getElementById('chart-doughnut-split');
    if (!canvas || !doughnut || !doughnut.data) return;

    if (doughnutInstance) {
        doughnutInstance.destroy();
    }

    doughnutInstance = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: doughnut.labels,
            datasets: [
                {
                    data: doughnut.data,
                    backgroundColor: [THEME.teal, THEME.orange],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        font: { size: 11, weight: '600' },
                        color: THEME.textPrimary,
                        padding: 10,
                    },
                },
                tooltip: {
                    backgroundColor: THEME.tooltipBg,
                    padding: 8,
                    cornerRadius: 6,
                    callbacks: {
                        label(ctx) {
                            return ` ${ctx.label}: ${ctx.raw}% accuracy`;
                        },
                    },
                },
            },
        },
    });
}

function initHistogramChart(histogram) {
    const canvas = document.getElementById('chart-time-histogram');
    if (!canvas || !histogram || !histogram.labels) return;

    if (histogramInstance) {
        histogramInstance.destroy();
    }

    histogramInstance = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: histogram.labels,
            datasets: [
                {
                    label: 'Correct',
                    data: histogram.correct,
                    backgroundColor: THEME.emerald,
                    borderRadius: 4,
                    maxBarThickness: 38,
                },
                {
                    label: 'Incorrect',
                    data: histogram.incorrect,
                    backgroundColor: THEME.rose,
                    borderRadius: 4,
                    maxBarThickness: 38,
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
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 10,
                        boxHeight: 10,
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        font: { size: 11, weight: '600' },
                        color: THEME.textPrimary,
                        padding: 12,
                    },
                },
                tooltip: {
                    backgroundColor: THEME.tooltipBg,
                    padding: 8,
                    cornerRadius: 6,
                    callbacks: {
                        label(ctx) {
                            return ` ${ctx.dataset.label}: ${ctx.raw} questions`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false },
                    ticks: { font: { size: 11 } },
                    title: {
                        display: true,
                        text: 'Time spent per question',
                        color: THEME.textMuted,
                        font: { size: 11 },
                    },
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: { stepSize: 5, font: { size: 11 } },
                    title: {
                        display: true,
                        text: 'Questions count',
                        color: THEME.textMuted,
                        font: { size: 11 },
                    },
                },
            },
        },
    });
}

// Interactive Scroll-Spy: updates active navigation tab as user scrolls
function initScrollSpy() {
    const navLinks = document.querySelectorAll('.progress-nav-link');
    const sections = document.querySelectorAll('.progress-section');
    if (!navLinks.length || !sections.length) return;

    // Detect if page is scrolled inside .shell-content or window
    const scrollContainer = document.querySelector('.shell-content');

    const observerOptions = {
        root: scrollContainer || null,
        rootMargin: '-10% 0px -70% 0px',
        threshold: 0,
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const targetId = entry.target.getAttribute('id');
                navLinks.forEach((link) => {
                    const href = link.getAttribute('href')?.replace('#', '');
                    if (href === targetId) {
                        link.classList.add('is-active');
                    } else {
                        link.classList.remove('is-active');
                    }
                });
            }
        });
    }, observerOptions);

    sections.forEach((section) => observer.observe(section));
}

window.ProgressCharts = {
    init: initProgressCharts,
    updateTrendRange: updateTrendRange,
};

document.addEventListener('DOMContentLoaded', () => {
    if (window.PROGRESS_CHARTS_CONFIG) {
        initProgressCharts(window.PROGRESS_CHARTS_CONFIG);
    }
});
