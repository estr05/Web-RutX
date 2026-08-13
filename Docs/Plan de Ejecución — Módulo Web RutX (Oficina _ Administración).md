# Plan de Ejecución — Módulo Web RutX (Oficina / Administración)

**Autor:** Manus AI · **Fecha:** 13 de agosto de 2026 · **Versión:** 1.3  
**Estado:** Roadmap de producto y arquitectura de alto nivel.  
**Ámbito:** Requerimientos y evolución de RutX Web; consume exclusivamente `/api/v2/web/*`. No modifica APIs móviles, no usa migraciones Laravel ni se comunica con el administrador local del Sincronizador `localhost:5047`.  
**Documentos relacionados:** [`Mapa_Documental_RutX_Web.md`](Mapa_Documental_RutX_Web.md), [`Plan_Estructura_Visual_Plataforma_Web_RutX.md`](Plan_Estructura_Visual_Plataforma_Web_RutX.md), [`guidelines.md`](guidelines.md), [`Contrato_API_Web_V2.md`](Contrato_API_Web_V2.md) y [`Plan_Sprints_Semana_1.md`](Plan_Sprints_Semana_1.md).  
**Repositorio analizado:** `gutierrezlopezhannia/rutx-web` (rama `dev`) · **Contexto:** `estr05/RutX-AppMovil`, `estr05/RutX-Sincronizador`, propuesta de BD de Ubicaciones (agosto 2026) y panel administrativo VeMobile (guía de versión a reemplazar; ver Anexo A)

---

## 1. Resumen ejecutivo

El repositorio de Hannia construyó en pocas semanas un **esqueleto funcional con 8 módulos visuales** (clientes, ventas, rutas, inventario, productos, cobranza, config y dashboard), rutas segmentadas por módulo y un patrón de rutas correcto. Sin embargo, **no es listo para producción ni para un cliente real**: casi toda la lógica vive inline dentro de vistas Volt gigantes (hasta 57 KB por archivo), los datos de negocio están *hardcodeados como arreglos PHP dentro de las vistas*, no existe sistema de diseño (40+ colores hex dispersos, sin paleta central, sin escala tipográfica), las tablas se reescriben desde cero en cada vista, no hay reglas de negocio del lado servidor, no hay pruebas automatizadas y la única conexión con la base real (Firebird de Microsip) es un comentario dentro de un repositorio.

Este documento propone un **plan de ejecución en cuatro fases** para reemplazar ese trabajo por un módulo web profesional, seguro, estandarizado y mantenible, construido sobre la misma base tecnológica (Laravel + Livewire + Tailwind 4) pero con disciplina de arquitectura. La decisión arquitectónica central —y no negociable— es la que ya valida la propuesta de BD de Ubicaciones:

> **Microsip (Firebird) es la fuente maestra y nunca se modifica desde el web.** Toda la información transaccional del día a día llega del campo a través del Sincronizador, y el módulo web se alimenta de una **base de datos complementaria propia** (lectura optimizada para reportes y monitoreo) más las vistas del Sincronizador.

---

## 2. Diagnóstico del repositorio actual

### 2.1 Lo que está bien y se conserva

Antes de señalar problemas conviene reconocer lo que funciona, porque esos patrones son la base sobre la que construiremos. El repositorio ya tiene **rutas segmentadas por módulo** (`routes/modules/{clientes,config,dashboard,inventario,productos,rutas,ventas}.php`) y una separación temprana de **repositorios con interfaz** (`CustomerRepositoryInterface`) que sirven como referencia de organización, aunque no se trasladan como acceso directo a datos. El driver Firebird y la migración `add_role_to_users_table` **no forman parte de la arquitectura de producción de RutX Web**: la web es API-first y su identidad/datos proceden del Sincronizador. Sí se conserva **Tailwind 4 + Vite**, moderno y adecuado, y el tema centralizado de la app móvil (`app_theme.dart`: primario `#003B5C`, acento `#FF6A13`) que dicta la paleta visual del web.

### 2.2 Problemas detectados (medibles)

| # | Problema | Evidencia cuantitativa | Impacto |
|---|----------|------------------------|---------|
| 1 | **Sin sistema de diseño**: colores hex hardcodeados | 40+ colores distintos: `#003859` ×328, `#004f7c` ×90, `#002d48` ×67, `#004066` ×51, `#1f2937` ×38, `#FF2D20` ×35, `#1976D2` ×5, etc. `app.css` = 2 líneas | Cada cambio de color requiere encontrar/reemplazar en ~60 archivos |
| 2 | **Tipografía sin escala** | `font-semibold` ×488, `font-bold` ×451, `font-medium` ×335, `text-[11px]` arbitrarios mezclados con `text-xs`/`text-lg` | Imposible auditar legibilidad y consistencia |
| 3 | **Tablas sin componente** | Cada una de las ~40 vistas de tablas define su propia estructura (`<table text-xs text-left whitespace-nowrap>`, `th` inline, sticky duplicado) | Duplicación masiva; cualquier ajuste (densidad, zebra, responsive) se repite N veces |
| 4 | **Lógica inline en vistas Volt** | `app/` = 20 archivos PHP, ~810 líneas totales; lógica de filtro, paginación y mock data vive dentro de `.blade.php` | Violación directa de separación de responsabilidades; difícil testear |
| 5 | **Componentes gigantes** | `reporte-preventa-entrega.blade.php` = 57 KB, `sidebar` = 52 KB, `rutas` = 46 KB | Imposible de revisar en PR; riesgo alto de bugs por morphdom (ya ocurrió un crash de Livewire documentado en el historial) |
| 6 | **Datos mock embebidos** | 80 líneas de array PHP con ventas ficticias dentro de `reportes-globales.blade.php` | El "reporte" no consulta nada; da falsa sensación de avance |
| 7 | **Dependencias por CDN** | Chart.js cargado desde `cdn.jsdelivr.net` en 4 vistas | Riesgo de disponibilidad y de versionado descontrolado en producción |
| 8 | **Sin capa de dominio** | Modelos Eloquent `Customer`, `Invoice`, etc. casi vacíos; sin Policies, sin Request validation, sin Servicios de negocio | Sin reglas de negocio, sin autorización granular |
| 9 | **Sin pruebas ni CI** | Tests solo con factory de `Customer`; sin workflow de GitHub Actions | Cada merge puede romper producción sin advertencia |
| 10 | **Sin time real** | Sin Broadcasting, sin Polling; el "log de sincronización" es estático | El requerimiento #1 del cliente (mapa y horarios en vivo) es técnicamente imposible hoy |
| 11 | **Artefactos de trabajo** | `layouts/app.blade.php.backup`, múltiples branches feature sin fusión coherente | Señal de desarrollo sin disciplina de versión |
| 12 | **Paleta web ≠ paleta app móvil** | Web usa `#003859`; la app oficial usa `#003B5C` y `#FF6A13` | La marca del producto está fragmentada desde ya |

### 2.3 Brecha frente a los requerimientos del cliente

| Requerimiento del cliente | Estado en el repo de Hannia | Qué falta |
|---------------------------|------------------------------|-----------|
| 1. Mapa y monitoreo en tiempo real (horarios, duración de visita, última venta) | `mapa-clientes` existe pero es mock estático | BD complementaria con eventos/visitas, canal de broadcast o polling, fuente de datos real |
| 2. Reportes consolidados/individuales con filtros diario/semanal/mensual y comparativas año anterior | Mock arrays en las vistas | Tablas analíticas, motor de consultas con caching, comparativa YoY |
| 3. Cancelación de ventas erróneas desde oficina | No existe | Flujo de cancelación con autorización, auditoría, motivo, y política de negocio (qué sí/no puede cancelar oficina vs vendedor) |
| 4. Créditos y cobranza (fase 2) | Módulo `cobranza` mock | Integración con CxC de Microsip vía Sincronizador |
| 5. Notificaciones oficina → app | Concepto existe en la app | Endpoint/API de notificaciones en Sincronizador + gestión desde el web |

---

## 3. Arquitectura objetivo

### 3.1 Principio rector

La propuesta de BD de Ubicaciones establece la regla de oro del ecosistema y este plan la hace transversal:

> El **Sincronizador es el único punto de control** que habla con las bases de datos. La app móvil no toca Microsip; el módulo web tampoco. El web consume datos **preparados y servidos** (del Sincronizador y de la BD complementaria), y nunca escribe sobre esquemas del ERP.

![Arquitectura objetivo](https://private-us-east-1.manuscdn.com/sessionFile/gCdAuLshxePnPGdNAZ4zZR/sandbox/oEt6NxLhfcGLik9IA9mdBI-images_1786653534625_na1fn_L2hvbWUvdWJ1bnR1L3BsYW5fcnV0eC9hc3NldHMvYXJxdWl0ZWN0dXJh.png?Policy=eyJTdGF0ZW1lbnQiOlt7IlJlc291cmNlIjoiaHR0cHM6Ly9wcml2YXRlLXVzLWVhc3QtMS5tYW51c2Nkbi5jb20vc2Vzc2lvbkZpbGUvZ0NkQXVMc2h4ZVBuUEdkTkFaNHpaUi9zYW5kYm94L29FdDZOeExoZmNHTGlrOUlBOW1kQkktaW1hZ2VzXzE3ODY2NTM1MzQ2MjVfbmExZm5fTDJodmJXVXZkV0oxYm5SMUwzQnNZVzVmY25WMGVDOWhjM05sZEhNdllYSnhkV2wwWldOMGRYSmgucG5nIiwiQ29uZGl0aW9uIjp7IkRhdGVMZXNzVGhhbiI6eyJBV1M6RXBvY2hUaW1lIjoxNzg4MjIwODAwfX19XX0_&Key-Pair-Id=K2QY5QTL8JSY6C&Signature=MEQCIENTIWrMv81oYYAcZRbO2idt6rmV6SRzzv1YTsMp1HEmAiAScU3eYAEvlY9h~iScGNIJon5nrPenh0LBRHNvrrYDNw__)

### 3.2 Flujo de datos

El día a día fluye así: el vendedor opera offline en la app → la app encola operaciones → al recuperar señal, el Sincronizador escribe en Microsip (respetando triggers y generadores de `DOCTOS_PV`) y **paralelamente** puebla/actualiza la BD complementaria con eventos de negocio: ventas, no-ventas, cobranza, cierre de jornada, visitas. El módulo web lee de la BD complementaria (diseñada para consultas analíticas, no para transacción del ERP) y de endpoints de consulta del Sincronizador.

Esto tiene tres ventajas concretas frente al enfoque del repo actual: **(a)** las consultas de reportes pesados nunca tocan Firebird en horario laboral, **(b)** el web puede crecer (visit
as, mapas, comparativas) sin riesgo para el ERP, y **(c)** el cliente puede replicar su BD complementaria en cualquier motor moderno (PostgreSQL o MySQL) mientras el ERP sigue en Firebird.

![Diagrama de la BD complementaria](https://private-us-east-1.manuscdn.com/sessionFile/gCdAuLshxePnPGdNAZ4zZR/sandbox/oEt6NxLhfcGLik9IA9mdBI-images_1786653534625_na1fn_L2hvbWUvdWJ1bnR1L3BsYW5fcnV0eC9hc3NldHMvYmQ.png?Policy=eyJTdGF0ZW1lbnQiOlt7IlJlc291cmNlIjoiaHR0cHM6Ly9wcml2YXRlLXVzLWVhc3QtMS5tYW51c2Nkbi5jb20vc2Vzc2lvbkZpbGUvZ0NkQXVMc2h4ZVBuUEdkTkFaNHpaUi9zYW5kYm94L29FdDZOeExoZmNHTGlrOUlBOW1kQkktaW1hZ2VzXzE3ODY2NTM1MzQ2MjVfbmExZm5fTDJodmJXVXZkV0oxYm5SMUwzQnNZVzVmY25WMGVDOWhjM05sZEhNdlltUS5wbmciLCJDb25kaXRpb24iOnsiRGF0ZUxlc3NUaGFuIjp7IkFXUzpFcG9jaFRpbWUiOjE3ODgyMjA4MDB9fX1dfQ__&Key-Pair-Id=K2QY5QTL8JSY6C&Signature=MEYCIQCMeYsFjmI8XAyWMJNobmx1ajXqnfi5CATn5HszygwhbwIhAOnhE1ZLnW-opP2evoJYOuo2noMjinJu5~8XTtFOZryx)

### 3.3 Base de datos complementaria (nucleo)

La BD complementaria no reemplaza ni copia Microsip: guarda solo lo que Microsip no guarda y lo que el web necesita para leer rápido. Propuesta inicial de tablas:

| Tabla | Contenido | Origen de datos |
|-------|-----------|-----------------|
| `ventas_consolidadas` | Una fila por ruta/día/producto: piezas, contado, crédito, preventa, entrega, devoluciones, faltante, sobrante, depósito | Sincronizador (parseo de `DOCTOS_PV` en cierre de jornada o streaming por venta) |
| `visitas` | `cliente_id`, ruta, hora llegada/salida, geolocalización, tipo (atendida/no-venta) | App móvil vía Sincronizador |
| `ubicaciones` | `cliente_id` (FK al ID real de Microsip), lat, lon, registrado_por, creado_en, nota | Etapa 1: captura manual desde el web; Etapa 2: refinamiento automático por la app (propuesta del PDF) |
| `pedidos` | Pedido en curso por ruta, estado (`borrador → enviado → entregado → cancelado`), `cancelado_por`, motivo | App web + app móvil (lectura/escritura coordinada por Sincronizador) |
| `cancelaciones` | `venta_id`, quien canceló, cuándo, motivo obligatorio, estado de reversa en Microsip | Módulo web (requerimiento #3) |
| `eventos_ruta` | Inicio de jornada, última venta registrada por ruta (timestamp), cierre | Sincronizador |
| `notificaciones` | Destinatario (ruta o vendedor), tipo, contenido, `leido_en` | Módulo web crea → Sincronizador entrega a la app |
| `creditos_cobranza` (fase 2) | Saldo por cliente, abonos, días de atraso, promesas de pago | Sincronizador leyendo CxC de Microsip |

Regla de esquemas: **nunca modificar la BD de Microsip**. La BD complementaria usa su propio motor, scripts/migraciones versionados y ejecutados por el Sincronizador —no por Laravel— y un usuario de BD de privilegios mínimos. RutX Web no mantiene conexión, migraciones ni modelos de esas tablas: solicita toda lectura o comando mediante `/api/v2/web/*`.

---

## 4. Estándares de estandarización (lo que tú pediste: "hacerlo bien")

### 4.1 Sistema de diseño en un solo lugar

Se crea **un único archivo de tokens de diseño** (`resources/css/tokens.css`, importado por `app.css`) con la paleta corporativa oficial tomada de la app móvil (`app_theme.dart`), tipografía y escala tipográfica. El resto del proyecto **prohibe cualquier valor hex arbitrario**: se auditoría con `stylelint` en CI.

```css
/* resources/css/tokens.css — ÚNICA fuente de verdad de diseño */
@theme {
  --color-brand: #003b5c;          /* primario (app oficial) */
  --color-brand-dark: #002d47;     /* hover/headers oscuros */
  --color-accent: #ff6a13;         /* acento: botones primarios, totales */
  --color-secondary: #a8c8e9;      /* fondos secundarios */
  --color-bg: #f4f6f8;             /* fondo general de pantallas */
  --color-surface: #ffffff;        /* tarjetas, inputs */
  --color-text-primary: #343d45;   /* texto principal */
  --color-text-secondary: #6b7680; /* subtítulos, hints */
  --color-border: #e0e0e0;         /* bordes y separadores */
  /* Semáforo de estados */
  --color-ok: #16a34a;
  --color-warn: #f59e0b;
  --color-error: #dc2626;
  --font-sans: "Inter", system-ui, sans-serif;
  --font-mono: "JetBrains Mono", monospace;
  --text-xs--line-height: 1rem;    /* escala tipográfica cerrada: xs/sm/base/lg/xl/2xl */
}
```

Con esto, una vista solo usa `text-brand`, `bg-accent`, `text-primary`… Si mañana la marca cambia, se edita **un archivo**.

### 4.2 Componentes UI reutilizables (eliminación de duplicación de tablas)

Se crean componentes Blade/Livewire genéricos y las 40 vistas de tablas pasan a usarlos:

| Componente | Qué resuelve |
|------------|--------------|
| `<x-data-table>` | Tabla genérica: headers ordenables, zebra, sticky, responsive, estado vacío, paginación, selección de columnas y exportación (CSV/Excel) |
| `<x-kpi-card>` | Tarjeta de indicador con etiqueta, valor, delta y semáforo |
| `<x-filter-bar>` | Barra de filtros estándar (zona, ruta, rango de fecha, botones Consultar/Limpiar) |
| `<x-chart>` | Envoltura de Chart.js (empaquetado en npm, no CDN) con tipos de serie predefinidos |
| `<x-breadcrumb>`, `<x-status-badge>`, `<x-modal>`, `<x-currency>` | Navegación, estados, diálogos, formato MXN |
| `app/Ui/` (o `App\View\Components`) | Clases PHP de los componentes con estilos en una sola clase CSS reutilizable |

### 4.3 Arquitectura de código (separación de responsabilidades)

Se migra el patrón Volt-inline a componentes Livewire con clase PHP (`php artisan make:livewire`) para todo módulo de negocio; Volt queda reservado a formularios pequeños. La estructura objetivo es:

```
app/
  Http/Controllers/     (rutas de página y autenticación web; sin acceso a BD de negocio)
  Livewire/             (un directorio por módulo; estado de interfaz en PHP)
  Services/             (ApiClient y adaptadores de respuestas: Dashboard, Agenda, Venta…)
  Data/                 (DTOs de request/response; nunca modelos Eloquent de Microsip)
  Policies/             (permisos de UI como segunda barrera; la API autoriza de nuevo)
  Http/Requests/        (validación centralizada de cada formulario antes de generar JSON)
  Http/Middleware/      (sesión cifrada, control de token, seguridad y trazabilidad)
  View/Components/      (arquetipos Blade reutilizables)
config/services.php → .env (solo `API_WEB_*`, TLS, timeout y secretos del entorno)

RutX-Sincronizador/
  Controllers/Web/      (rutas `/api/v2/web/*` exclusivas de oficina)
  Models/Web/           (DTOs/contratos web)
  Services/Web/         (consultas y comandos hacia BD complementaria/servicios autorizados)
  Docs/CONTRATOS_WEB_V2.md
```

Regla dura: **las vistas nunca contienen arreglos de datos ni lógica de filtro**. Cada vista consume propiedades de su componente, y cada componente consume un Service.

### 4.4 Seguridad

| Área | Protocolo |
|------|-----------|
| Autenticación | Login exclusivo `POST /api/v2/web/auth/login`, rate limiting y CSRF en Laravel; JWT `scope=web` guardado únicamente en sesión cifrada del servidor |
| Autorización | Roles y zonas se validan en dos capas: Laravel decide visibilidad con Policies/`@can`; la API v2 valida de nuevo cada operación. Nada de `@if(auth->es)` disperso. |
| Entrada y JSON | Form Requests antes de emitir JSON; DTOs/validadores en la API; `Accept`/`Content-Type: application/json`, TLS, límites, timeouts y envelopes con `trace_id` |
| Acceso a datos | RutX Web usa exclusivamente `ApiClient`; no abre Firebird, BD complementaria ni localhost:5047. El Sincronizador es el único proceso que accede a datos. |
| Registro de operaciones | El Sincronizador registra auditoría en la BD complementaria: actor, acción, recurso, motivo, timestamp, `trace_id` e IP cuando corresponda. |
| Secretos | Sin credenciales en repo; `.env.example` contiene solo nombres `API_WEB_*`; CI con Pint, pruebas y auditoría de dependencias. |
| Cancelaciones | Motivo obligatorio, comando idempotente bajo `/api/v2/web/sales/*`, permisos y auditoría; reversa coordinada por el Sincronizador (nunca `DELETE` sobre `DOCTOS_PV` desde el web). |

### 4.5 Calidad y entrega continua

GitHub Actions por cada PR: instalación de dependencias, `php artisan test` (PHPUnit/Pest), `pint` (formato), build de assets, y un job de lint de estilos (`stylelint` sobre tokens). Rama `main` = producción, `dev` = integración; cada feature en su rama con PR y al menos un aprobador. Cobertura mínima inicial del 70% en Services (no en vistas).

---

## 5. Roadmap por fases (sugerencia de ~14 semanas, ajustable por tamaño de equipo)

| Fase | Duración sugerida | Entregable | Criterio de aceptación |
|------|-------------------|------------|------------------------|
| **0. Fundaciones** | Semanas 1–2 | Repo limpio, sistema de tokens/componentes, CI/CD, topbar/sidebar, ApiClient y frontera `/api/v2/web/*` con autenticación web; catálogo de permisos que reserva `contador` sin habilitarlo para Coyatoc; sin conexiones Laravel a Firebird ni BD complementaria | App estandarizada, pipeline verde y usuario de oficina inicia sesión mediante API v2; para Coyatoc solo se emiten `administrador`, `supervisor` y `lector`. |
| **1. Datos, catálogos y agenda** | Semanas 3–5 | El Sincronizador publica proyecciones y catálogos mediante `/api/v2/web`; incluye Agenda, zonas, rutas, vendedores, clientes, cadenas e inventario VeMobile. El web construye Clientes y Agenda con filtros, búsqueda, calendario vendedor→día→clientes y asignaciones batch. | Catálogos/Agenda consumen JSON real; la descarga móvil diaria se filtra internamente por agenda sin cambiar su contrato. |
| **2. Reportes y tableros** | Semanas 6–8 | Módulo Reportes completo: consolidado e individual por ruta, desglose piezas/monto, filtros diario/semanal/mensual, **comparativa con la misma semana del año anterior**, exportación, dashboards con `<x-chart>` | Cliente valida cifras contra un cierre real de Microsip (reconciliación) |
| **3. Monitoreo en tiempo real** | Semanas 8–10 | Mapa por ruta con ubicaciones de clientes, horarios de inicio de jornada, duración de visitas, **hora de última venta por ruta** (polling de 10–30 s vía Livewire; si se requiere mapa con movimiento en vivo, activar Laravel Reverb + Leaflet/OpenStreetMap, que ya es el mapa elegido en la app móvil) | Un supervisor ve en pantalla la actividad del día con latencia ≤ 1 min |
| **4. Control de cancelaciones** | Semanas 10–11 | Flujo: consultar venta → motivo obligatorio → política (solo rol administrador, dentro de ventana configurable, reversa vía Sincronizador) → registro en `cancelaciones` y `auditoria` | Cancelación deja traza completa y el cliente firma la política de uso |
| **5. Notificaciones** | Semanas 11–12 | El web crea avisos mediante `/api/v2/web/notifications/*`; el Sincronizador persiste, audita y entrega a la app móvil por sus flujos internos, sin ampliar el contrato móvil. | Aviso enviado desde web aparece en el celular del vendedor al recuperar conexión. |
| **6. Estabilización y producción** | Semanas 12–14 | Pruebas de carga en reportes, conciliación final de cifras, documentación de instalación (manual como el de Sincronizador), hardening (rate limit, headers de seguridad, backups de la BD complementaria), piloto con 1–2 rutas reales | Piloto de 1 semana sin desviaciones de cifras y sin incidentes |
| **Fase 2 (fuera de alcance de este plan)** | — | Créditos y cobranza: lectura de CxC de Microsip vía Sincro, seguimiento de saldos, promesas de pago, aging. En esta fase o para un cliente que lo contrate se podrá activar `contador` con permisos de solo lectura financiera. | Ya validado por el cliente como siguiente etapa; el rol no forma parte del MVP Coyatoc. |

### 5.1 Priorización si el tiempo aprieta

Si hay que acortar, el orden de valor para el cliente es: **Fase 1 → 2 → 3** (los tres primeros pilares que mencionó: reportes reales, monitoreo, mapa), dejando cancelaciones y notificaciones para un "mini release" posterior. Nunca sacrificar la Fase 0: sin fundaciones estandarizadas, volvemos al mismo problema del repo actual en seis meses.

---

## 6. Decisión sobre el repo de Hannia

No se recomienda continuar iterando sobre `gutierrezlopezhannia/rutx-web`: la deuda de estandarización (40+ colores, tablas duplicadas, lógica inline, mocks) es mayor que el costo de reestructurar. Se recomiendan dos opciones:

| Opción | Descripción | Cuándo elegirla |
|--------|-------------|-----------------|
| **A. Fork y reestructuración quirúrgica** | Se toma el repo, se conservan las rutas por módulo y los layouts base, se migran las vistas a componentes genéricos y se introduce el sistema de tokens archivo por archivo | Si se quiere preservar el trabajo visual de diseño de pantallas de Hannia y el equipo es pequeño |
| **B. Repo nuevo desde cero (recomendado)** | Se arranca el proyecto web de producción en un repo nuevo bajo la organización del proyecto (p. ej. `RutX-Web` junto a `RutX-AppMovil` y `RutX-Sincronizador`), aplicando desde el día uno todos los estándares de la sección 4; se toma como referencia *visual* el repo de Hannia pero sin heredar su deuda | Si el compromiso es "hacerlo bien" para producción con un cliente real |

En ambas, el código de Hannia queda archivado como referencia histórica y sus PRs/commits se citan en la documentación del nuevo repo para reconocimiento.

---

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Leer Firebird en horario laboral degrada el ERP | La BD complementaria se actualiza fuera de pico (job nocturno) o por eventos del Sincronizador; el web nunca consulta Firebird en caliente salvo catálogos cacheados |
| Cancelar ventas genera discrepancias contables | Política escrita y firmada por el cliente; reversa solo vía Sincronizador con reversa en Microsip; reportes distinguen ventas netas vs canceladas |
| Duplicación de datos entre Microsip y BD complementaria | La complementaria nunca "edita" datos maestros; en caso de conflicto, Microsip manda (documentado en la política de datos) |
| Alcance desbordado (créditos/cobranza antes de tiempo) | Fase 2 explícitamente fuera del MVP; el esquema ya deja tablas preparadas (`creditos_cobranza`) para no tirar trabajo |
| Dependencia de una sola persona (el repo actual es de un solo author) | Estándares escritos en el repo (PROMPT_REVIEW_CODIGO ya existe en Sincronizador: replicarlo), CI obligatorio, PRs con aprobación cruzada |

---

## 8. Próximos pasos inmediatos (esta semana)

1. Decidir opción A o B del apartado 6 y crear el repo de producción.
2. Definir con el cliente la **política de cancelaciones** (quién, hasta cuándo, qué tipos de venta) — es un requerimiento de negocio, no técnico, y bloquea la fase 4.
3. Acordar con el equipo del Sincronizador la extensión mínima del contrato API: endpoints de consulta para el web y tabla `notificaciones` (actualizar `CONTRATOS.md`).
4. Instalar la BD complementaria en el ambiente del cliente (PostgreSQL recomendado por su soporte de extensiones geoespaciales PostGIS para el módulo de mapa).
5. Levantar la Fase 0: tokens de diseño + componentes base + CI, y cerrar el primer PR con pipeline verde.
6. Validar el inventario de módulos del Anexo A con el cliente (¿qué pantallas de VeMobile usa realmente a diario y cuáles son prescindibles?) para fijar el alcance del MVP sin sobreconstruir.

---

*Documento generado a partir del análisis de los repositorios `gutierrezlopezhannia/rutx-web` (rama dev, commit `250408b`), `estr05/RutX-AppMovil` y `estr05/RutX-Sincronizador`, y de la "Propuesta: Ubicación de clientes en la app móvil" (agosto 2026).*

---

# Anexo A — Referencia funcional: panel administrativo VeMobile

**Autor:** Manus AI · **Fecha:** 13 de agosto de 2026 · **Fuente:** exploración en vivo de https://ventas.vemobile.mx/prueba/ (ambiente de prueba, usuario `UPrueba`), realizada el 13 de agosto de 2026.

VeMobile es la **guía de versión** que el módulo RutX debe reemplazar. Este anexo documenta lo que el cliente ya conoce y espera ver en nuestra solución, para que el plan no subestime el alcance funcional: no basta con que nuestro web sea técnicamente mejor, debe **igualar o superar cada capacidad visible de VeMobile** en el día a día de la oficina.

## A.1 Arquitectura técnica observada

VeMobile es una **SPA de React con Material-UI** (comboboxes tipo autocomplete `mui-*`), Chart.js para gráficas (`canvas`) y un tema oscuro de dos niveles (header azul, sidebar azul marino, fondo casi negro). El login redirige a `/cpanel`, el header muestra la empresa activa, el usuario, un selector de idioma (ESP/i18n) y un toggle de layout, y el sidebar colapsable agrupa los módulos bajo la sección "PÁGINAS". Algunas rutas internas redirigen al `cpanel` cuando fallan o no aplican en el ambiente de prueba (posible catch-all de la SPA), lo que en nuestra solución debe resolverse con rutas correctas y estados de error claros.

## A.2 Inventario completo de módulos y pantallas

| Módulo | Pantallas observadas | Relevancia para RutX |
|--------|----------------------|----------------------|
| **Dashboard (cpanel)** | KPIs del mes (Total, Contado, Crédito, Cobranza, No Ventas, Entrega con desglose Contado/Crédito, Gastos), gráfica diaria con selector de métrica, filtros "Rango de fechas" y "Filtrar por Cadena", botón refrescar, contador de días restantes del mes, tablas de Movimientos (Preventa/Devolución) y Productos por unidad de medida | **Referente obligatorio**: nuestro dashboard de la Fase 2 debe partir de este inventario de KPIs y superarlo con filtros diario/semanal/mensual y comparativa YoY |
| **Catálogos** | Causas No Venta, Rubro Gasto, Lista de Precio, Precio Zona, Unidades de Medida, Causas No Entrega, Causas Merma | Catálogos maestros de causas y precios que ya alimentan los reportes; deben existir en la BD complementaria antes de los reportes |
| **Configuración** | Usuarios, Roles, Zonas, Campos Adicionales, Ticket, Ajustes | Confirma que roles, zonas y campos personalizados son ya parte del día a día del cliente; nuestra Fase 0 (roles + zonas) es el mínimo esperado |
| **Cliente** | Clientes, Cadena de Clientes, Crédito, Pedidos Crédito, Traspaso de Cliente | La pantalla de Clientes (filtros Zona/Ruta/Cadena/Origen/Estatus, búsqueda, selección de columnas, paginación, indicador "Con ubicación") es el patrón UI exacto que debe replicar nuestro `<x-data-table>`. El **Traspaso de Cliente** (mover un cliente entre rutas) es una función operativa que el plan debe incorporar |
| **Producto** | (submenú sin explorar en el ambiente de prueba) | Catálogo de productos con unidades de medida |
| **Ruta** | Rutas, Agenda, Gastos Operativos, Mapa de Clientes, Kilometraje, Unidades de Reparto, Clientes sincronizados por fecha, Agenda de Entregas | **Mapa de Clientes ya existe** en VeMobile: nuestro requerimiento #1 no es "crear mapa" sino "mapa en tiempo real + visitas + últimas ventas". Kilometraje y unidades de reparto completan la gestión logística |
| **Venta** | Levantamiento, Pedidos, Reportes y Gráficas, Visor, Cobranza, Clientes Pendientes, Reporte de Productos, Reportes Globales, Depósito Venta, Clientes con Mayor Venta, Reporte de Productos Rechazados, Reporte Preventa Entrega, Utilidad, Reporte de Ventas por Cliente, Reporte Rentabilidad por Ruta, Nuevo Gasto Operativo (~15 pantallas) | Es el corazón funcional del producto: el grueso de nuestra Fase 2 (reportes) y Fase 4 (cancelaciones/pedidos) debe cubrir este inventario. "Reporte Rentabilidad por Ruta" implica que el cliente ya valora márgenes y costos |
| **Inventario** | (submenú sin explorar en el ambiente de prueba) | Gestión de existencias en ruta |

## A.3 Qué replica RutX de VeMobile y qué debe superar

La estrategia de producto es doble: **igualar lo visible, superar lo invisible**. VeMobile ya entrega dashboards mensuales, gestión de rutas con mapa, catálogo maestro de clientes con ubicación, módulos de crédito/cobranza, reportes operativos extensos y gestión de gastos. Nuestra ventaja competitiva no está en añadir veinte módulos más, sino en tres capacidades que VeMobile **no** muestra en su panel y que el cliente RutX pidió explícitamente:

| Dimensión | VeMobile (guía actual) | RutX web (objetivo) |
|-----------|------------------------|---------------------|
| Tiempo real | Dashboard agregado del mes con botón refrescar; mapa de clientes estático | Monitoreo en vivo de la jornada: inicio de ruta, duración de cada visita, hora exacta de la última venta por ruta, con actualización automática (polling/broadcast) sin botón refrescar |
| Control de ventas | Sin flujo visible de cancelación desde oficina | Cancelaciones con motivo, política por rol y auditoría completa (requerimiento #3) |
| Datos | Sin datos en ambiente de prueba; no se observa comparativas interanuales | Reportes con comparativa semana contra misma semana del año anterior (YoY), filtros diario/semanal/mensual, exportación |
| Comunicación | Sin evidencia de notificaciones oficina→vendedor en el panel web | Emisión de avisos desde la oficina con entrega en la app móvil al recuperar conexión |

Además, los **reportes VeMobile son operativos** (qué se vendió hoy); el valor de RutX es agregar la **capa analítica** (tendencias, comparativas, rentabilidad consistente) que el cliente usa "para tomar decisiones administrativas".

## A.4 Implicaciones para el plan (cambios incorporados)

La exploración de VeMobile confirma y refuerza varias decisiones del plan principal, y agrega cuatro acciones concretas:

1. **Fase 2 (Reportes) debe nacer del inventario de pantallas de Venta/Ruta**: los entregables mínimos son Dashboard mensual con los 7 KPIs de VeMobile, Reporte de Productos, Preventa/Entrega, Rentabilidad por Ruta, Ventas por Cliente y Globales — todos los demás reportes VeMobile quedan mapeados como backlog.
2. **La pantalla de Clientes de VeMobile es el mock de alta fidelidad de nuestro `<x-data-table>`** (filtros + búsqueda + selección de columnas + paginación + indicador de ubicación), más el Traspaso de Cliente que se agrega a la Fase 1 (catálogos).
3. **Catálogos (Sección 5, Fase 1)** se amplía con: Causas No Venta, Rubros de Gasto, Listas de Precio, Precios por Zona, Causas de No Entrega y Causas de Merma, ya que los reportes dependen de ellos.
4. **El dashboard web hereda el concepto de "filtrar por Cadena" y "rango de fechas"** de VeMobile, que es exactamente el requerimiento #2 del cliente, confirmando que esa decisión de diseño está validada por la experiencia real del usuario.

---

*Este anexo complementa el "Plan de Ejecución — Módulo Web RutX" v1.1 (el documento principal incorpora esta referencia funcional en su roadmap desde la Fase 1).*
