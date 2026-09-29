# Iteración v5.1.x — Bug "no hay datos" + reorganización (módulo Contratación)

> Iteración sobre el módulo ya entregado (PR #11 fusionado + PR #12 abierto). 7 mejoras del usuario + bug crítico.

## FASE A — Bug "siempre no hay datos" (PRIORIDAD)
Causa raíz (Fase 1 de depuración): `group_by_dimension()` siempre devuelve `[]`. Dos hipótesis:
- **A-VIEW-missing:** el VIEW se crea solo en `activate()`; una actualización por archivos no lo dispara → VIEW inexistente → `get_table_columns()` vacío → corta.
- **A-año:** el VIEW existe pero sin filas para `anio = YEAR(CURDATE())`.

Acciones:
- **A1. Diagnóstico (evidence-gathering):** helpers `Database::view_exists()`, `count_view_rows(?anio)`, `rows_by_year()`; notice/admin informativo que reporta: ¿VIEW existe?, filas totales, filas por año, vigencia usada por el código, conteo de la consulta cruda (3 tablas) y existencia/conteo de tablas Sysman.
- **A2. Fix robusto (causa A-VIEW-missing):** hook de actualización en `admin_init`/`plugins_loaded` que compara la versión almacenada con `SECOP_SUITE_VERSION` y ejecuta `create_view()` + migraciones sin requerir reactivación.
- **A3. Empty-state honesto:** el frontend/AJAX distingue "VIEW no existe" / "sin datos para vigencia X" / "0 filas" en lugar de un genérico "no hay datos".
- (Tras la evidencia del diagnóstico: si es A-año o un desajuste del JOIN, fix dirigido.)

## FASE B — Reorganización de menús
- **B1 (item 1):** Renombrar el módulo "Seguimiento Dependencias" → **"Contratación"** (label del CPT `secop_dep_card`, títulos).
- **B2 (item 2):** Eliminar el submenú "Panel de Control" (el click en el menú principal "SECOP Suite" ya renderiza el dashboard).
- **B3 (item 3):** "Ver Registros" → **"Registros"**, con dos pestañas: *Actual* (contratos importados) y *Consulta* (datos del VIEW).
- **B4 (item 4):** Ordenar submenús alfabéticamente: Contratación, Datos Abiertos, Filtros, Gráficas, Importar Datos, Logs, Registros.
- **B5 (item 6):** Posicionar el menú principal **debajo de "Páginas"** (position ~21).

## FASE C — Gráficas del módulo Contratación (item 5) — ENFOQUE: reusar Visualizer (decidido)
Cómo funciona el motor existente (detalle):
- CPT `secop_chart`, config en post meta `_secop_chart_config`; `Visualizer::build_chart_query()` valida tabla (whitelist) y columnas (DESCRIBE), arma SQL agregado seguro (SUM/COUNT/AVG, agrupación de fecha, multi-Y, filtros validados), cachea 15 min.
- Frontend `assets/js/frontend.js` → `ChartManager` sobre `.ss-chart-container`, lee config JSON inline y pide datos por AJAX `secop_suite_get_chart_data` (que lee `_secop_chart_config` de cualquier post, sin checar tipo) → render d3plus (bar/línea/área/pie/donut/treemap/tree/pack/network), tooltips, formato es-CO, toolbar (compartir/datos/imagen/CSV), leyenda.
- El VIEW `dat_seguimiento_dependencias` ya está en `get_available_tables()` (`dat_*`), graficable por este motor.

Reutilización (decidido con el usuario — opción A):
- **C1 (estáticas):** `Tracking::card_to_chart_config()` mapea una card (dimensión/tipo/métrica) a un `_secop_chart_config` sobre el VIEW con filtro `anio = vigencia` (+ dependencia opcional). Al guardar la card se almacena también `_secop_chart_config`. `[secop_dep_chart]` renderiza el contenedor del Visualizer (`templates/frontend/chart.php`); `Visualizer::enqueue_frontend_assets` incluye los shortcodes del módulo. Se jubila el render propio de `dep-tracking.js`.
- **C2 (interactivo):** `[secop_seguimiento]` usa contenedores Visualizer con `data-dependencia`; `ajax_get_chart_data` acepta un parámetro opcional `dependencia` (validado, solo sobre el VIEW) que añade un filtro `nombredependencia = X`; `frontend.js` (ChartManager) envía ese parámetro; el selector re-inicializa los charts. La tabla de contratos sigue por su AJAX propio.

## FASE D — Filtros: valores distintos por campo (item 7)
- **D1.** Reutilizar el `ajax_get_filter_options()` existente (ya hace `SELECT DISTINCT ... LIMIT 200`): cablear en `admin-filters.js` para que, al elegir tipo de campo `select`/`checkbox`/`range`, se carguen y muestren los valores únicos de la columna seleccionada.

## Verificación
- Núcleo puro: `php tests/run.php` (mantener verde).
- Lint `php -l` en cada archivo; `node --check` en JS.
- Integración WordPress: checkpoints manuales del usuario (el diagnóstico de A1 es el primero).
