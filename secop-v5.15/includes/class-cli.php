<?php
/**
 * Cli — Comandos WP-CLI para SECOP Suite.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Cli
{
    /**
     * Importar datos desde la API del SECOP.
     *
     * ## OPTIONS
     *
     * [--nit=<nit>]
     * : NIT de la entidad a importar
     *
     * [--desde=<fecha>]
     * : Fecha de inicio (YYYY-MM-DD)
     *
     * [--hasta=<fecha>]
     * : Fecha de fin (YYYY-MM-DD)
     *
     * ## EXAMPLES
     *
     *     wp secop import
     *     wp secop import --nit=800103923 --desde=2020-01-01 --hasta=2024-12-31
     *
     * @when after_wp_load
     */
    public function import($args, $assoc_args): void
    {
        if (isset($assoc_args['nit'])) {
            update_option(SECOP_SUITE_PREFIX . 'nit_entidad', sanitize_text_field($assoc_args['nit']));
        }
        if (isset($assoc_args['desde'])) {
            update_option(SECOP_SUITE_PREFIX . 'fecha_inicio', sanitize_text_field($assoc_args['desde']));
        }
        if (isset($assoc_args['hasta'])) {
            update_option(SECOP_SUITE_PREFIX . 'fecha_fin', sanitize_text_field($assoc_args['hasta']));
        }

        \WP_CLI::log('Iniciando importación de datos SECOP...');

        $result = Plugin::get_instance()->importer()->run();

        if ($result['success']) {
            \WP_CLI::success($result['message']);
        } else {
            \WP_CLI::error($result['message']);
        }
    }

    /**
     * Mostrar estadísticas de los datos importados.
     *
     * ## EXAMPLES
     *
     *     wp secop stats
     *
     * @when after_wp_load
     */
    public function stats(): void
    {
        global $wpdb;
        $db    = Plugin::get_instance()->database();
        $table = $db->get_table_name();

        $total       = $db->get_total_records();
        $total_value = $db->get_total_value();
        $last_import = get_option(SECOP_SUITE_PREFIX . 'last_import', 'Nunca');

        \WP_CLI::log('=== Estadísticas SECOP Suite ===');
        \WP_CLI::log('Total de contratos: ' . number_format($total));
        \WP_CLI::log('Valor total: $' . number_format($total_value, 2));
        \WP_CLI::log("Última importación: {$last_import}");

        $by_year = $wpdb->get_results(
            "SELECT YEAR(fecha_de_firma_del_contrato) AS anno, COUNT(*) AS count, SUM(valor_contrato) AS total
             FROM {$table}
             WHERE fecha_de_firma_del_contrato IS NOT NULL
             GROUP BY YEAR(fecha_de_firma_del_contrato)
             ORDER BY anno DESC"
        );

        if ($by_year) {
            \WP_CLI::log("\nPor año:");
            foreach ($by_year as $row) {
                \WP_CLI::log(sprintf(
                    '  %s: %s contratos ($%s)',
                    $row->anno,
                    number_format((float) $row->count),
                    number_format((float) $row->total, 2)
                ));
            }
        }
    }

    /**
     * Limpiar todos los datos importados.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Confirmar sin preguntar
     *
     * ## EXAMPLES
     *
     *     wp secop truncate --yes
     *
     * @when after_wp_load
     */
    public function truncate($args, $assoc_args): void
    {
        global $wpdb;

        if (!isset($assoc_args['yes'])) {
            \WP_CLI::confirm('¿Está seguro de eliminar TODOS los datos de contratos?');
        }

        $table = Plugin::get_instance()->database()->get_table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("TRUNCATE TABLE {$table}");

        update_option(SECOP_SUITE_PREFIX . 'total_records', 0);
        delete_option(SECOP_SUITE_PREFIX . 'last_import');

        \WP_CLI::success('Todos los datos han sido eliminados');
    }
}
