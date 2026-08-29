/**
 * Ciclo de vida de gráficas RutX bajo polling/cambios de filtro (Chart.js).
 *
 * Ejecutar con: npm run test:js   (node --test)
 *
 * Inyecta un doble de Chart vía setChartConstructorForTesting() para
 * ejercitar refreshRutxCharts SIN navegador:
 *  - La primera pasada crea UNA instancia por canvas.
 *  - Un refresco posterior (polling/filtros) ACTUALIZA la instancia existente
 *    en sitio (nunca la recrea) y sincroniza dimensiones.
 *  - Escenario de regresión: 12 puntos → 1 punto libera el min-width.
 *  - Barras y líneas conservan sus datos tras el refresco.
 *  - Canvas huérfanos (removidos del DOM por Livewire) se destruyen una vez.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    refreshRutxCharts,
    setChartConstructorForTesting,
} from '../../resources/js/modules/chart.js';
import { MIN_WIDTH_PER_LABEL_PX } from '../../resources/js/modules/chart-sizes.js';

/**
 * DOM mínimo en Node: rutxChartOptions() y build*Dataset() leen tokens vía
 * getComputedStyle(document.documentElement); valores vacíos son válidos.
 * Al importarse, el módulo aún no ve `document`, así que su auto-arranque
 * se omite y cada test controla el ciclo explícitamente.
 */
globalThis.document = { documentElement: {}, querySelectorAll: () => [] };
globalThis.getComputedStyle = () => ({ getPropertyValue: () => '' });

/**
 * Doble de Chart.js: cuenta construcciones (detecta duplicados), graba
 * updates y permite verificar datos/options vivos.
 */
class FakeChart {
    static constructed = [];

    constructor(canvas, config) {
        this.canvas = canvas;
        this.data = config.data;
        this.options = config.options;
        this.updates = [];
        this.destroyed = false;
        FakeChart.constructed.push(this);
    }

    update(mode) {
        this.updates.push(mode);
    }

    destroy() {
        this.destroyed = true;
    }
}

/** Aísla cada test: doble fresco y registro vacío. */
function useFakeChart() {
    FakeChart.constructed.length = 0;
    setChartConstructorForTesting(FakeChart);
}

function fakeCanvas({ type = 'line', points = 12, scrollable = false } = {}) {
    const labels = Array.from({ length: points }, (_, i) => `2026-08-${String(i + 1).padStart(2, '0')}`);
    const values = Array.from({ length: points }, (_, i) => (i + 1) * 100);

    return {
        isConnected: true,
        style: {},
        dataset: {
            type,
            format: 'currency',
            ...(scrollable ? { scrollable: 'true' } : {}),
            labels: JSON.stringify(labels),
            datasets: JSON.stringify([{ label: type === 'bar' ? 'Contado' : 'Venta', data: values }]),
        },
    };
}

test('ciclo de vida: la primera pasada crea una instancia por canvas', () => {
    useFakeChart();

    const canvas = fakeCanvas();
    const root = { querySelectorAll: () => [canvas] };

    refreshRutxCharts(root);

    assert.equal(FakeChart.constructed.length, 1);
    assert.equal(FakeChart.constructed[0].canvas, canvas);
    assert.equal(FakeChart.constructed[0].data.labels.length, 12);
});

test('regresión principal: 12 puntos → 1 punto reutiliza la instancia, libera el min-width y no duplica canvases', () => {
    useFakeChart();

    const canvas = fakeCanvas({ points: 12 });
    const root = { querySelectorAll: () => [canvas] };

    refreshRutxCharts(root);
    const original = FakeChart.constructed.at(-1);

    // Cambio de filtros/polling: la serie colapsa a 1 punto.
    canvas.dataset.labels = JSON.stringify(['2026-08-14']);
    canvas.dataset.datasets = JSON.stringify([{ label: 'Venta', data: [4200] }]);

    refreshRutxCharts(root, 'none');

    assert.equal(FakeChart.constructed.length, 1, 'no debe construirse una segunda instancia');
    assert.equal(FakeChart.constructed[0], original, 'misma instancia viva del mismo canvas');
    assert.deepEqual(original.data.labels, ['2026-08-14']);
    assert.deepEqual(original.data.datasets[0].data, [4200]);
    assert.ok(original.updates.includes('none'), 'update en modo none (sin animación ni recreate)');
    assert.equal(canvas.style.minWidth, '', 'ningún min-width artificial sobrevive al refresco');
});

test('scrollable en el ciclo de vida: fija px con muchas etiquetas y los libera al bajar a 1', () => {
    useFakeChart();

    const canvas = fakeCanvas({ points: 20, scrollable: true });
    const root = { querySelectorAll: () => [canvas] };

    refreshRutxCharts(root);

    assert.equal(FakeChart.constructed[0].canvas.style.minWidth, `${20 * MIN_WIDTH_PER_LABEL_PX}px`);

    canvas.dataset.labels = JSON.stringify(['2026-08-14']);
    canvas.dataset.datasets = JSON.stringify([{ label: 'Venta', data: [10] }]);

    refreshRutxCharts(root, 'none');

    assert.equal(FakeChart.constructed.length, 1);
    assert.equal(FakeChart.constructed[0].canvas.style.minWidth, '');
});

test('barras y líneas conviven y conservan sus datos tras el refresco', () => {
    useFakeChart();

    const linea = fakeCanvas({ type: 'line', points: 3 });
    const barras = fakeCanvas({ type: 'bar', points: 2 });
    const root = { querySelectorAll: () => [linea, barras] };

    refreshRutxCharts(root);
    assert.equal(FakeChart.constructed.length, 2);

    const instanciaLinea = FakeChart.constructed[0];
    const instanciaBarra = FakeChart.constructed[1];

    linea.dataset.labels = JSON.stringify(['lunes', 'martes']);
    linea.dataset.datasets = JSON.stringify([{ label: 'Venta', data: [7, 8] }]);
    barras.dataset.labels = JSON.stringify(['Ruta A', 'Ruta B', 'Ruta C']);
    barras.dataset.datasets = JSON.stringify([
        { label: 'Contado', data: [1, 2, 3] },
        { label: 'Crédito', data: [4, 5, 6] },
    ]);

    refreshRutxCharts(root, 'none');

    assert.equal(FakeChart.constructed.length, 2, 'sin instancias nuevas');
    assert.deepEqual(instanciaLinea.data.labels, ['lunes', 'martes']);
    assert.deepEqual(instanciaLinea.data.datasets[0].data, [7, 8]);
    assert.deepEqual(instanciaBarra.data.labels, ['Ruta A', 'Ruta B', 'Ruta C']);
    assert.deepEqual(instanciaBarra.data.datasets.map((d) => d.label), ['Contado', 'Crédito']);
});

test('canvas huérfano: se destruye una sola vez y no se recrea mientras siga fuera del DOM', () => {
    useFakeChart();

    const canvas = fakeCanvas();
    let enDom = [canvas];
    const root = { querySelectorAll: () => enDom };

    refreshRutxCharts(root);
    const instancia = FakeChart.constructed.at(-1);

    canvas.isConnected = false;

    enDom = [];
    refreshRutxCharts(root, 'none');

    assert.ok(instancia.destroyed, 'la instancia huérfana fue destruida');
    assert.equal(FakeChart.constructed.length, 1, 'y no se reconstruyó');
});
