# Módulo "Seguimiento de Dependencias" y "Datos Abiertos" — SECOP Suite

- **Fecha:** 2026-06-19
- **Versión objetivo del plugin:** 5.1.0 (minor, retrocompatible)
- **Autor del diseño:** Gobernación de Nariño / asistido
- **Estado:** Aprobado para implementación

## 1. Contexto y objetivo

SECOP Suite (v5.0.0) importa, almacena y visualiza datos contractuales del SECOP
(datos.gov.co, recurso `rpmr-utcd`) para la Gobernación de Nariño, publicados en
`gobiernoabierto.narino.gov.co/datos-abiertos`. Las gráficas existentes son un CPT
(`secop_chart`) renderizado vía shortcode `[secop_chart id="X"]` con d3/d3plus.

Se requiere un **nuevo módulo** enfocado en la **contratación de la entidad** que:

1. Cruce los datos de contratación con la **ejecución presupuestal por dependencia**
   (sistema Sysman), mediante un **VIEW SQL**.
2. Ofrezca **gráficas prediseñadas** (d3/d3plus interactivas, tooltips informativos)
   organizadas en *cards* de backend.
3. Genere **análisis autogenerados** (Descripción, Cualitativo, Cuantitativo,
   Predicción) con **rigor de científico/analista de datos**.
4. Sea **interactivo**: seleccionar una dependencia muestra la lista de sus contratos.
5. Trabaje **únicamente con la vigencia actual**, auto-actualizándose al cambiar de año.
6. Incorpore un submenú **"Datos Abiertos"** que organice los shortcodes de
   consulta y descarga (CSV / TXT / JSON en línea, como API).

### Principio transversal: rigor de datos
Toda inferencia debe ser estadísticamente defendible: limpieza/validación de datos,
supuestos explícitos, regresión con bondad de ajuste (R²) e incertidumbre en los
pronósticos, manejo honesto de datos escasos/estacionales, y textos de análisis que
reflejen cifras realmente calculadas. Se exponen limitaciones, no se sobre-afirma.

## 2. Arquitectura (Enfoque A — aprobado)

Módulo dedicado que **no toca** el motor de gráficas existente (cero regresiones,
consistencia visual garantizada):

- **`includes/class-tracking.php`** — clase `Tracking`: registra el VIEW, el CPT
  `secop_dep_card`, los shortcodes, los endpoints AJAX/REST, los submenús admin y
  el servicio de análisis estadístico.
- **CPT `secop_dep_card`** — organización **interna de backend** de las cards
  (espejo del patrón `secop_chart`). No es contenido de frontend.
- **VIEW SQL** — `{prefix}dat_seguimiento_dependencias` (el prefijo `dat_` hace que
  entre automáticamente en la whitelist `get_available_tables()`, reutilizando la
  validación de columnas por `DESCRIBE`).
- Reutiliza el enqueue de d3/d3plus/topojson/html2canvas y `frontend.css`.

Se inyecta en `Plugin::__construct()` junto a las demás clases (Database, Importer,
Visualizer, Filter, Rest_Api, Updater) con getter público `tracking()`.

## 3. Capa de datos — el VIEW

Reescritura limpia de la consulta original (sin `SELECT *` sobre cruce de 3 tablas,
con JOIN explícitos y columnas nombradas). Portable vía `$wpdb->prefix` (en
producción el prefijo es `ga_`):

```sql
CREATE OR REPLACE VIEW `{prefix}dat_seguimiento_dependencias` AS
SELECT
  pp.dependencia,
  pp.nombredependencia,
  ac.tercero, ac.nombretercero,
  ac.numero            AS numero_de_proceso,   -- = ac.nrodocumento
  ac.valordebito, ac.valorcredito, ac.saldoporejecutaresp,
  ac.cmpteafectado, ac.fecha, ac.anio, ac.mes,
  c.numero_del_contrato, c.nom_raz_social_contratista,
  c.fecha_inicio_ejecucion, c.fecha_fin_ejecucion,
  c.valor_contrato, c.objeto_del_proceso, c.url_contrato,
  c.tipo_de_contrato, c.modalidad_de_contratacion, c.origen
FROM `{prefix}sysman_auxiliar_cuentas`  ac
INNER JOIN `{prefix}sysman_plan_presupuestal` pp ON ac.rubro = pp.codigo
INNER JOIN `{prefix}secop_contracts`      c  ON ac.nrodocumento = c.numero_de_proceso
WHERE ac.tipocpte = 'REs';
```

Decisiones:
- **Sin filtro de año en el VIEW** → reutilizable. La "vigencia actual" se aplica en
  el módulo (`anio = YEAR(CURDATE())`), auto-actualizando y rotando la caché por año.
- Creación en activación/upgrade, **guardada por verificación** de existencia de
  `sysman_auxiliar_cuentas` y `sysman_plan_presupuestal`; si faltan → aviso admin
  (admin notice), nunca un fatal.
- `DROP/CREATE OR REPLACE VIEW` idempotente; el nombre se construye con `$wpdb->prefix`.

### Notas de calidad de datos
- Un contrato tiene **varias filas** en `auxiliar_cuentas` (múltiples comprobantes).
  Para totales por contrato y para la tabla de contratos se **agrega/deduplica**
  (`GROUP BY numero_del_contrato` o `DISTINCT` según el caso).
- Campos monetarios tratados como `DECIMAL`; se validan nulos y negativos.

## 4. Cards (organización interna de backend)

CPT `secop_dep_card`, espejo de `secop_chart`. Meta `_secop_dep_card_config`:

- `dimension`: `dependencia | tipo_contrato | modalidad | fuente | mensual | ejecucion`
- `chart_type`: tipo de gráfica (validado contra los compatibles de la dimensión)
- `dependencia` (opcional): filtra la card a una dependencia concreta
- `metric`: `valordebito | valorcredito | saldoporejecutaresp | valor_contrato | conteo`

Pantalla de edición (metaboxes, patrón Visualizer):
- **Configuración** (dimensión, tipo, métrica, dependencia).
- **Shortcodes** para copiar (gráfica + 4 análisis).
- **Vista previa** de la gráfica y de los 4 párrafos autogenerados.

## 5. Shortcodes (frontend)

| Shortcode | Función | Atributos |
|---|---|---|
| `[secop_seguimiento]` | Landing interactiva completa | `dependencia` (inicial), `dimensiones` |
| `[secop_dep_chart]` | Gráfica prediseñada | `card`, `dimension`, `tipo`, `dependencia`, `height` |
| `[secop_dep_analisis]` | Párrafo de análisis (≤564 ch) | `card`, `tipo` (`descripcion\|cualitativo\|cuantitativo\|prediccion`) |
| `[secop_dep_contratos]` | Tabla de contratos de una dependencia | `dependencia`, `per_page` |
| `[secop_consulta]` | Datos abiertos del VIEW (vista/descarga) | `formato` (`tabla\|csv\|txt\|json`) |

**Atributo `tipo` + ayuda de tipos compatibles** (renderizado como texto de ayuda y
validado server-side):
- Categóricas (`dependencia`, `tipo_contrato`, `modalidad`, `fuente`): `bar`,
  `stacked_bar`, `treemap`, `pie`, `donut`.
- Temporal (`mensual`): `line`, `area`.
- Ratio (`ejecucion`): `donut`, `bar`.

Si `tipo` no es compatible con la `dimension`, se cae al primero compatible y se
muestra una nota. Tooltips d3plus con cifras formateadas (es-CO, `$`) y contexto.

## 6. Análisis autogenerados (≤564 caracteres c/u)

Servicio `Tracking::analyze($config)` calcula sobre el VIEW (vigencia actual) y
rellena plantillas con cifras reales:

- **Descripción**: qué muestra, dimensión, vigencia, totales (nº contratos, valor
  total, nº dependencias/categorías).
- **Cualitativo**: concentración (índice tipo Herfindahl-Hirschman normalizado y/o
  % del top-1/top-3), categorías dominantes, vacíos notables.
- **Cuantitativo**: suma, media, mediana, máx/mín, % ejecución (débito/crédito vs
  saldo), coeficiente de variación.
- **Predicción**: **regresión lineal por mínimos cuadrados** sobre la serie mensual
  acumulada de la vigencia → proyección de cierre de año **con R² e intervalo de
  incertidumbre** (error estándar de la estimación). Advertencia honesta si hay
  pocos meses (`N` observaciones) o estacionalidad marcada.

Cada texto se ajusta a ~564 caracteres (recorte limpio por palabra si excede).

## 7. Interactividad

- Endpoints AJAX del módulo (`wp_ajax_*` + `nopriv`), protegidos con **nonce** y
  **rate-limiting por IP** (patrón `Visualizer::ajax_get_chart_data`).
- `[secop_seguimiento]`: selector de dependencia (lista/dropdown o clic en gráfica)
  → re-query de gráficas + carga de la **tabla de contratos**:
  `numero_del_contrato` (enlace a `url_contrato`), `nom_raz_social_contratista`,
  `fecha_inicio_ejecucion`, `fecha_fin_ejecucion`, `valor_contrato`,
  `objeto_del_proceso`. Deduplicada por contrato.
- Estilos heredados de `frontend.css` para consistencia con el resto del plugin.

## 8. Submenú "Datos Abiertos" (admin) + consulta como API

Página admin **SECOP Suite → Datos Abiertos**: *hub* que organiza y documenta los
shortcodes de datos abiertos (no es frontend). Cada uno con descripción, atributos,
botón *copiar* y enlace de vista previa:

| Shortcode | Expone | Formatos en línea |
|---|---|---|
| `[secop_export]` (ya existe) | Todos los contratos | CSV · TXT · JSON (API) |
| `[secop_consulta]` (nuevo) | El VIEW (dependencias + ejecución, vigencia actual) | CSV · TXT · JSON (API) |

- `[secop_consulta]` reutiliza el patrón de `[secop_export]`: visor con botones para
  ver/descargar en **CSV, TXT o JSON en línea**, paginado, filtrado a vigencia actual.
- **Endpoint REST** nuevo `secop-suite/v1/consulta` (en `Rest_Api`) para consumo como
  API, con paginación y `permission_callback` público de solo lectura.

## 9. Vigencia actual (auto-actualización)

- Helper `Tracking::current_vigencia(): int` = `YEAR(CURDATE())` (server-side).
- Toda query del módulo añade `WHERE anio = {vigencia}`.
- Cachés (transients) keyeadas por año → rotan automáticamente el 1 de enero.
- Indicador en admin: "Vigencia activa: {año}".

## 10. Seguridad

- Tablas/columnas validadas contra whitelist (`get_available_tables`) y `DESCRIBE`.
- Sin SQL dinámico sin preparar: `$wpdb->prepare` con placeholders; nombres de
  columna/tabla solo desde whitelists.
- Nonces en todos los AJAX/forms; `current_user_can('manage_options')` en admin,
  `current_user_can('edit_posts')` en operaciones de card.
- Rate-limiting por IP en endpoints públicos; escape de salida (`esc_html`,
  `esc_url`, `esc_attr`) en todas las plantillas.
- REST: `permission_callback` explícito; nunca exponer columnas sensibles.

## 11. Versionado y entrega

- Bump a **v5.1.0**: header del plugin, `SECOP_SUITE_VERSION`, `SECOP_SUITE_DB_VERSION`,
  y changelog en `README.md`.
- Migración `maybe_upgrade()`: crear el VIEW (guardada por verificación de tablas).
- **Commits organizados por cambio lógico**; cada cambio sube la versión para control.
- **1 PR** a `GobernaciondeNarino/secop-suite`, rama `claude/dependency-tracking-module`.
- ⚠️ El push requiere autenticación GitHub (no hay `gh` instalado): paso explícito
  que necesita credenciales/PAT del usuario.

## 12. Desarrollo por fases (con checkpoints de prueba)

Cada fase se prueba y se ajusta IU/UX/seguridad antes de avanzar:

1. **Datos**: VIEW + verificación de tablas + helper de vigencia + bump de versión.
2. **Servicio de análisis**: queries del módulo + estadística (regresión, R²,
   concentración) + generación de párrafos.
3. **Cards backend**: CPT `secop_dep_card`, metaboxes, vista previa.
4. **Shortcodes de gráfica/análisis**: `[secop_dep_chart]`, `[secop_dep_analisis]`
   con d3/d3plus y tooltips.
5. **Interactividad**: `[secop_seguimiento]` + `[secop_dep_contratos]` + AJAX.
6. **Datos abiertos**: `[secop_consulta]`, endpoint REST, submenú "Datos Abiertos".
7. **Pulido**: IU/UX, seguridad, README, versión final, PR.

## 13. Fuera de alcance (YAGNI)

- Predicción por tendencia histórica multi-año (se eligió regresión sobre serie
  mensual de la vigencia actual).
- Edición manual de los párrafos (se eligió autogenerado).
- Importación de las tablas Sysman (se asumen presentes en la misma BD).
