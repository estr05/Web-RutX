# Maqueta — Consultas del Sincronizador y API v2 para Agenda, Asignación y Agrupación

**Versión:** 0.1 · **Fecha:** 13 de agosto de 2026 · **Estado:** Maqueta para validar con el equipo
**Ámbito:** Alcanzables **agenda**, **asignación** y **agrupación** definidos en los documentos de `Docs/`.
**Documentos que gobiernan este archivo:** `Contrato y Nomenclatura - API exclusiva RutX Web v2.md` (endpoints, envelopes, JSON), `Plan de Estructura y Desarrollo Visual...` (vista Ruta · Agenda), `Plan de Sprints...` (orden), `consultas_interactivas.sql` (diagnóstico real sobre Microsip).

---

## 0. Reglas que la maqueta respeta

| Regla | Aplicación aquí |
|---|---|
| Microsip es fuente maestra de **catálogos** (clientes, zonas, rutas) y de **ventas/frecuencia**; la web no abre Firebird. | El Sincro ejecuta el SQL de secciones 1–3 contra `COYATOC.FDB`. |
| La **BD complementaria** es la fuente de verdad de la **agenda** (asignaciones vendedor→día→cliente). Su esquema se aprueba aparte; aquí se maqueta el SQL que la leerá. | Sección 4, tabla `agendas`. |
| Todo endpoint web vive bajo `/api/v2/web/*`, JSON `snake_case`, envelope `data / meta / filters / trace_id`. | Secciones 5–7. |
| Comandos llevan `Idempotency-Key` y versionado de agenda (`schedule_version`) → `409 SCHEDULE_VERSION_CONFLICT`. | Sección 6. |

Ventana de análisis base: **últimos 2 meses** (`DATEADD(-2 MONTH TO CURRENT_DATE)`). Ruta piloto: **RUTA01** (`VENDEDOR_ID = 3572`).

---

## 1. ALCANZABLE — AGRUPACIÓN (clientes por zonas)

**Objetivo de negocio:** ubicar clientes por zona; las zonas alimentan el filtro cascada *Zona → Ruta* del tablero y la futura vista Configuración · Zonas.

### 1.1 Consultas del Sincronizador (Microsip / Firebird)

```sql
-- A1. Catálogo de zonas activas (en Coyatoc: LUNES..SABADO, es decir, los días de visita)
SELECT Z.ZONA_CLIENTE_ID AS zone_id,
       Z.NOMBRE          AS name,
       Z.ES_PREDET       AS is_default
FROM ZONAS_CLIENTES Z
WHERE Z.OCULTO = 'N'
ORDER BY Z.NOMBRE;

-- A2. Carga de clientes por zona × ruta (para el contador del tablero y el filtro cascada)
SELECT Z.ZONA_CLIENTE_ID      AS zone_id,
       Z.NOMBRE               AS zone_name,
       V.VENDEDOR_ID          AS route_id,
       V.NOMBRE               AS route_name,
       COUNT(C.CLIENTE_ID)    AS assigned_customers
FROM ZONAS_CLIENTES Z
LEFT JOIN CLIENTES C    ON C.ZONA_CLIENTE_ID = Z.ZONA_CLIENTE_ID AND C.ESTATUS = 'A'
LEFT JOIN VENDEDORES V  ON V.VENDEDOR_ID     = C.VENDEDOR_ID
WHERE Z.OCULTO = 'N'
GROUP BY Z.ZONA_CLIENTE_ID, Z.NOMBRE, V.VENDEDOR_ID, V.NOMBRE
ORDER BY Z.NOMBRE, V.NOMBRE;
```

### 1.2 Envío a la web (API)

El catálogo de zonas **no abre endpoint nuevo**: se entrega como opciones disponibles dentro de `GET /api/v2/web/agendas` para el filtro cascada (decisión de maqueta a validar).

```json
{
  "data": {
    "schedule_version": 42,
    "options": {
      "zones": [
        { "zone_id": 3771, "name": "Lunes",    "is_default": false },
        { "zone_id": 3772, "name": "Martes",   "is_default": false },
        { "zone_id": 3773, "name": "Miercoles", "is_default": false }
      ],
      "routes": [
        { "route_id": 3572, "name": "RUTA01", "zone_id": null },
        { "route_id": 3573, "name": "RUTA02", "zone_id": null }
      ]
    }
  },
  "meta": { "page": 1, "per_page": 7, "total": 7, "last_page": 1 },
  "filters": { "zone_id": null, "route_id": null, "date_from": "2026-08-17", "date_to": "2026-08-23" },
  "trace_id": "01J8..."
}
```

---

## 2. ALCANZABLE — ASIGNACIÓN (vendedor → cliente / día)

**Objetivo de negocio:** decidir qué vendedor atiende a cada cliente y qué día. En Coyatoc hoy eso vive en Microsip (`CLIENTES.VENDEDOR_ID` + `ZONA_CLIENTE_ID`), pero la agenda que controla la descarga diaria de la app vivirá en la BD complementaria. Las consultas de esta sección le dan al Sincro (y a la oficina vía API) el **patrón real** para validar o reasignar.

### 2.1 Consultas del Sincronizador (Microsip / Firebird) — base `consultas_interactivas.sql`

```sql
-- B1. Asignación actual del catálogo: cliente → ruta + día asignado
SELECT C.CLIENTE_ID      AS customer_id,
       C.CLAVE_CLIENTE   AS customer_code,
       C.NOMBRE          AS customer_name,
       V.VENDEDOR_ID     AS route_id,
       V.NOMBRE          AS route_name,
       Z.ZONA_CLIENTE_ID AS zone_id,
       Z.NOMBRE          AS assigned_day
FROM CLIENTES C
LEFT JOIN VENDEDORES V  ON V.VENDEDOR_ID = C.VENDEDOR_ID
LEFT JOIN ZONAS_CLIENTES Z ON Z.ZONA_CLIENTE_ID = C.ZONA_CLIENTE_ID
WHERE C.ESTATUS = 'A';

-- B2. Frecuencia real de compra por cliente (últimos 2 meses)
--     Alimenta weekly_frequency y el estatus regular/eventual del tablero.
SELECT P.CLIENTE_ID                       AS customer_id,
       COUNT(*)                           AS purchases,
       COUNT(DISTINCT P.FECHA)            AS days_with_purchase,
       COUNT(DISTINCT EXTRACT(WEEKDAY FROM P.FECHA)) AS weekdays,
       SUM(P.IMPORTE_NETO)                AS amount
FROM DOCTOS_PV P
WHERE P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
GROUP BY P.CLIENTE_ID;

-- B3. Días de la semana en que compra cada cliente (últimos 2 meses)
--     Forma el patrón de 7 círculos de la tarjeta del cliente.
SELECT P.CLIENTE_ID AS customer_id,
       EXTRACT(WEEKDAY FROM P.FECHA) AS weekday,
       COUNT(*)     AS purchases
FROM DOCTOS_PV P
WHERE P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
GROUP BY P.CLIENTE_ID, EXTRACT(WEEKDAY FROM P.FECHA)
ORDER BY P.CLIENTE_ID, EXTRACT(WEEKDAY FROM P.FECHA);

-- B4. Mezclas: clientes de una cartera con ventas registradas a otra ruta (últimos 2 meses)
--     Detección de ventas cruzadas para decidir reasignaciones.
SELECT P.CLIENTE_ID                 AS customer_id,
       C.NOMBRE                     AS customer_name,
       C.VENDEDOR_ID                AS catalog_route_id,
       VC.NOMBRE                    AS catalog_route,
       P.VENDEDOR_ID                AS selling_route_id,
       VS.NOMBRE                    AS selling_route,
       COUNT(*)                     AS docs,
       SUM(P.IMPORTE_NETO)          AS amount
FROM DOCTOS_PV P
JOIN CLIENTES C   ON C.CLIENTE_ID   = P.CLIENTE_ID
JOIN VENDEDORES VC ON VC.VENDEDOR_ID = C.VENDEDOR_ID
JOIN VENDEDORES VS ON VS.VENDEDOR_ID = P.VENDEDOR_ID
WHERE P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
  AND C.VENDEDOR_ID <> P.VENDEDOR_ID
GROUP BY P.CLIENTE_ID, C.NOMBRE, C.VENDEDOR_ID, VC.NOMBRE, P.VENDEDOR_ID, VS.NOMBRE
ORDER BY amount DESC;
```

### 2.2 Envío a la web (API)

**Cliente sin agenda** (panel izquierdo del tablero) — `GET /api/v2/web/agendas/unassigned-customers`

```json
{
  "data": [
    {
      "customer_id": 123,
      "code": "CLI-0123",
      "name": "SAN ANTONIO JAMONES Y SALCHICHAS",
      "route_id": 3572,
      "route_name": "RUTA01",
      "assigned_day": "Jueves",
      "weekly_frequency": { "L": 3, "M": 2, "X": 1, "J": 4, "V": 2, "S": 1, "D": 0 },
      "status": "regular"
    }
  ],
  "meta": { "page": 1, "per_page": 25, "total": 7, "last_page": 1 },
  "filters": { "search": "", "zone_id": null, "route_id": 3572 },
  "trace_id": "01J9..."
}
```

**Guardar movimientos del tablero (asignar / mover / quitar)** — `PATCH /api/v2/web/agendas/assignments:batch`

Request:
```json
{
  "schedule_version": 42,
  "assignments": [
    { "customer_id": 123, "seller_id": 3572, "agenda_date": "2026-08-17", "action": "assign" },
    { "customer_id": 456, "seller_id": 3574, "agenda_date": "2026-08-18", "action": "move" },
    { "customer_id": 789, "seller_id": null, "agenda_date": null,        "action": "remove" }
  ]
}
```
Headers: `Idempotency-Key: 7d8f...` · `Content-Type: application/json`

Response:
```json
{
  "data": {
    "schedule_version": 43,
    "results": [
      { "customer_id": 123, "action": "assign", "agenda_date": "2026-08-17", "status": "assigned" },
      { "customer_id": 456, "action": "move",   "agenda_date": "2026-08-18", "status": "moved" },
      { "customer_id": 789, "action": "remove", "agenda_date": null,         "status": "removed" }
    ]
  },
  "trace_id": "01JA..."
}
```
Errores de contrato: `422 VALIDATION_ERROR` (customer/seller inexistente, fecha fuera de rango), `409 SCHEDULE_VERSION_CONFLICT` (versión desactualizada).

---

## 3. ALCANZABLE — AGENDA (tablero semanal: días → vendedor → clientes)

**Objetivo de negocio:** el tablero de la vista **Ruta · Agenda** responde "¿quién sale cada día y a cuántos clientes atiende?"; la descarga matutina de la app usa esta agenda para bajar el paquete del día.

### 3.1 Consultas del Sincronizador (BD complementaria + join a Microsip)

Esquema de maqueta de la tabla (la aprobación del esquema real es trabajo aparte):

```text
agendas (
  id            BIGINT  PK,
  agenda_date   DATE,            -- día programado
  seller_id     INT,             -- vendedor/ruta
  customer_id   INT,             -- cliente
  zone_id       INT,             -- zona del día (derivada del día)
  active        BOOLEAN,
  created_by    VARCHAR,
  created_at    TIMESTAMP,
  updated_by    VARCHAR,
  updated_at    TIMESTAMP
)
```

```sql
-- C1. Asignaciones de la semana (BD complementaria)
SELECT a.id, a.agenda_date, a.seller_id, a.customer_id, a.zone_id
FROM agendas a
WHERE a.agenda_date BETWEEN @date_from AND @date_to
  AND a.active = TRUE
  AND (@zone_id  IS NULL OR a.zone_id    = @zone_id)
  AND (@route_id IS NULL OR a.seller_id  = @route_id)
ORDER BY a.agenda_date, a.seller_id;

-- C2. Unión con Microsip para nombres (el Sincro resuelve los nombres antes de responder)
--     SELECT c.customer_id, c.code, c.name, v.seller_name ...
--     FROM (C1) JOIN CLIENTES c ... JOIN VENDEDORES v ...
```

### 3.2 Envío a la web (API)

**Tablero de la semana** — `GET /api/v2/web/agendas?zone_id=&route_id=&date_from=&date_to=`

```json
{
  "data": {
    "schedule_version": 42,
    "days": [
      {
        "date": "2026-08-17",
        "weekday": "Lunes",
        "is_today": false,
        "sellers": [
          { "seller_id": 3572, "seller_name": "RUTA01", "customer_count": 14 }
        ],
        "total_customers": 14
      },
      {
        "date": "2026-08-18",
        "weekday": "Martes",
        "is_today": true,
        "sellers": [
          { "seller_id": 3572, "seller_name": "RUTA01", "customer_count": 44 }
        ],
        "total_customers": 44
      }
    ]
  },
  "meta": { "page": 1, "per_page": 7, "total": 7, "last_page": 1 },
  "filters": { "zone_id": null, "route_id": null, "date_from": "2026-08-17", "date_to": "2026-08-23" },
  "trace_id": "01J8..."
}
```

**Clientes de un vendedor en un día (acordeón)** — `GET /api/v2/web/agendas/2026-08-18/sellers/3572/customers`

```json
{
  "data": {
    "agenda_date": "2026-08-18",
    "weekday": "Martes",
    "seller_id": 3572,
    "seller_name": "RUTA01",
    "customer_count": 44,
    "customers": [
      {
        "customer_id": 1001,
        "code": "CLI-1001",
        "name": "ABARROTES DIAZ",
        "assigned_day": "Martes",
        "weekly_frequency": { "L": 0, "M": 6, "X": 2, "J": 1, "V": 0, "S": 0, "D": 0 },
        "status": "regular"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 25, "total": 44, "last_page": 2 },
  "trace_id": "01JB..."
}
```

---

## 4. Mapa consulta del Sincro → endpoint v2 → pantalla web

| Alcanzable | Consulta Sincro (origen) | Endpoint v2 | Pantalla / componente |
|---|---|---|---|
| Agrupación | A1, A2 (Microsip: zonas, carga) | `GET /agendas` → `data.options` | Filtro cascada Zona→Ruta del tablero |
| Asignación | B1, B2, B3, B4 (Microsip: asignación actual, frecuencia, mezclas) | `GET /agendas/unassigned-customers` | Panel izquierdo del tablero |
| Asignación | (comando sobre BD complementaria) | `PATCH /agendas/assignments:batch` | Drag & drop entre franjas/días |
| Agenda | C1, C2 (BD complementaria + Microsip) | `GET /agendas` | Columnas por día, franja de vendedor |
| Agenda | C1, C2 | `GET /agendas/{fecha}/sellers/{seller_id}/customers` | Acordeón de clientes por vendedor |

---

## 5. Pendientes de validación con el equipo

1. **Zonas como opciones del filtro**: no existe endpoint de zonas en el contrato; se propone entregarlas en `GET /agendas → data.options`. ¿Se valida así o se abre `GET /api/v2/web/zones` (más limpio para Configuración · Zonas)?
2. **Esquema de la BD complementaria**: la tabla `agendas` es maqueta; definir columnas reales, PK compuesta (customer, fecha) y política de `active`.
3. **Umbral regular/eventual**: definir con datos (¿regular = ≥4 compras en 2 meses?) para el `status` de la tarjeta.
4. **Origen de `schedule_version`**: contador global por empresa o por ruta/zona (afecta el `409`).
5. **RUTA01 piloto**: validar con el equipo la agenda propuesta (las 7 sin compra en 2 meses quedan "sin agendar").
