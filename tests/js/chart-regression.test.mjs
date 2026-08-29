/**
 * Prueba de regresión del dimensionado de gráficas RutX (Chart.js).
 *
 * Ejecutar con: npm run test:js   (node --test tests/js/)
 *
 * Escenario clave: una gráfica con 12 puntos pasa a 1 punto (cambio de
 * filtros/polling) y el ancho debe VOLVER al contenedor ('100%', sin
 * min-width heredado). Cubre también el modo scrollable y la pureza de
 * computeChartMinWidth.
 */
import test from 'node:test';
import assert from 'node:assert/strict';

import {
    computeChartMinWidth,
    syncChartDimensions,
    MIN_WIDTH_PER_LABEL_PX,
} from '../../resources/js/modules/chart-sizes.js';

function fakeCanvas(scrollable = false) {
    return { style: {}, dataset: scrollable ? { scrollable: 'true' } : {} };
}

test('computeChartMinWidth: 12 puntos o menos ocupan el contenedor (null)', () => {
    assert.equal(computeChartMinWidth(12), null);
    assert.equal(computeChartMinWidth(1), null);
    assert.equal(computeChartMinWidth(0), null);
});

test('computeChartMinWidth: sin modo scrollable nunca exige px', () => {
    assert.equal(computeChartMinWidth(50), null);
    assert.equal(computeChartMinWidth(500), null);
});

test('computeChartMinWidth: scrollable con más puntos que el umbral exige px por etiqueta', () => {
    assert.equal(
        computeChartMinWidth(20, { scrollable: true }),
        20 * MIN_WIDTH_PER_LABEL_PX,
    );
});

/**
 * REGRESIÓN PRINCIPAL: 12 puntos → 1 punto; el ancho debe volver al
 * contenedor (min-width restablecido a ''), y viceversa al crecer de nuevo.
 */
test('regresión: cambiar de 12 puntos a 1 devuelve el ancho al contenedor', () => {
    const canvas = fakeCanvas();

    // Estado inicial con 12 puntos: responsive, sin min-width.
    syncChartDimensions(canvas, 12);
    assert.equal(canvas.style.minWidth, '');

    // Cambio de filtros: la serie se reduce a 1 punto tras un refresh.
    syncChartDimensions(canvas, 1);
    assert.equal(canvas.style.minWidth, '', 'ningún min-width artificial debe sobrevivir al refresco');

    // La serie vuelve a crecer: sigue siendo responsive (modo no-scrollable).
    syncChartDimensions(canvas, 30);
    assert.equal(canvas.style.minWidth, '');
});

/**
 * En modo scrollable el min-width SÍ se aplica, pero al reducir los puntos
 * se libera de nuevo (no queda ancho heredado del filtro anterior).
 */
test('scrollable: min-width aplicado y liberado al reducir los puntos', () => {
    const canvas = fakeCanvas(true);

    syncChartDimensions(canvas, 24);
    assert.equal(canvas.style.minWidth, `${24 * MIN_WIDTH_PER_LABEL_PX}px`);

    // De 24 puntos a 5: el ancho regresa al contenedor.
    syncChartDimensions(canvas, 5);
    assert.equal(canvas.style.minWidth, '');
});

test('syncChartDimensions tolera entradas degeneradas', () => {
    assert.doesNotThrow(() => syncChartDimensions(null, 10));
    assert.doesNotThrow(() => syncChartDimensions(undefined, 10));
    assert.doesNotThrow(() => syncChartDimensions({ style: {}, dataset: undefined }, NaN));

    // dataset ausente → nunca scrollable → ancho en contenedor.
    const orphan = { style: {} };
    syncChartDimensions(orphan, 30);
    assert.equal(orphan.style.minWidth, '');
});
