# Auditoría SECOP Suite — v5.16.0

**Fecha:** 2026-08-24
**Alcance:** todo el código PHP (núcleo, plantillas, desinstalador), JS propio (`assets/js/`, sin `vendor/`) y pruebas. Tres pasadas independientes: seguridad backend, XSS/plantillas/JS y calidad (bugs, duplicación, código deficiente), con verificación cruzada de cada hallazgo.
**Estado de pruebas:** `php tests/run.php` → 30/30 OK antes y después de los cambios.

Leyenda de estado: ✅ corregido en v5.16.0 · ⏳ pendiente (priorizado para próximas versiones).

---

## 1. Seguridad

| # | Sev. | Estado | Hallazgo | Ubicación |
|---|------|--------|----------|-----------|
| S1 | ALTA | ✅ | Fuga de PII (Ley 1581): `documento_proveedor` devuelto a visitantes anónimos por el AJAX de filtros (`SELECT *` sin exclusión) | `includes/class-filter.php` |
| S2 | ALTA | ✅ | Fuga de PII: explorador y listas de contratistas seleccionaban y devolvían `documento_proveedor` vía AJAX públicos; también era pedible con el atributo `campos` | `includes/class-tracking.php` |
| S3 | ALTA | ✅ | XSS DOM en tooltips d3plus de `[secop_chart]`/`[secop_dep_chart]`: d3plus inserta título y celdas con `innerHTML` y los datos SECOP (p. ej. razón social) llegaban sin escapar | `assets/js/frontend.js` |
| S4 | ALTA | ✅ | XSS en wp-admin: `escapeHtml` no escapaba comillas y `url_contrato` se interpolaba en un atributo `href` sin validar esquema (`javascript:` clicable) | `assets/js/admin-import.js` |
| S5 | MEDIA | ✅ | Endpoints públicos de gráficas ejecutaban la config de CUALQUIER post con la meta `_secop_chart_config` (borradores, privados, papelera) | `includes/class-rest-api.php`, `class-visualizer.php` |
| S6 | MEDIA | ✅ | Logs en directorio público predecible del plugin, protegidos solo por `.htaccess` sintaxis Apache 2.2 (inútil en nginx). Movidos a uploads con sufijo aleatorio + `.htaccess` 2.2/2.4 + migración | `includes/class-logger.php`, `uninstall.php` |
| S7 | MEDIA | ✅ | `custom_query`: la validación de tabla era por substring (mencionar una tabla permitida bastaba para leer otra). Ahora se extraen las tablas reales de FROM/JOIN y todas deben estar en la whitelist; joins por coma rechazados | `includes/class-visualizer.php` |
| S8 | BAJA | ✅ | Rate limiter: lectura-escritura no atómica y TTL reiniciado en cada petición (ventana renovable → bloqueo indefinido de usuarios legítimos). Centralizado en `Rate_Limiter` con ventana fija | `includes/class-rate-limiter.php` (nuevo) |
| S9 | BAJA | ✅ | SSRF (endurecimiento): el importador aceptaba cualquier URL de API configurada. Ahora allowlist de hosts `datos.gov.co` (filtro `secop_suite_allowed_api_hosts`) | `includes/class-importer.php` |
| S10 | BAJA | ⏳ | Transients ilimitados derivados de entrada anónima: cada combinación de parámetros crea una fila en `wp_options` por 15–30 min (mitigado por rate limit). Mejorar: cachear solo combinaciones validadas o usar object cache | `class-rest-api.php`, `class-tracking.php` |
| S11 | BAJA | ⏳ | El AJAX público de gráficas devuelve la config interna completa (nombres reales de tablas/columnas → reconocimiento de esquema). Devolver solo claves de presentación, como ya hace el endpoint REST | `class-visualizer.php` (ajax_get_chart_data) |
| S12 | BAJA | ⏳ | Actualizador desde GitHub sin verificación de integridad (hash/firma) del ZIP y con reactivación automática. Riesgo de cadena de suministro estándar; documentar o añadir verificación | `includes/class-updater.php` |
| S13 | BAJA | ⏳ | El rate limiter usa `REMOTE_ADDR`: tras un proxy/CDN todos los visitantes comparten cubo. Considerar cabeceras de proxy confiables configurables | `includes/class-rate-limiter.php` |
| S14 | INFO | — | Verificado sin hallazgos: SQL con `prepare` + whitelists/DESCRIBE en todas las rutas con entrada de usuario; nonces + capacidades en todos los AJAX de admin; escapado consistente en plantillas (`esc_html`/`esc_attr`/`esc_url`, `wp_json_encode` con `JSON_HEX_TAG\|JSON_HEX_APOS`); sin eval/deserialización/includes dinámicos/subida de archivos | — |

## 2. Bugs

| # | Imp. | Estado | Hallazgo | Ubicación |
|---|------|--------|----------|-----------|
| B1 | ALTO | ✅ | `TypeError` fatal cuando un lote de importación falla (`count(null)` en la condición del `do…while`); la importación moría y el candado bloqueaba reintentos 1 h. Ahora salta el lote con tope de 3 fallos consecutivos | `includes/class-importer.php` |
| B2 | ALTO | ✅ | Los filtros tipo "range" de `[secop_filter]` nunca se aplicaban (descartados por valor vacío antes de evaluar `_from`/`_to`) | `includes/class-filter.php` |
| B3 | ALTO | ✅ | Bucle de recargas: tras completar una importación, cada visita a la página de importación se recargaba cada 2 s durante ~1 h (el transient conserva `complete`) | `assets/js/admin-import.js` |
| B4 | ALTO | ✅ | REST `/contracts`: `per_page=0` → `DivisionByZeroError` (HTTP 500); `page=0` → OFFSET negativo (error SQL) | `includes/class-rest-api.php` |
| B5 | ALTO | ✅ | El cron de importación automática solo se (des)programaba al activar/desactivar el plugin: guardar los ajustes no programaba, cambiar frecuencia no reprogramaba, desactivar no cancelaba | `secop-suite.php` |
| B6 | ALTO | ✅ | `fecha_fin` congelada en el año de activación: desde el 1 de enero siguiente las importaciones programadas dejaban de traer contratos sin aviso. Un default "31-dic" de año pasado se avanza al año en curso (cortes explícitos a mitad de año se respetan) | `includes/class-importer.php` |
| B7 | MEDIO | ✅ | Una importación cancelada terminaba reportada como "completada" (pisaba el estado `cancelled`) | `includes/class-importer.php` |
| B8 | MEDIO | ✅ | Importaciones >1 h se "auto-cancelaban" (el transient `import_running` expiraba y se interpretaba como cancelación). Ahora se renueva por lote | `includes/class-importer.php` |
| B9 | MEDIO | ✅ | Paginación/contador de Registros ignoraban los filtros (mostraba el total sin filtrar y páginas vacías) | `secop-suite.php` |
| B10 | MEDIO | ✅ | Limpieza de logs: `wp_safe_redirect` tras salida ya enviada ("headers already sent"); movido a `admin_init` | `secop-suite.php` |
| B11 | MEDIO | ✅ | Exportación por lotes con ORDER BY sobre columna no única: filas duplicadas u omitidas entre lotes. Tie-breaker `id` añadido | `includes/class-rest-api.php` |
| B12 | MEDIO | ✅ | La invalidación de caché tras importar/truncar no borraba los cachés `secop_trk_*` (gráficas de Contratación con datos viejos hasta 30 min) | `includes/class-importer.php` |
| B13 | MEDIO | ✅ | Desinstalación incompleta: no borraba cards `secop_dep_card`, el VIEW `vista_secop_sysman` ni los transients `secop_trk_/secop_rl_` | `uninstall.php` |
| B14 | MEDIO | ⏳ | Los análisis narrativos (`[secop_dep_analisis]`, vista previa) ignoran los filtros personalizados de la card: los textos no cuadran con la gráfica filtrada | `class-tracking.php` (build_dataset) |
| B15 | BAJO | ⏳ | Drill-down muerto en gráficas "mensual": `sysman_label_expr` no resuelve `fecha_de_firma_del_contrato` → popup siempre vacío | `class-tracking.php` |
| B16 | BAJO | ⏳ | Fallback AJAX de opciones de filtro para visitantes es código muerto (endpoint solo admin + nonce equivocado): select vacío/spinner eterno si falla el render server-side | `assets/js/frontend-filters.js`, `class-filter.php` |
| B17 | BAJO | ⏳ | El formateador de miles del frontend formatea cualquier cadena numérica, incluidos números de contrato ("20240001" → "20.240.001") | `assets/js/frontend-filters.js` |
| B18 | BAJO | ⏳ | Tabla legacy `$wpdb->prefix . 'wp_data_contracting'` queda como `wp_wp_…` (¿prefijo duplicado?); `LIKE 'dat_%'` con `_` como comodín sin `esc_like` | `includes/class-database.php` |

## 3. Código duplicado

| # | Estado | Hallazgo | Ubicación |
|---|--------|----------|-----------|
| D1 | ✅ | Rate limiter por IP copiado ~8 veces → extraído a `Rate_Limiter::limited()` | todas las clases con AJAX/REST |
| D2 | ⏳ | `explora_contratistas()` y `lista_contratistas()` casi idénticos (~70 líneas): unificar (el primero es un caso particular del segundo) | `class-tracking.php` |
| D3 | ⏳ | Escritores CSV (cabeceras+BOM+csv_safe+columnas sin PII) y TXT (anchos fijos) duplicados entre `export_*` y `get_consulta_*`: extraer `stream_csv()`/`stream_txt()`. De paso, `get_consulta_csv/txt` cargan toda la vigencia en memoria — reutilizar el lote de 2000 | `class-rest-api.php` |
| D4 | ⏳ | Grafo de fuerza completo (colores, leyenda, drag, ticks, tooltip) copiado línea a línea entre `dep-network.js` y `dep-rings.js`: extraer módulo `SSForceGraph` | `assets/js/` |
| D5 | ⏳ | Switch de creación de gráficas d3plus (11 tipos) duplicado entre `frontend.js` y `admin-charts.js`: el admin debería delegar en `window.SSChartRender` | `assets/js/` |
| D6 | ⏳ | `build_multi_y_query()` re-copia el WHERE y el mapa de meses de `build_chart_query()` (y `class-tracking.php` repite el mapa): extraer `build_where()` + constante `MONTH_NAMES` | `class-visualizer.php`, `class-tracking.php` |
| D7 | ⏳ | Card de catálogo (`<details>` + shortcodes copiables) repetida 7 veces: extraer partial `render_catalog_card()`. El `SELECT YEAR(...) GROUP BY` está triplicado (dashboard, REST stats, CLI) | `templates/admin/contratacion-catalogo.php`, otros |

## 4. Código deficiente / rendimiento

| # | Estado | Hallazgo | Ubicación |
|---|--------|----------|-----------|
| C1 | ✅ | Errores de creación del VIEW y rechazos de seguridad se registraban como INFO: ahora `Logger::error`/`Logger::warning` | `class-database.php`, `class-visualizer.php` |
| C2 | ⏳ | `sc_chart` con overrides ejecuta en cada render frontend un `get_posts` por `meta_value` (sin índice) y puede crear posts ilimitados (uno por combinación de atributos, huérfanos para siempre): cachear hash→ID y limpiar huérfanas | `class-tracking.php` |
| C3 | ⏳ | `admin-import.js` + `wp_localize_script` se encolan en TODAS las páginas del plugin; encolar solo en Dashboard/Importar | `secop-suite.php` |
| C4 | ⏳ | Parámetro `$campos` muerto en el caché del explorador (documentado pero no usado en la clave) | `class-tracking.php` |
| C5 | ⏳ | `Tracking` (2.700+ líneas) mezcla CPT, 10 shortcodes, 12 AJAX y capa de consultas; funciones de 90–180 líneas en varias clases: separar un repositorio del VIEW de la capa de presentación | `class-tracking.php` y otros |
| C6 | ⏳ | `invalidate_chart_cache` borra por SQL directo sobre `wp_options`: no funciona con object cache externo (Redis/Memcached). Considerar "cache version salt" en las claves | `class-importer.php` |
| C7 | ⏳ | El detalle de contrato en admin muestra "Documento" vacío (la REST elimina la PII): decidir un endpoint admin autenticado que la incluya, o quitar la fila | `assets/js/admin-import.js` |
| C8 | ⏳ | Changelog del README sin entradas v5.13–v5.15 | `README.md` |

## 5. Recomendaciones de proceso

1. **Secret `ANTHROPIC_API_KEY`** en GitHub (Settings → Secrets → Actions) para activar los dos workflows nuevos: revisión de seguridad automática en cada PR (`security-review.yml`) y `@claude` en issues/PRs (`claude.yml`).
2. Trabajar por PRs (no push directo a `main`) para que la revisión de seguridad automática corra sobre cada cambio.
3. Ampliar `tests/` con casos para los bugs corregidos (rango de filtros, `per_page=0`, cancelación de importación) — los tests actuales solo cubren estadística/formato.
4. Ejecutar `phpcs` con el estándar WordPress en CI (varios `phpcs:ignore` puntuales ya documentan las interpolaciones seguras).
5. Revisar periódicamente esta lista: los ⏳ de las secciones 2–4 son el backlog sugerido para v5.17+.
