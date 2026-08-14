import { Chart } from 'chart.js';

/**
 * Módulo de gráficas RutX — Chart.js vía npm/Vite (prohibido CDN).
 *
 * Inicializa automáticamente los <canvas data-rutx-chart> del DOM leyendo
 * type/labels/datasets desde data-attributes (los componentes Blade son solo
 * HTML). La paleta se lee en runtime de los tokens CSS --rutx-chart-* vía
 * getComputedStyle; prohibido definir colores de serie fuera de chartBlue /
 * chartCyan / secondary (guidelines §5.2).
 *
 * Exporta rutxChartOptions y buildLineDataset para uso programático futuro
 * (p. ej. desde componentes Livewire).
 */
const CHART_COLOR_TOKENS = ['--rutx-chart-blue', '--rutx-chart-cyan'];

/**
 * Opciones base de Chart.js con la paleta centralizada de tokens.css.
 */
export function rutxChartOptions() {
    const palette = getComputedStyle(document.documentElement);
    const text = palette.getPropertyValue('--rutx-text').trim();
    const textMuted = palette.getPropertyValue('--rutx-text-muted').trim();
    const primary = palette.getPropertyValue('--rutx-primary').trim();

    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: text, font: { family: 'Inter' } },
            },
            tooltip: {
                backgroundColor: primary,
            },
        },
        scales: {
            x: { ticks: { color: textMuted } },
            y: { ticks: { color: textMuted } },
        },
    };
}

/**
 * Serializa un dataset del contrato (SalesSeriesResponse) a Chart.js.
 *
 * @param {Array}  series     Puntos: [valor, ...] o { points: [...] }.
 * @param {string} label      Etiqueta de la serie para la leyenda.
 * @param {string} colorToken Token CSS de la serie (--rutx-chart-*).
 */
export function buildLineDataset(series, label, colorToken = '--rutx-chart-blue') {
    const palette = getComputedStyle(document.documentElement);

    return {
        label,
        data: series.points ?? series,
        borderColor: palette.getPropertyValue(colorToken).trim(),
        backgroundColor: palette.getPropertyValue(`${colorToken}-bg`).trim(),
        fill: false,
        tension: 0,
    };
}

/**
 * Lee y valida un data-attribute con JSON; ante cualquier fallo usa fallback.
 */
function safeParse(raw, fallback) {
    if (typeof raw !== 'string' || raw === '') {
        return fallback;
    }

    try {
        return JSON.parse(raw);
    } catch {
        return fallback;
    }
}

function initCharts() {
    document.querySelectorAll('[data-rutx-chart]').forEach((canvas) => {
        try {
            const datasets = safeParse(canvas.dataset.datasets, []).map((dataset, index) => (
                buildLineDataset(
                    dataset.data ?? dataset,
                    dataset.label ?? '',
                    dataset.colorToken ?? CHART_COLOR_TOKENS[index % CHART_COLOR_TOKENS.length],
                )
            ));

            new Chart(canvas, {
                type: canvas.dataset.type ?? 'line',
                data: {
                    labels: safeParse(canvas.dataset.labels, []),
                    datasets,
                },
                options: rutxChartOptions(),
            });
        } catch (error) {
            console.warn('No se pudo inicializar la gráfica de RutX.', error);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts, { once: true });
} else {
    initCharts();
}

export { Chart };
