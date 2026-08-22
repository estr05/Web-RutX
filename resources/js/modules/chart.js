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
 * WeakMap<HTMLCanvasElement, Chart> — rastrea instancias activas.
 * Permite destruir la instancia previa antes de re-crear sobre el mismo
 * nodo (idempotencia en re-renders de Livewire). WeakMap evita retener
 * referencias a nodos eliminados del DOM (sin memory leak).
 */
const chartInstances = new WeakMap();

/**
 * Formateo seguro de moneda para tooltips y ejes de Chart.js sin divisa hardcodeada.
 * Captura RangeError ante divisas inválidas y hace fallback a número plano.
 *
 * @param {number|string} value    Monto a formatear.
 * @param {string|null}   currency Código de divisa ISO 4217 (ej. 'MXN', 'USD').
 */
export function safeFormatCurrency(value, currency) {
    const absValue = Math.abs(Number(value));
    const isNegative = Number(value) < 0;
    let formatted;

    try {
        if (!currency || typeof currency !== 'string' || currency.trim() === '') {
            throw new Error('No currency');
        }
        
        // Format absolute value to get the currency symbol without the negative sign
        const parts = new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: currency.trim(),
        }).formatToParts(absValue);
        
        formatted = parts.map(p => p.value).join('');
    } catch {
        formatted = '$ ' + absValue.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Ensure space after $ if there isn't one
    if (formatted.startsWith('$') && !formatted.startsWith('$ ')) {
        formatted = formatted.replace('$', '$ ');
    }

    return isNegative ? `-${formatted}` : formatted;
}

/**
 * Opciones base de Chart.js con la paleta centralizada de tokens.css.
 *
 * @param {string|null} format   Formato de eje/tooltip (ej. 'currency').
 * @param {string|null} currency Código ISO de divisa pasado desde Blade/Livewire.
 */
export function rutxChartOptions(format = null, currency = null) {
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
                ...(format === 'currency' ? {
                    callbacks: {
                        label(context) {
                            const label = context.dataset.label ? `${context.dataset.label}: ` : '';
                            return `${label}${safeFormatCurrency(context.parsed.y, currency)}`;
                        },
                    },
                } : {}),
            },
        },
        scales: {
            x: { ticks: { color: textMuted } },
            y: {
                ticks: {
                    color: textMuted,
                    ...(format === 'currency' ? {
                        callback(value) {
                            return safeFormatCurrency(value, currency);
                        },
                    } : {}),
                },
            },
        },
    };
}

/**
 * Serializa un dataset del contrato (SalesSeriesResponse) a Chart.js (línea).
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
 * Serializa un dataset a Chart.js para gráfica de barras.
 *
 * @param {Array}  series     Puntos: [valor, ...] o { points: [...] }.
 * @param {string} label      Etiqueta de la serie para la leyenda.
 * @param {string} colorToken Token CSS de la serie (--rutx-chart-*).
 */
export function buildBarDataset(series, label, colorToken = '--rutx-chart-blue') {
    const palette = getComputedStyle(document.documentElement);
    const color = palette.getPropertyValue(colorToken).trim();
    const bgToken = `${colorToken}-bg`;
    const bg = palette.getPropertyValue(bgToken).trim() || color;

    return {
        label,
        data: series.points ?? series,
        backgroundColor: bg,
        borderColor: color,
        borderWidth: 1.5,
        borderRadius: 4,
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

/**
 * Re-inicializa todas las gráficas dentro de root.
 * Destruye la instancia Chart existente (vía WeakMap) antes de crear
 * una nueva — idempotencia ante re-renders de wire:poll.
 *
 * @param {Document|HTMLElement} root Raíz de búsqueda (document por defecto).
 */
export function refreshRutxCharts(root = document) {
    root.querySelectorAll('[data-rutx-chart]').forEach((canvas) => {
        // Destruir instancia previa si el canvas ya fue inicializado.
        if (chartInstances.has(canvas)) {
            chartInstances.get(canvas).destroy();
            chartInstances.delete(canvas);
        }

        try {
            const format = canvas.dataset.format || null;
            const currency = canvas.dataset.currency || null;

            const datasets = safeParse(canvas.dataset.datasets, []).map((dataset, index) => {
                const token = dataset.colorToken ?? CHART_COLOR_TOKENS[index % CHART_COLOR_TOKENS.length];
                const dataPoints = dataset.data ?? dataset;
                const label = dataset.label ?? '';

                return canvas.dataset.type === 'bar'
                    ? buildBarDataset(dataPoints, label, token)
                    : buildLineDataset(dataPoints, label, token);
            });

            const instance = new Chart(canvas, {
                type: canvas.dataset.type ?? 'line',
                data: {
                    labels: safeParse(canvas.dataset.labels, []),
                    datasets,
                },
                options: rutxChartOptions(format, currency),
            });

            // Registrar instancia para destrucción futura.
            chartInstances.set(canvas, instance);
        } catch (error) {
            console.warn('No se pudo inicializar la gráfica de RutX.', error);
        }
    });
}

/** Alias interno para la inicialización inicial del DOM. */
function initCharts() {
    refreshRutxCharts();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts, { once: true });
} else {
    initCharts();
}

/**
 * Evento personalizado que Alpine/Livewire despacha cuando el DOM con
 * gráficas ha sido re-renderizado por un ciclo de wire:poll.
 * En Blade: @this.dispatchTo('…') o Alpine $dispatch('rutx:refresh-charts').
 */
document.addEventListener('rutx:refresh-charts', () => refreshRutxCharts());

/**
 * Livewire 3 dispara 'livewire:navigated' al terminar de actualizar el DOM
 * en una navegación SPA. Re-inicializar por si la nueva página tiene gráficas.
 */
document.addEventListener('livewire:navigated', () => refreshRutxCharts());

export { Chart };
