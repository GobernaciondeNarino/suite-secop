<?php
/**
 * SECOP Suite — Desinstalación limpia.
 *
 * @package SecopSuite
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

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

// Eliminar transients
delete_transient('secop_suite_import_progress');
delete_transient('secop_suite_import_running');

// Eliminar CPT de gráficas y sus meta
$charts = get_posts([
    'post_type'   => 'secop_chart',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields'      => 'ids',
]);

foreach ($charts as $chart_id) {
    wp_delete_post($chart_id, true);
}

// Eliminar CPT de filtros y sus meta
$filters = get_posts([
    'post_type'   => 'secop_filter',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields'      => 'ids',
]);

foreach ($filters as $filter_id) {
    wp_delete_post($filter_id, true);
}

// Eliminar CPT de cards de seguimiento (incluye las autogeneradas)
$cards = get_posts([
    'post_type'   => 'secop_dep_card',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields'      => 'ids',
]);

foreach ($cards as $card_id) {
    wp_delete_post($card_id, true);
}

// Eliminar transients de cache de gráficas, seguimiento y rate limiting
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_secop_%' OR option_name LIKE '_transient_timeout_secop_%'"
);

// Eliminar cron
wp_clear_scheduled_hook('secop_suite_scheduled_import');
