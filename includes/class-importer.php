<?php
/**
 * Importer — Importación de datos desde la API del SECOP.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Importer
{
    private Database $db;
    private const API_LIMIT    = 1000;
    private const HTTP_TIMEOUT = 60;
    private const RETRY_DELAY  = 2;      // segundos
    private const BATCH_DELAY  = 500000; // microsegundos (0.5s)

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->register_hooks();
    }

    private function register_hooks(): void
    {
        add_action('wp_ajax_secop_suite_start_import',  [$this, 'ajax_start_import']);
        add_action('wp_ajax_secop_suite_check_progress', [$this, 'ajax_check_progress']);
        add_action('wp_ajax_secop_suite_cancel_import', [$this, 'ajax_cancel_import']);
        add_action('wp_ajax_secop_suite_truncate_table', [$this, 'ajax_truncate_table']);
    }

    // ── AJAX: Iniciar importación ──────────────────────────────
    public function ajax_start_import(): void
    {
        check_ajax_referer('secop_suite_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'secop-suite')]);
        }

        if (get_transient(SECOP_SUITE_PREFIX . 'import_running')) {
            wp_send_json_error(['message' => __('Ya hay una importación en curso', 'secop-suite')]);
        }

        set_transient(SECOP_SUITE_PREFIX . 'import_running', true, HOUR_IN_SECONDS);
        delete_transient(SECOP_SUITE_PREFIX . 'import_progress');

        // Programar ejecución en background
        wp_schedule_single_event(time(), 'secop_suite_run_import');

        wp_send_json_success(['message' => __('Importación iniciada', 'secop-suite')]);
    }

    // ── AJAX: Verificar progreso ───────────────────────────────
    public function ajax_check_progress(): void
    {
        check_ajax_referer('secop_suite_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }

        $progress = get_transient(SECOP_SUITE_PREFIX . 'import_progress') ?: [
            'processed' => 0,
            'total'     => 0,
            'status'    => 'idle',
            'message'   => '',
        ];

        wp_send_json_success($progress);
    }

    // ── AJAX: Cancelar importación ─────────────────────────────
    public function ajax_cancel_import(): void
    {
        check_ajax_referer('secop_suite_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'secop-suite')]);
        }

        delete_transient(SECOP_SUITE_PREFIX . 'import_running');
        $this->update_progress(0, 0, 'cancelled', __('Importación cancelada por el usuario', 'secop-suite'));

        wp_send_json_success(['message' => __('Importación cancelada', 'secop-suite')]);
    }

    // ── AJAX: Limpiar tabla ────────────────────────────────────
    public function ajax_truncate_table(): void
    {
        check_ajax_referer('secop_suite_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'secop-suite')]);
        }

        if (get_transient(SECOP_SUITE_PREFIX . 'import_running')) {
            wp_send_json_error(['message' => __('No se puede limpiar mientras hay una importación en curso', 'secop-suite')]);
        }

        global $wpdb;
        $table = $this->db->get_table_name();
        $count = $this->db->get_total_records();

        $wpdb->query("TRUNCATE TABLE `{$table}`");

        // Reset options
        update_option(SECOP_SUITE_PREFIX . 'total_records', 0);

        // Invalidate caches
        $this->invalidate_chart_cache();

        Logger::info("Tabla {$table} limpiada — {$count} registros eliminados");

        wp_send_json_success([
            'message' => sprintf(__('%s registros eliminados correctamente', 'secop-suite'), number_format($count)),
        ]);
    }

    // ── Ejecución de importación ───────────────────────────────
    /**
     * Punto de entrada para cron programado.
     */
    public function run_scheduled(): void
    {
        $this->run();
    }

    /**
     * Punto de entrada para background (single event).
     */
    public function run_background(): void
    {
        // Background execution was scheduled by ajax_start_import which already
        // set the import_running transient. Define a constant so run() doesn't
        // interpret the existing transient as a concurrent import.
        if (!defined('SECOP_SUITE_FORCE_IMPORT')) {
            define('SECOP_SUITE_FORCE_IMPORT', true);
        }
        $this->run();
    }

    /**
     * Ejecutar importación completa.
     */
    public function run(): array
    {
        // Proteger contra importaciones simultáneas (CLI + Admin)
        if (get_transient(SECOP_SUITE_PREFIX . 'import_running') && !defined('SECOP_SUITE_FORCE_IMPORT')) {
            $msg = 'Ya hay una importación en curso';
            Logger::warning($msg);
            return ['success' => false, 'message' => $msg];
        }
        set_transient(SECOP_SUITE_PREFIX . 'import_running', true, HOUR_IN_SECONDS);

        $api_url      = get_option(SECOP_SUITE_PREFIX . 'api_url');
        $nit          = get_option(SECOP_SUITE_PREFIX . 'nit_entidad', '800103923');
        $fecha_inicio = get_option(SECOP_SUITE_PREFIX . 'fecha_inicio', '2016-01-01');
        $fecha_fin    = get_option(SECOP_SUITE_PREFIX . 'fecha_fin', date('Y-12-31'));

        if (empty($api_url)) {
            Logger::error('URL de API no configurada');
            delete_transient(SECOP_SUITE_PREFIX . 'import_running');
            return ['success' => false, 'message' => 'URL de API no configurada'];
        }

        /**
         * Hook antes de iniciar la importación.
         *
         * @param string $api_url URL de la API
         * @param string $nit     NIT de la entidad
         */
        do_action('secop_suite_before_import', $api_url, $nit);

        $where_clause = sprintf(
            'nit_de_la_entidad="%s" AND fecha_de_firma_del_contrato >= "%sT00:00:00.000" AND fecha_de_firma_del_contrato <= "%sT23:59:59.999"',
            $nit,
            $fecha_inicio,
            $fecha_fin
        );

        Logger::info('=== Iniciando importación ===');
        Logger::info("API: {$api_url} | NIT: {$nit} | Fechas: {$fecha_inicio} → {$fecha_fin}");

        // Obtener total estimado
        $estimated_total = $this->fetch_total_count($api_url, $where_clause);
        Logger::info("Total estimado: {$estimated_total}");

        $this->update_progress(0, $estimated_total, 'running', 'Iniciando importación...');

        $offset         = 0;
        $batch_number   = 0;
        $total_imported = 0;
        $total_updated  = 0;
        $errors         = [];

        do {
            // ¿Se canceló?
            if (!get_transient(SECOP_SUITE_PREFIX . 'import_running')) {
                Logger::warning('Importación cancelada por el usuario');
                break;
            }

            $batch_number++;
            $url = add_query_arg([
                '$where'  => $where_clause,
                '$limit'  => self::API_LIMIT,
                '$offset' => $offset,
                '$order'  => 'fecha_de_firma_del_contrato DESC',
            ], $api_url);

            Logger::debug("Batch #{$batch_number}: offset {$offset}");

            $items = $this->fetch_batch($url, $batch_number, $errors);

            if ($items === null) {
                $offset += self::API_LIMIT;
                continue;
            }

            if (empty($items)) {
                Logger::debug("Batch #{$batch_number}: sin más registros");
                break;
            }

            $batch_stats = $this->process_batch($items);
            $total_imported += $batch_stats['inserted'];
            $total_updated  += $batch_stats['updated'];

            Logger::info("Batch #{$batch_number}: {$batch_stats['inserted']} insertados, {$batch_stats['updated']} actualizados");

            $this->update_progress(
                $total_imported + $total_updated,
                $estimated_total,
                'running',
                sprintf('Procesando batch #%d...', $batch_number)
            );

            $offset += self::API_LIMIT;

            // Pausa entre batches para no saturar la API
            if (count($items) === self::API_LIMIT) {
                usleep(self::BATCH_DELAY);
            }

        } while (count($items) === self::API_LIMIT);

        // Finalizar
        delete_transient(SECOP_SUITE_PREFIX . 'import_running');
        update_option(SECOP_SUITE_PREFIX . 'last_import', current_time('mysql'));
        update_option(SECOP_SUITE_PREFIX . 'total_records', $this->db->get_total_records());

        // Invalidar cache de gráficas tras importación
        $this->invalidate_chart_cache();

        $summary = sprintf(
            'Importación completada: %d insertados, %d actualizados, %d errores',
            $total_imported,
            $total_updated,
            count($errors)
        );

        Logger::info("=== {$summary} ===");
        $this->update_progress($total_imported + $total_updated, $estimated_total, 'complete', $summary);

        $result = [
            'success'  => true,
            'imported' => $total_imported,
            'updated'  => $total_updated,
            'errors'   => $errors,
            'message'  => $summary,
        ];

        /**
         * Hook después de completar la importación.
         *
         * @param array $result Resultado de la importación
         */
        do_action('secop_suite_after_import', $result);

        return $result;
    }

    // ── Métodos internos ───────────────────────────────────────
    private function fetch_total_count(string $api_url, string $where_clause): int
    {
        $url = add_query_arg([
            '$select' => 'count(*)',
            '$where'  => $where_clause,
        ], $api_url);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return 0;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return (int) ($data[0]['count'] ?? 0);
    }

    /**
     * Descargar un lote de registros con un reintento en caso de error.
     *
     * @return array|null  null si falla definitivamente
     */
    private function fetch_batch(string $url, int $batch_number, array &$errors): ?array
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = wp_remote_get($url, [
                'timeout' => $attempt === 1 ? self::HTTP_TIMEOUT : self::HTTP_TIMEOUT + 30,
                'headers' => ['Accept' => 'application/json'],
            ]);

            if (is_wp_error($response)) {
                $msg = $response->get_error_message();
                Logger::error("Batch #{$batch_number} (intento {$attempt}): {$msg}");
                if ($attempt === 1) {
                    sleep(self::RETRY_DELAY);
                    continue;
                }
                $errors[] = "Batch #{$batch_number}: {$msg}";
                return null;
            }

            $status = wp_remote_retrieve_response_code($response);
            if ($status !== 200) {
                Logger::error("HTTP {$status} en batch #{$batch_number}");
                $errors[] = "HTTP {$status}";
                return null;
            }

            $body  = wp_remote_retrieve_body($response);
            $items = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Logger::error('JSON inválido - ' . json_last_error_msg());
                $errors[] = 'JSON: ' . json_last_error_msg();
                return null;
            }

            return is_array($items) ? $items : [];
        }

        return null;
    }

    /**
     * Procesar un lote de registros.
     *
     * @return array{inserted: int, updated: int}
     */
    private function process_batch(array $items): array
    {
        $stats = ['inserted' => 0, 'updated' => 0];

        foreach ($items as $item) {
            $result = $this->db->process_record($item);
            if ($result === 'inserted') {
                $stats['inserted']++;
            } elseif ($result === 'updated') {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    /**
     * Invalidar cache de gráficas tras importación.
     */
    private function invalidate_chart_cache(): void
    {
        global $wpdb;
        // Eliminar transients de cache de charts
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_secop_chart_%' OR option_name LIKE '_transient_timeout_secop_chart_%'"
        );
        // Limpiar object cache
        wp_cache_delete('secop_available_tables', 'secop_suite');
    }

    private function update_progress(int $processed, int $total, string $status, string $message): void
    {
        set_transient(SECOP_SUITE_PREFIX . 'import_progress', [
            'processed' => $processed,
            'total'     => $total,
            'status'    => $status,
            'message'   => $message,
            'timestamp' => time(),
        ], HOUR_IN_SECONDS);
    }
}
