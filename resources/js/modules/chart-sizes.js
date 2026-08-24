/**
 * Lógica pura de dimensionado de gráficas RutX (sin dependencias de DOM ni
 * de Chart.js) para poder probarla con node:test sin navegador.
 *
 * Contrato:
 *  - computeChartMinWidth(labelCount, opts) devuelve null cuando el canvas
 *    debe ocupar el 100 % del contenedor, o un ancho mínimo en px cuando
 *    hay más etiquetas que las que caben legibles y el canvas es scrollable.
 */

export const MIN_WIDTH_PER_LABEL_PX = 90;

export const SCROLLABLE_LABEL_THRESHOLD = 12;

/**
 * Ancho mínimo requerido para que las etiquetas no se compriman.
 *
 * @param {number} labelCount Número de puntos/etiquetas del eje X.
 * @param {object}  [opts]
 * @param {boolean} [opts.scrollable] El canvas admite desplazamiento horizontal.
 * @param {number}  [opts.minWidthPerLabelPx] Px reservados por etiqueta.
 * @param {number}  [opts.threshold] Etiquetas toleradas antes de exigir ancho.
 * @returns {number|null} null → '100%' (responsive); número → px mínimos.
 */
export function computeChartMinWidth(
    labelCount,
    {
        scrollable = false,
        minWidthPerLabelPx = MIN_WIDTH_PER_LABEL_PX,
        threshold = SCROLLABLE_LABEL_THRESHOLD,
    } = {},
) {
    const count = Number(labelCount);

    if (!Number.isFinite(count) || count <= threshold || !scrollable) {
        return null;
    }

    return Math.ceil(count) * minWidthPerLabelPx;
}

/**
 * Restablece SIEMPRE el min-width del canvas antes de decidir: ningún filtro
 * previo puede dejar un ancho artificial heredado. Con labels suficientes y
 * modo scrollable fija px; en cualquier otro caso libera el ancho para que
 * vuelva al contenedor (100 %).
 *
 * @param {{ style: Record<string, string>, dataset: Record<string, string> }} canvas
 * @param {number} labelCount
 */
export function syncChartDimensions(canvas, labelCount) {
    if (!canvas || !canvas.style) {
        return;
    }

    canvas.style.minWidth = '';

    const required = computeChartMinWidth(labelCount, {
        scrollable: canvas.dataset?.scrollable === 'true',
    });

    if (required !== null) {
        canvas.style.minWidth = `${required}px`;
    }
}
