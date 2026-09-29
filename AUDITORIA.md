# Auditoría SECOP Suite — v5.16.0 (actualizada en v5.19.2)

**Fecha:** 2026-08-24
**Alcance:** todo el código PHP (núcleo, plantillas, desinstalador), JS propio (`assets/js/`, sin `vendor/`) y pruebas. Tres pasadas independientes: seguridad backend, XSS/plantillas/JS y calidad (bugs, duplicación, código deficiente), con verificación cruzada de cada hallazgo.
**Estado de pruebas:** `php tests/run.php` → 30/30 OK en v5.16.0; 39/39 OK en v5.17.0 (más pruebas de extremo a extremo contra MariaDB 10.11 con `ONLY_FULL_GROUP_BY`).

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
| S15 | MEDIA | ✅ v5.17.0 | La columna `tercero` del VIEW (identificación del tercero en Sysman) se exportaba en `/consulta/csv` y `/consulta/txt` (`SELECT *`) y podía usarse como filtro. Ahora es PII junto a `documento_proveedor` (lista única `Open_Data::PII_COLS`) | `includes/class-open-data.php`, `class-rest-api.php` |
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
| B19 | ALTO | ✅ v5.17.0 | Las APIs `/consulta*` devolvían un contrato una vez por asiento presupuestal (LEFT JOIN del VIEW) y repetían `valor_contrato` en cada fila; los asientos reimportados en Sysman aparecían como filas idénticas. Ahora `DISTINCT` sin ids internos + una fila por contrato por defecto (`agrupar=detalle` opcional) | `includes/class-open-data.php`, `class-rest-api.php` |
| B20 | MEDIO | ✅ v5.17.0 | Sin herramienta para eliminar duplicados en la base (contratos registrados con dos números, asientos Sysman reimportados). Nuevo módulo Depuración BD con respaldo y restauración | `includes/class-deduplicator.php` |
| B21 | MEDIO | ⏳ | El VIEW cruza asientos por `numero_de_proceso`: si un proceso tiene varios contratos legítimos, todos reciben los mismos asientos y la ejecución se cuenta en cada uno. El diagnóstico de Depuración BD lo cuantifica; la solución de fondo requiere un campo de cruce por contrato en Sysman | `includes/class-database.php` (create_view) |
| B22 | BAJO | ⏳ | La pestaña «Consulta» de Registros (admin) sigue mostrando filas crudas del VIEW (una por asiento, máx. 200); podría reutilizar `Open_Data::consulta_sql()` | `secop-suite.php` (render_records_page) |

## 3. Código duplicado

| # | Estado | Hallazgo | Ubicación |
|---|--------|----------|-----------|
| D1 | ✅ | Rate limiter por IP copiado ~8 veces → extraído a `Rate_Limiter::limited()` | todas las clases con AJAX/REST |
| D2 | ⏳ | `explora_contratistas()` y `lista_contratistas()` casi idénticos (~70 líneas): unificar (el primero es un caso particular del segundo) | `class-tracking.php` |
| D3 | ✅ v5.17.0 | Escritores CSV/TXT duplicados entre `export_*` y `get_consulta_*` → unificados en `stream_download()`, por lotes también para la vigencia | `class-rest-api.php` |
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
| C9 | ✅ v5.17.0 | `fputcsv` sin parámetro `escape` (obsoleto en PHP 8.4, podía mezclar avisos en la descarga) → escape vacío explícito (RFC 4180) | `class-rest-api.php` |

## 5. Actualizaciones y seguridad de los datos (revisión del código de producción, v5.19.2)

Revisión del código instalado en producción (`secop-v5.15/` del repositorio, idéntico a la 5.15.0; carpeta del servidor `secop-suite-main/`). Explica por qué instalar una versión nueva «reemplazaba toda la BD y los shortcodes».

| # | Sev. | Estado | Hallazgo | Ubicación |
|---|------|--------|----------|-----------|
| U1 | CRÍTICA | ✅ 5.15.1 / 5.19.1 | `uninstall.php` de 5.15.0–5.17.0: al pulsar «Eliminar» en CUALQUIER copia borra la tabla de contratos, todas las opciones `secop_suite_*` y todas las gráficas y filtros (y en 5.16–5.17 también la vista y las cards). Esa es la pérdida de datos de producción. Desde 5.15.1 desinstalar conserva los datos salvo opción expresa; desde 5.19.1 la versión activa neutraliza el desinstalador de otras copias antes de que WordPress lo ejecute | `uninstall.php`, `includes/copy-manager.php` |
| U2 | ALTA | ✅ 5.19.2 | ZIP con una carpeta raíz distinta a la instalada (`secop-suite/`, `secop-v5.15/`, `suite-secop-main/` del «Download ZIP») → WordPress lo instala como OTRO plugin en vez de ofrecer «Reemplazar el actual con el subido». El ZIP se genera ahora con `secop-suite-main/`; desde 5.19.1 el plugin instalado renombra cualquier ZIP subido a su carpeta | `bin/build-zip.sh`, `includes/class-updater.php` |
| U3 | ALTA | ✅ 5.19.2 | Cada activación hacía `CREATE OR REPLACE VIEW` sobre la vista de Contratación: una vista ajustada en producción se sobrescribía con la definición por defecto. Ahora solo se crea si no existe | `includes/class-plugin.php` (`activate`), `class-database.php` (`create_view`) |
| U4 | MEDIA | ✅ 5.19.2 | Cada activación aplicaba `dbDelta` a la tabla de contratos (puede cambiar tipos o índices de una tabla existente). Ahora `ensure_table()` solo la crea si no existe | `class-database.php` |
| U5 | ALTA | ✅ 5.19.2 | Toda migración de `db_version` hacía `DROP VIEW` y volvía a crear la vista. Ahora una vista con la estructura vigente se conserva; una antigua se reemplaza solo tras respaldar su definición | `class-plugin.php` (`maybe_upgrade`) |
| U6 | ALTA | ✅ 5.19.2 | La migración desde el esquema anterior a 5.0.0 hacía `DROP TABLE` de los contratos. Ahora renombra la tabla a `…_respaldo_AAAAMMDD_HHMMSS` | `class-database.php` (`migrate_to_new_schema`) |
| U7 | MEDIA | ✅ 5.19.2 | No había respaldo de la configuración: gráficas, filtros, cards, opciones y vista solo existían en la BD. Nueva pestaña **Configuración › Respaldos** con instantáneas automáticas (al cargar una versión nueva, al desactivar y antes de cambios en la vista o la tabla), restauración que conserva los IDs de los shortcodes y exportación `.json`. Tabla `{prefijo}secop_respaldos`, que el desinstalador antiguo no borra | `includes/class-config-backup.php` (nuevo) |
| U9 | ALTA | ✅ 5.19.2 | Revisión del módulo de respaldos antes de publicarlo. Restaurar desde un `.json` ajeno o alterado podía: dejar SQL arbitrario en `custom_query`, que los endpoints públicos ejecutan; cambiar la vista para publicar otras tablas; activar la purga al desinstalar; devolver a la papelera gráficas activas (WordPress las borra a los 30 días); sobrescribir sin respaldo previo. Ahora: los archivos validan la consulta como el editor y no restauran la vista; la purga nunca se restaura; la papelera se omite; sin respaldo previo no se restaura; un archivo de otro sitio no sobrescribe IDs existentes | `includes/class-config-backup.php` |
| U10 | MEDIA | ✅ 5.19.2 | Desde la 5.19.0, la primera carga del administrador programaba la importación automática si estaba activada sin evento (la 5.15.0 solo programaba al activar el plugin): actualizar el plugin podía iniciar una importación. Ahora se avisa con un botón y no se programa sola | `includes/class-plugin.php` |
| U11 | BAJA | ⏳ | `build_chart_query()` ejecuta la `custom_query` guardada sin volver a validarla. Las consultas se validan al guardarlas en el editor y al importarlas desde archivo; revalidar también al ejecutar requiere comprobar antes que las consultas de producción de la 5.15.0 pasan el validador actual | `includes/class-visualizer.php` |
| U12 | ALTA | ✅ 5.19.2 | Depuración BD (5.17.0–5.19.1) permitía borrar filas de las tablas presupuestales `sysman_auxiliar_cuentas` y `sysman_plan_presupuestal` y de las `dat_*`, que no pertenecen al plugin (el plugin solo las consulta para la vista). Ahora solo se depura la tabla de contratos; los lotes antiguos pueden restaurarse. Ninguna otra ruta del plugin escribe en esas tablas: la vista solo las lee, los `TRUNCATE` y la purga actúan solo sobre tablas propias | `includes/class-deduplicator.php` |
| U8 | INFO | — | Lo que se borró antes de la 5.19.2 (contratos, gráficas, filtros, opciones) solo puede recuperarse desde un respaldo de la base de datos del hosting. Los contratos también se pueden reimportar desde datos.gov.co | — |

Recomendación de operación: exportar la base de datos antes de cada actualización; actualizar siempre con «Reemplazar el actual con el subido»; no usar «Eliminar» en copias 5.15.0–5.17.0 (borrar su carpeta por FTP); descargar de vez en cuando un respaldo `.json` desde la pestaña Respaldos.

## 6. Recomendaciones de proceso

1. **Secret `ANTHROPIC_API_KEY`** en GitHub (Settings → Secrets → Actions) para activar los dos workflows nuevos: revisión de seguridad automática en cada PR (`security-review.yml`) y `@claude` en issues/PRs (`claude.yml`).
2. Trabajar por PRs (no push directo a `main`) para que la revisión de seguridad automática corra sobre cada cambio.
3. Ampliar `tests/` con casos para los bugs corregidos (rango de filtros, `per_page=0`, cancelación de importación) — los tests actuales solo cubren estadística/formato.
4. Ejecutar `phpcs` con el estándar WordPress en CI (varios `phpcs:ignore` puntuales ya documentan las interpolaciones seguras).
5. Revisar periódicamente esta lista: los ⏳ de las secciones 2–4 son el backlog sugerido para v5.17+.
