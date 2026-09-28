<?php
/**
 * SECOP Suite — Desinstalación.
 *
 * Por defecto se CONSERVAN todos los datos (tabla de contratos, vista de
 * seguimiento, respaldos, gráficas, filtros, cards y opciones). Así, borrar una
 * copia vieja del plugin para instalar la nueva no destruye datos de producción.
 *
 * Los datos solo se purgan si el administrador activó la opción
 * «Eliminar todos los datos al desinstalar el plugin»
 * (secop_suite_delete_data_on_uninstall) Y no existe otra copia del plugin
 * instalada en wp-content/plugins (otra carpeta cuyo archivo principal tenga
 * «Plugin Name: SECOP Suite»), porque esa otra copia sigue usando los datos.
 *
 * @package SecopSuite
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// ─── ¿Hay otra copia de SECOP Suite instalada? ─────────────────
// Se revisan los archivos .php de primer nivel de cada carpeta de plugins
// (igual que hace WordPress con get_plugins()), excluyendo esta misma carpeta.
$secop_suite_otra_copia = false;
$secop_suite_propia     = realpath(__DIR__);
$secop_suite_dirs       = defined('WP_PLUGIN_DIR') ? glob(rtrim(WP_PLUGIN_DIR, '/\\') . '/*', GLOB_ONLYDIR) : [];

foreach ((array) $secop_suite_dirs as $secop_suite_dir) {
    if (realpath($secop_suite_dir) === $secop_suite_propia) {
        continue;
    }
    foreach ((array) glob($secop_suite_dir . '/*.php') as $secop_suite_php) {
        if (!is_file($secop_suite_php) || !is_readable($secop_suite_php)) {
            continue;
        }
        $secop_suite_head = get_file_data($secop_suite_php, ['Name' => 'Plugin Name']);
        if (trim((string) ($secop_suite_head['Name'] ?? '')) === 'SECOP Suite') {
            $secop_suite_otra_copia = true;
            break 2;
        }
    }
}

// ─── Limpieza siempre segura ───────────────────────────────────
// Tarea cron y transients de importación en curso. No se tocan si otra copia
// sigue instalada (podría estar activa y usándolos).
if (!$secop_suite_otra_copia) {
    wp_clear_scheduled_hook('secop_suite_scheduled_import');
    wp_clear_scheduled_hook('secop_suite_run_import');
    delete_transient('secop_suite_import_progress');
    delete_transient('secop_suite_import_running');
}

// ─── Purga de datos (solo si se pidió expresamente) ────────────
$secop_suite_purgar = in_array(get_option('secop_suite_delete_data_on_uninstall', false), [true, 1, '1', 'yes', 'on', 'true'], true);

if (!$secop_suite_purgar || $secop_suite_otra_copia) {
    // Conservar todos los datos: una reinstalación o actualización los reutiliza.
    return;
}

// Eliminar directorio de logs en uploads (v5.16.0) antes de borrar las opciones
// (el sufijo aleatorio del directorio se guarda como opción).
$log_suffix = get_option('secop_suite_log_dir_suffix');
if (is_string($log_suffix) && $log_suffix !== '') {
    $uploads = wp_upload_dir(null, false);
    $log_dir = trailingslashit($uploads['basedir']) . 'secop-suite-logs-' . $log_suffix;
    if (is_dir($log_dir)) {
        foreach ((array) glob($log_dir . '/{,.}*', GLOB_BRACE) as $log_file) {
            if (is_file($log_file)) {
                @unlink($log_file);
            }
        }
        @rmdir($log_dir);
    }
}

// Eliminar tabla de contratos
$table = $wpdb->prefix . 'secop_contracts';
$wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore

// Eliminar la tabla de respaldos del módulo de depuración (v5.17.0)
$dedup_backup = $wpdb->prefix . 'secop_dedup_backup';
$wpdb->query("DROP TABLE IF EXISTS `{$dedup_backup}`"); // phpcs:ignore

// Eliminar el VIEW del módulo de seguimiento
$view = $wpdb->prefix . 'vista_secop_sysman';
$wpdb->query("DROP VIEW IF EXISTS `{$view}`"); // phpcs:ignore

// Eliminar opciones del plugin
$options = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        'secop_suite_%'
    )
);

foreach ($options as $option) {
    delete_option($option);
}

// Eliminar CPT de gráficas, filtros y cards de seguimiento (incluye las
// autogeneradas) junto con su metadata.
foreach (['secop_chart', 'secop_filter', 'secop_dep_card'] as $secop_suite_cpt) {
    $secop_suite_ids = get_posts([
        'post_type'   => $secop_suite_cpt,
        'numberposts' => -1,
        'post_status' => 'any',
        'fields'      => 'ids',
    ]);

    foreach ($secop_suite_ids as $secop_suite_id) {
        wp_delete_post($secop_suite_id, true);
    }
}

// Eliminar transients de cache de gráficas, seguimiento y rate limiting
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_secop_%' OR option_name LIKE '_transient_timeout_secop_%'"
);
