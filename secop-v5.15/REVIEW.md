# Revisión del Plugin SECOP Suite v4.0.0 → v4.0.1

**Fecha:** 2026-03-19
**Revisor:** Claude (AI Code Review)
**Plugin:** SECOP Suite - Importación y Visualización de Datos SECOP
**Estado:** TODOS LOS ERRORES CORREGIDOS Y MEJORAS IMPLEMENTADAS EN v4.0.1
**Versión:** 4.0.0
**Autor:** Jonnathan Bucheli Galindo - Gobernación de Nariño

---

## Resumen General

SECOP Suite es un plugin de WordPress para importar, almacenar y visualizar datos contractuales del SECOP (Sistema Electrónico de Contratación Pública) de Colombia. Integra la API de datos.gov.co con gráficas D3plus configurables mediante shortcodes.

El plugin está bien estructurado con patrón Singleton, namespaces PHP 8.1, strict types, y arquitectura modular con 7 clases. Incluye medidas de seguridad razonables como validación de tablas por whitelist, validación de columnas contra DESCRIBE, y sanitización de filtros.

---

## Errores Encontrados

### ERR-01: Vulnerabilidad de SQL Injection en Custom Query (CRÍTICO)

**Archivo:** `includes/class-visualizer.php` líneas 414-417

La query personalizada se ejecuta directamente sin `$wpdb->prepare()`. Aunque `sanitize_custom_query()` filtra algunas palabras clave, la protección es insuficiente:

- Se puede evadir con comentarios SQL (`SEL/**/ECT`), codificación, o funciones MySQL.
- `UNION` simple (sin `ALL`) no está bloqueado en la lista de palabras prohibidas.
- No se bloquean funciones peligrosas como `BENCHMARK()`, `SLEEP()`, `LOAD DATA INFILE`, o `INTO @variable`.
- `wp_kses_post()` no es apropiado para sanitizar SQL.

```php
// Línea 416 - Query ejecutada sin preparar
$results = $wpdb->get_results($config['custom_query'], ARRAY_A);
```

**Recomendación:** Eliminar la funcionalidad de custom query o implementar un query builder parametrizado.

---

### ERR-02: Slug incorrecto en página de registros (BUG FUNCIONAL)

**Archivo:** `templates/admin/records-page.php` líneas 21 y 164
**vs.** `secop-suite.php` línea 168

El template usa `page=ss-records` en el formulario de filtros:
```html
<input type="hidden" name="page" value="ss-records" />
```

Pero el menú registra la página con slug `secop-suite-records`:
```php
add_submenu_page('secop-suite', ..., 'secop-suite-records', ...);
```

Esto hace que los filtros de búsqueda, año y estado no funcionen correctamente, redirigiendo a una página inexistente. El mismo error se repite en la paginación (línea 164).

---

### ERR-03: Doble ejecución de query en preview

**Archivo:** `includes/class-visualizer.php` líneas 402-405

```php
wp_send_json_success([
    'data'  => $this->get_chart_data($config),
    'count' => count($this->get_chart_data($config)), // Query ejecutada 2 veces
]);
```

La query se ejecuta dos veces innecesariamente, duplicando la carga en la base de datos.

**Corrección:**
```php
$data = $this->get_chart_data($config);
wp_send_json_success(['data' => $data, 'count' => count($data)]);
```

---

### ERR-04: Uso de `_e()` sin escape en templates (XSS potencial)

**Archivo:** `templates/frontend/chart.php` líneas 39, 52, 65, 87, 98, 111, 121, 133, 134, 144, 188, 194

Se usa `_e()` donde debería usarse `esc_html_e()` para prevenir XSS si el texto traducido contiene HTML malicioso:

```php
<span><?php _e('Detalle', 'secop-suite'); ?></span>  // Sin escape
```

**Corrección:** Reemplazar todas las instancias de `_e()` por `esc_html_e()` en contextos HTML.

---

### ERR-05: Total de registros incorrecto en paginación REST API

**Archivo:** `includes/class-rest-api.php` línea 107

```php
$total = $this->db->get_total_records(); // Siempre retorna total global
```

El total retornado no considera los filtros aplicados (`anno`, `estado`, `search`), generando metadatos de paginación incorrectos para el cliente.

---

### ERR-06: Falta validación de formato de fecha

**Archivos:** `secop-suite.php` líneas 192-193

Las fechas `fecha_inicio` y `fecha_fin` se sanitizan con `sanitize_text_field` pero no se valida que sean formato `YYYY-MM-DD`. Valores no válidos rompen la consulta a la API SECOP.

---

### ERR-07: Endpoint CSV retorna JSON en lugar de CSV

**Archivo:** `includes/class-rest-api.php` líneas 192-197

`WP_REST_Response` serializa automáticamente a JSON. Pasar una cadena CSV como contenido resultará en un string JSON con la cadena CSV escapada, no un archivo CSV descargable.

**Corrección:** Usar output directo con `header()` + `echo` + `exit`, o registrar un handler de formato personalizado.

---

### ERR-08: Interval infinito en frontend

**Archivo:** `assets/js/frontend.js` líneas 406-412

```javascript
const checkRendered = setInterval(function() {
    const svgElement = document.querySelector(renderTarget + ' svg');
    if (svgElement) {
        self.$container.addClass('ss-loaded');
        clearInterval(checkRendered);
    }
}, 100);
```

El `setInterval` nunca se detiene si la gráfica falla al renderizar, consumiendo recursos indefinidamente.

**Corrección:** Agregar un timeout máximo (ej. 30 segundos) o un contador de intentos.

---

### ERR-09: XSS en función `showToast`

**Archivo:** `assets/js/frontend.js` línea 540

```javascript
const $toast = $('<div class="ss-toast">' + message + '</div>');
```

Si `message` contiene HTML, se ejecutará como tal. Debería usar `.text()`:

```javascript
const $toast = $('<div class="ss-toast">').text(message);
```

---

### ERR-10: Log sin protección contra Nginx

**Archivo:** `includes/class-logger.php` línea 28

Solo se crea `.htaccess` para proteger el directorio de logs, lo cual solo funciona con Apache. Servidores Nginx ignoran `.htaccess` y los logs quedarían expuestos públicamente.

---

## Lista de Mejoras

### Seguridad

| # | Mejora | Prioridad |
|---|--------|-----------|
| M-01 | Eliminar custom query SQL o reemplazar con query builder visual parametrizado | Alta |
| M-02 | Agregar rate limiting a endpoints AJAX y REST API | Alta |
| M-03 | Servir D3.js, html2canvas y topojson localmente en vez de CDNs externos (riesgo supply chain + GDPR) | Alta |
| M-04 | Implementar nonces por chart ID en lugar de nonce global para frontend | Media |
| M-05 | Agregar headers de seguridad (`X-Content-Type-Options`, etc.) en REST API | Media |

### Rendimiento

| # | Mejora | Prioridad |
|---|--------|-----------|
| M-06 | Cachear `get_chart_data()` con transients, invalidando al importar | Alta |
| M-07 | Usar `INSERT ... ON DUPLICATE KEY UPDATE` en vez de SELECT + INSERT/UPDATE | Alta |
| M-08 | Cachear `get_available_tables()` y `get_table_columns()` (ejecutan SHOW/DESCRIBE por cada chart) | Media |
| M-09 | Agregar paginación o streaming para exportación CSV masiva | Media |
| M-10 | Implementar lazy loading de gráficas con Intersection Observer | Baja |

### Arquitectura y Código

| # | Mejora | Prioridad |
|---|--------|-----------|
| M-11 | Agregar `load_plugin_textdomain()` - el plugin usa `__()` pero nunca carga traducciones | Alta |
| M-12 | Agregar tests unitarios y de integración con PHPUnit | Alta |
| M-13 | Implementar migraciones de BD versionadas para futuras actualizaciones de esquema | Media |
| M-14 | Separar Logger en niveles (DEBUG, INFO, WARNING, ERROR) con rotación de archivos | Media |
| M-15 | Agregar hooks/actions propios (`do_action('secop_suite_after_import')`) para extensibilidad | Media |
| M-16 | Usar Composer con autoloader PSR-4 para mejor interoperabilidad | Baja |
| M-17 | Proteger contra importaciones simultáneas CLI + Admin (verificar transient en `run()`) | Media |

### UX / Frontend

| # | Mejora | Prioridad |
|---|--------|-----------|
| M-18 | Agregar exportación a Excel (.xlsx) además de CSV | Media |
| M-19 | Mejorar accesibilidad (a11y): aria-labels, focus trapping en modales, scope en tablas | Alta |
| M-20 | Agregar filtros de fecha en REST API (`fecha_desde`, `fecha_hasta`) | Media |
| M-21 | Implementar búsqueda con debounce en página de registros | Baja |
| M-22 | Agregar estados de error descriptivos en la UI de importación (timeout, red, API) | Baja |
| M-23 | Agregar confirmación visual de guardado exitoso en configuración de gráficas | Baja |

---

## Conclusión

SECOP Suite v4.0.0 es un plugin funcional y bien organizado que cumple su propósito. Sin embargo, tiene un **error crítico de seguridad** (ERR-01: SQL injection en custom queries) que debe corregirse de inmediato, y un **bug funcional** (ERR-02: slug incorrecto) que impide el uso de filtros en la página de registros. Se recomienda priorizar las correcciones de seguridad y los bugs funcionales antes de desplegar en producción.
