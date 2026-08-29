import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { syncChartDimensions } from './chart-sizes.js';

// Chart.js v4 no registra nada por defecto al importar desde 'chart.js':
// sin este registro el constructor lanza "<tipo> is not a registered
// controller" y el catch deja el canvas vacío pese a tener datos.
Chart.register(
    BarController,
    BarElement,
    CategoryScale,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
);

/**
 * Módulo de gráficas RutX — Chart.js vía npm/Vite (prohibido CDN).
 *
 * Inicializa automáticamente los <canvas data-rutx-chart> del DOM leyendo
 * type/labels/datasets desde data-attributes (los componentes Blade son solo
 * HTML). La paleta se lee en runtime de los tokens CSS --rutx-chart-* vía
 * getComputedStyle; prohibido definir colores de serie fuera de chartBlue /
 * chartCyan / secondary (guidelines §5.2).
 *
 * Ciclo de vida con Livewire (v4):
 *  - Re-render del MISMO canvas → se actualiza data/options en sitio y se
 *    llama instance.update('none') en polling: nunca destroy() + recreate()
 *    por ciclo de wire:poll.
 *  - Canvas que Livewire eliminó del DOM → la instancia se destruye una sola
 *    vez en el barrido de huérfanos (compatible sin hooks específicos de
 *    versión; el hook 'commit' solo refresca tras el morph).
 *  - syncChartDimensions restablece el min-width en cada refresco: ningún
 *    filtro previo deja un ancho heredado que rompa el layout o provoque
 *    overflow del body.
 *
 * Exporta rutxChartOptions y buildLineDataset para uso programático futuro
 * (p. ej. desde componentes Livewire).
 */
const CHART_COLOR_TOKENS = ['--rutx-chart-blue', '--rutx-chart-cyan'];

/**
 * Constructor usado por refreshRutxCharts. Existe como variable mutable SOLO
 * para permitir inyectar un doble de Chart en los tests de ciclo de vida
 * (tests/js/chart-lifecycle.test.mjs) sin navegador ni flags experimentales
 * de Node; en producción nadie llama al setter y se usa Chart directamente.
 */
let chartConstructor = Chart;

export function setChartConstructorForTesting(constructor) {
    chartConstructor = constructor;
}

/**
 * Map<HTMLCanvasElement, Chart> — registro iterable de instancias activas.
 * A diferencia de WeakMap permite el barrido que destruye instancias cuyos
 * canvases ya no están conectados al DOM.
 */
const chartInstances = new Map();

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
            x: {
                ticks: {
                    color: textMuted,
                    autoSkip: true,
                    maxRotation: 0,
                },
            },
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
 * Construye labels/datasets/options desde los data-attributes del canvas.
 */
function readChartConfig(canvas) {
    const format = canvas.dataset.format || null;
    const currency = canvas.dataset.currency || null;
    const labels = safeParse(canvas.dataset.labels, []);

    const datasets = safeParse(canvas.dataset.datasets, []).map((dataset, index) => {
        const token = dataset.colorToken ?? CHART_COLOR_TOKENS[index % CHART_COLOR_TOKENS.length];
        const dataPoints = dataset.data ?? dataset;
        const label = dataset.label ?? '';

        return canvas.dataset.type === 'bar'
            ? buildBarDataset(dataPoints, label, token)
            : buildLineDataset(dataPoints, label, token);
    });

    return {
        type: canvas.dataset.type ?? 'line',
        labels,
        datasets,
        options: rutxChartOptions(format, currency),
    };
}

/**
 * Destruye las instancias cuyos canvas ya no pertenecen al DOM (Livewire los
 * removió durante un morph/navegación). Único punto de destroy(): las gráficas
 * vivas jamás se destruyen en un refresh.
 */
export function sweepDetachedCharts() {
    for (const [canvas, instance] of chartInstances) {
        if (!canvas.isConnected) {
            instance.destroy();
            chartInstances.delete(canvas);
        }
    }
}

/**
 * Refresca todas las gráficas dentro de root.
 *
 * Si el canvas ya tiene instancia viva, se actualiza EN SITIO (labels,
 * datasets, options) y se llama update(mode): con mode='none' el ciclo de
 * polling no recrea la gráfica ni dispara animaciones. Solo se crea una
 * instancia nueva cuando el canvas no tenía ninguna.
 *
 * @param {Document|HTMLElement} root Raíz de búsqueda (document por defecto).
 * @param {'default'|'none'} [mode] Modo de update de Chart.js.
 */
export function refreshRutxCharts(root = document, mode = 'default') {
    sweepDetachedCharts();

    root.querySelectorAll('[data-rutx-chart]').forEach((canvas) => {
        try {
            const config = readChartConfig(canvas);
            const existing = chartInstances.get(canvas);

            if (existing && existing.canvas === canvas) {
                existing.data.labels = config.labels;
                existing.data.datasets = config.datasets;
                existing.options = config.options;
                syncChartDimensions(canvas, config.labels.length);
                existing.update(mode);

                return;
            }

            // Dimensionar antes de crear evita el primer frame con ancho erróneo.
            syncChartDimensions(canvas, config.labels.length);

            const instance = new chartConstructor(canvas, {
                type: config.type,
                data: {
                    labels: config.labels,
                    datasets: config.datasets,
                },
                options: config.options,
            });

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

/**
 * Integración con el ciclo de vida de Livewire/Alpine. Guardada detrás de
 * comprobaciones de entorno para poder importar este módulo en Node (tests).
 */
export function setupChartLifecycle() {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('rutx:refresh-charts', () => refreshRutxCharts());

    /**
     * Livewire dispara 'livewire:navigated' al terminar la navegación SPA.
     */
    document.addEventListener('livewire:navigated', () => refreshRutxCharts());

    /**
     * Livewire v3/v4: tras cada commit exitoso el DOM ya fue actualizado.
     * Refresco en modo 'none' — sin animaciones ni recreación de instancias.
     * El barrido interno destruye únicamente instancias huérfanas.
     */
    const livewire = typeof window !== 'undefined' ? window.Livewire : undefined;

    if (livewire && typeof livewire.hook === 'function') {
        livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                queueMicrotask(() => refreshRutxCharts(document, 'none'));
            });
        });
    }
}

setupChartLifecycle();

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts, { once: true });
    } else {
        initCharts();
    }
}

export { Chart };
