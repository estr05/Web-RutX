-- ============================================================================
-- CONSULTAS INTERACTIVAS RutX — Diagnóstico de asignación vendedor -> cliente
-- ============================================================================
-- Origen : COYATOC.FDB (Microsip, esquema Punto de Venta, tabla DOCTOS_PV)
-- Herramienta: C:\Firebird\fb50\isql.exe -user SYSDBA -password masterkey "C:\Microsip datos\COYATOC.FDB"
-- Foco   : RUTA01 como ruta piloto de la app
-- Ventana: últimos 2 meses. Si hoy es X, el inicio es DATEADD(-2 MONTH TO CURRENT_DATE)
--
-- CONFORMIDAD con "Revision_Tecnica_Maqueta_Agenda_Asignacion_Agrupacion.md":
--   * seller_id  = VENDEDORES.VENDEDOR_ID   (RUTA01 es NOMBRE de vendedor, no una entidad Ruta).
--   * legacy_visit_day = ZONAS_CLIENTES.NOMBRE (patrón histórico de día; NO es zona geográfica).
--   * Frecuencia SOLO de ventas válidas: TIPO_DOCTO='V', ESTATUS='N' y DESCRIPCION
--     que no inicie con 'NO VENTA:'. Validado contra Coyatoc: en la ventana actual
--     todos los docs son V/N y solo hay 1 'NO VENTA', por lo que los totales no cambian;
--     el filtro protege el cálculo cuando aparezcan no-ventas/devoluciones.
--
-- Para cambiar la ruta piloto, basta reemplazar el VENDEDOR_ID 3572 (RUTA01).
--   SELECT VENDEDOR_ID, NOMBRE FROM VENDEDORES;   -> obtener el id de la ruta deseada
-- ============================================================================

SET LIST ON;

-- ----------------------------------------------------------------------------
-- Q1. VENTAS VÁLIDAS POR RUTA (el vendedor que VENDIÓ), últimos 2 meses
--     Cuántos clientes distintos, documentos y monto por cada ruta.
-- ----------------------------------------------------------------------------
SELECT V.NOMBRE AS RUTA,
       COUNT(DISTINCT P.CLIENTE_ID) AS CLIENTES_COMPRADORES,
       COUNT(*)                      AS DOCTOS,
       SUM(P.IMPORTE_NETO)           AS VENTA_TOTAL
FROM DOCTOS_PV P
JOIN VENDEDORES V ON V.VENDEDOR_ID = P.VENDEDOR_ID
WHERE P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
  AND P.TIPO_DOCTO = 'V'
  AND P.ESTATUS = 'N'
  AND (P.DESCRIPCION NOT LIKE 'NO VENTA:%' OR P.DESCRIPCION IS NULL)
GROUP BY V.NOMBRE
ORDER BY VENTA_TOTAL DESC;

-- ----------------------------------------------------------------------------
-- Q2. CARTERA ASIGNADA vs CLIENTES QUE COMPRARON (últimos 2 meses) por ruta
--     Brecha = clientes que la ruta tiene asignados pero que no compraron.
-- ----------------------------------------------------------------------------
SELECT V.NOMBRE AS RUTA,
       (SELECT COUNT(*) FROM CLIENTES C WHERE C.VENDEDOR_ID = V.VENDEDOR_ID) AS CARTERA,
       (SELECT COUNT(DISTINCT P.CLIENTE_ID)
        FROM DOCTOS_PV P
        JOIN CLIENTES C2 ON C2.CLIENTE_ID = P.CLIENTE_ID
                        AND C2.VENDEDOR_ID = V.VENDEDOR_ID
        WHERE P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
          AND P.TIPO_DOCTO = 'V'
          AND P.ESTATUS = 'N'
          AND (P.DESCRIPCION NOT LIKE 'NO VENTA:%' OR P.DESCRIPCION IS NULL)) AS COMPRADORES_2M
FROM VENDEDORES V
WHERE V.NOMBRE LIKE 'RUTA%' AND V.NOMBRE <> 'RUTXVENDEDOR01'
ORDER BY CARTERA;

-- ----------------------------------------------------------------------------
-- Q3. PATRÓN DE DÍAS DE LA SEMANA por ruta (últimos 2 meses)
--     Días reales en que cada ruta vende: clientes, docs y monto.
-- ----------------------------------------------------------------------------
SELECT V.NOMBRE AS RUTA,
       CASE EXTRACT(WEEKDAY FROM P.FECHA)
         WHEN 0 THEN 'Domingo' WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes'
         WHEN 3 THEN 'Miercoles' WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes'
         ELSE 'Sabado' END AS DIA,
       COUNT(DISTINCT P.CLIENTE_ID) AS CLIENTES,
       COUNT(*)                     AS DOCTOS,
       SUM(P.IMPORTE_NETO)          AS VENTA
FROM DOCTOS_PV P
JOIN VENDEDORES V ON V.VENDEDOR_ID = P.VENDEDOR_ID
WHERE P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
  AND P.TIPO_DOCTO = 'V'
  AND P.ESTATUS = 'N'
  AND (P.DESCRIPCION NOT LIKE 'NO VENTA:%' OR P.DESCRIPCION IS NULL)
GROUP BY V.NOMBRE, EXTRACT(WEEKDAY FROM P.FECHA)
ORDER BY V.NOMBRE, EXTRACT(WEEKDAY FROM P.FECHA);

-- ----------------------------------------------------------------------------
-- Q4. MEZCLA A: clientes asignados a RUTA01 que compraron a OTRA ruta
--     (últimos 2 meses). Revela "quién está vendiendo en mi cartera".
-- ----------------------------------------------------------------------------
SELECT V.NOMBRE  AS VENDIO_ESTA_RUTA,
       C.NOMBRE  AS CLIENTE_DE_RUTA01,
       COUNT(*)  AS DOCTOS,
       SUM(P.IMPORTE_NETO) AS VENTA,
       MIN(P.FECHA) AS PRIMERA,
       MAX(P.FECHA) AS ULTIMA
FROM DOCTOS_PV P
JOIN CLIENTES C ON C.CLIENTE_ID = P.CLIENTE_ID AND C.VENDEDOR_ID = 3572 /* RUTA01 */
JOIN VENDEDORES V ON V.VENDEDOR_ID = P.VENDEDOR_ID
WHERE P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
  AND P.TIPO_DOCTO = 'V'
  AND P.ESTATUS = 'N'
  AND (P.DESCRIPCION NOT LIKE 'NO VENTA:%' OR P.DESCRIPCION IS NULL)
  AND V.VENDEDOR_ID <> 3572
GROUP BY V.NOMBRE, C.NOMBRE
ORDER BY VENTA DESC;

-- ----------------------------------------------------------------------------
-- Q5. MEZCLA B: clientes de OTRAS carteras que compraron a RUTA01
--     (últimos 2 meses). Revela "a quién le estoy vendiendo fuera de mi cartera".
-- ----------------------------------------------------------------------------
SELECT V.NOMBRE AS RUTA_DEL_CLIENTE,
       C.NOMBRE AS CLIENTE,
       COUNT(*) AS DOCTOS,
       SUM(P.IMPORTE_NETO) AS VENTA
FROM DOCTOS_PV P
JOIN CLIENTES C ON C.CLIENTE_ID = P.CLIENTE_ID AND C.VENDEDOR_ID <> 3572
JOIN VENDEDORES V ON V.VENDEDOR_ID = C.VENDEDOR_ID
WHERE P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
  AND P.TIPO_DOCTO = 'V'
  AND P.ESTATUS = 'N'
  AND (P.DESCRIPCION NOT LIKE 'NO VENTA:%' OR P.DESCRIPCION IS NULL)
  AND P.VENDEDOR_ID = 3572 /* RUTA01 */
GROUP BY V.NOMBRE, C.NOMBRE
ORDER BY VENTA DESC;

-- ----------------------------------------------------------------------------
-- Q6. RUTA01: día ASIGNADO (legacy_visit_day) vs días REALES de compra
--     (últimos 2 meses). Por cliente: dónde lo tiene el catálogo vs cuándo
--     compra de verdad. Solo ventas válidas.
-- ----------------------------------------------------------------------------
SELECT C.NOMBRE AS CLIENTE,
       Z.NOMBRE AS LEGACY_VISIT_DAY,
       LIST(DISTINCT CASE EXTRACT(WEEKDAY FROM P.FECHA)
         WHEN 0 THEN 'Dom' WHEN 1 THEN 'Lun' WHEN 2 THEN 'Mar'
         WHEN 3 THEN 'Mie' WHEN 4 THEN 'Jue' WHEN 5 THEN 'Vie'
         ELSE 'Sab' END, ', ') AS DIAS_REALES,
       COUNT(*) AS DOCTOS,
       SUM(P.IMPORTE_NETO) AS VENTA_2M
FROM CLIENTES C
LEFT JOIN ZONAS_CLIENTES Z ON Z.ZONA_CLIENTE_ID = C.ZONA_CLIENTE_ID
LEFT JOIN DOCTOS_PV P ON P.CLIENTE_ID = C.CLIENTE_ID
                     AND P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
                     AND P.TIPO_DOCTO = 'V'
                     AND P.ESTATUS = 'N'
                     AND (P.DESCRIPCION NOT LIKE 'NO VENTA:%' OR P.DESCRIPCION IS NULL)
WHERE C.VENDEDOR_ID = 3572 /* RUTA01 */
GROUP BY C.NOMBRE, Z.NOMBRE
ORDER BY VENTA_2M DESC;

-- ----------------------------------------------------------------------------
-- Q7. RUTA01: compradores vs no compradores en los últimos 2 meses
--     (basado en ventas válidas)
-- ----------------------------------------------------------------------------
SELECT 'COMPRARON EN 2M' AS ESTADO, COUNT(*) AS CLIENTES
FROM CLIENTES C
WHERE C.VENDEDOR_ID = 3572 /* RUTA01 */
  AND EXISTS (SELECT 1 FROM DOCTOS_PV P
              WHERE P.CLIENTE_ID = C.CLIENTE_ID
                AND P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
                AND P.TIPO_DOCTO = 'V'
                AND P.ESTATUS = 'N'
                AND (P.DESCRIPCION NOT LIKE 'NO VENTA:%' OR P.DESCRIPCION IS NULL))
UNION ALL
SELECT 'SIN COMPRA EN 2M', COUNT(*)
FROM CLIENTES C
WHERE C.VENDEDOR_ID = 3572 /* RUTA01 */
  AND NOT EXISTS (SELECT 1 FROM DOCTOS_PV P
                  WHERE P.CLIENTE_ID = C.CLIENTE_ID
                    AND P.FECHA >= DATEADD(-2 MONTH TO CURRENT_DATE)
                    AND P.TIPO_DOCTO = 'V'
                    AND P.ESTATUS = 'N'
                    AND (P.DESCRIPCION NOT LIKE 'NO VENTA:%' OR P.DESCRIPCION IS NULL));

-- ----------------------------------------------------------------------------
-- Q8. RUTA01: carga de clientes por día ASIGNADO (legacy_visit_day)
--     Muestra el equilibrio de la agenda actual por día de la semana.
--     Es referencia histórica; la agenda operativa se decide por agenda_date
--     en la BD complementaria, NO por este valor.
-- ----------------------------------------------------------------------------
SELECT Z.NOMBRE AS LEGACY_VISIT_DAY, COUNT(*) AS CLIENTES
FROM CLIENTES C
JOIN ZONAS_CLIENTES Z ON Z.ZONA_CLIENTE_ID = C.ZONA_CLIENTE_ID
WHERE C.VENDEDOR_ID = 3572 /* RUTA01 */
GROUP BY Z.NOMBRE
ORDER BY CLIENTES DESC;

-- ============================================================================
-- FIN
-- ============================================================================
