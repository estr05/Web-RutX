import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Módulo de mapas RutX — Leaflet + OpenStreetMap vía npm/Vite (prohibido CDN).
 *
 * Inicializa automáticamente los contenedores [data-rutx-map] del DOM leyendo
 * markers/center/zoom desde data-attributes. Los marcadores son circleMarker
 * con colores semánticos de los tokens --rutx-status-* (success/warning/
 * error/unknown); el texto de los popups se inserta con textContent (sin
 * HTML) para evitar XSS con labels remotos.
 *
 * Exporta rutxMap y routeMarker para uso programático futuro.
 */
const STATUS_TOKENS = {
    active: '--rutx-status-success',
    delayed: '--rutx-status-warning',
    stopped: '--rutx-status-error',
    unknown: '--rutx-status-unknown',
};

const DEFAULT_CENTER = [20.6, -103.4];

/**
 * Lee un token CSS desde :root (getComputedStyle).
 */
function tokenValue(token) {
    return getComputedStyle(document.documentElement).getPropertyValue(token).trim();
}

/**
 * Crea el mapa Leaflet con tiles de OpenStreetMap (sin CDN de librerías).
 */
export function rutxMap(containerId, { center = DEFAULT_CENTER, zoom = 12 } = {}) {
    const map = L.map(containerId, { zoomControl: true });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    map.setView(center, zoom);

    return map;
}

/**
 * Marcador circleMarker con color semántico de tokens --rutx-status-*.
 */
export function routeMarker(lat, lon, { status = 'unknown', label = '' } = {}) {
    const token = STATUS_TOKENS[status] ?? STATUS_TOKENS.unknown;
    const fillColor = tokenValue(token) || tokenValue(STATUS_TOKENS.unknown);

    const marker = L.circleMarker([lat, lon], {
        radius: 8,
        color: tokenValue('--rutx-surface'),
        weight: 2,
        fillColor,
        fillOpacity: 1,
    });

    const popupContent = document.createElement('div');
    popupContent.className = 'rutx-map-popup';
    popupContent.textContent = label;

    marker.bindPopup(L.popup().setContent(popupContent));

    return marker;
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

function initMaps() {
    document.querySelectorAll('[data-rutx-map]').forEach((container) => {
        try {
            const markers = safeParse(container.dataset.markers, []);
            const center = safeParse(container.dataset.center, DEFAULT_CENTER);
            const zoom = Number(container.dataset.zoom ?? 12);

            const map = rutxMap(container.id, { center, zoom });

            markers.forEach((marker) => {
                routeMarker(
                    Number(marker.lat),
                    Number(marker.lon),
                    { status: marker.status ?? 'unknown', label: marker.label ?? '' },
                ).addTo(map);
            });
        } catch (error) {
            console.warn('No se pudo inicializar el mapa de RutX.', error);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMaps, { once: true });
} else {
    initMaps();
}

export { L };
