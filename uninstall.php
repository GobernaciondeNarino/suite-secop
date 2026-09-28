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

// Eliminar tabla de contratos
$table = $wpdb->prefix . 'secop_contracts';
$wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore

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

// Eliminar transients de cache de gráficas
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_secop_chart_%' OR option_name LIKE '_transient_timeout_secop_chart_%'"
);

// Eliminar cron
wp_clear_scheduled_hook('secop_suite_scheduled_import');
