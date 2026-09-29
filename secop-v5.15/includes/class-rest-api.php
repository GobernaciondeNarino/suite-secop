<?php
/**
 * Rest_Api — Endpoints REST unificados para contratos y gráficas.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Rest_Api
{
    private Database $db;
    private const NAMESPACE = 'secop-suite/v1';

    /** Columnas de datos personales del proveedor — Ley 1581 (nunca se exponen en endpoints públicos). */
    private const PII_COLS = ['documento_proveedor', 'tipo_documento_proveedor'];

    /** Quita columnas de datos personales (documento del proveedor) de una fila — Ley 1581. */
    private function strip_pii(array $row): array
    {
        unset($row['documento_proveedor'], $row['tipo_documento_proveedor']);
        return $row;
    }

    public function __construct(Database $db)
    {
        $this->db = $db;
        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('rest_post_dispatch', [$this, 'add_security_headers'], 10, 3);
    }

    /**
     * Agregar headers de seguridad a respuestas REST.
     */
    public function add_security_headers(\WP_HTTP_Response $response, \WP_REST_Server $server, \WP_REST_Request $request): \WP_HTTP_Response
    {
        $route = $request->get_route();
        if (str_starts_with($route, '/' . self::NAMESPACE)) {
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-Frame-Options', 'DENY');
            $response->header('Cache-Control', 'no-store, max-age=0');
        }
        return $response;
    }

    public function register_routes(): void
    {
        // ── Contratos ──────────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/contracts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_contracts'],
            'permission_callback' => '__return_true',
            'args'                => [
                'per_page'    => ['default' => 10, 'sanitize_callback' => 'absint'],
                'page'        => ['default' => 1,  'sanitize_callback' => 'absint'],
                'anno'        => ['sanitize_callback' => 'sanitize_text_field'],
                'estado'      => ['sanitize_callback' => 'sanitize_text_field'],
                'search'      => ['sanitize_callback' => 'sanitize_text_field'],
                'fecha_desde' => ['sanitize_callback' => 'sanitize_text_field'],
                'fecha_hasta' => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contracts/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_contract'],
            'permission_callback' => '__return_true',
        ]);

        // ── Estadísticas ───────────────────────────────────────
        register_rest_route(self::NAMESPACE, '/stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_stats'],
            'permission_callback' => '__return_true',
        ]);

        // ── Datos de gráfica ───────────────────────────────────
        register_rest_route(self::NAMESPACE, '/chart/(?P<id>\d+)/data', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_chart_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/chart/(?P<id>\d+)/csv', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_chart_csv'],
            'permission_callback' => '__return_true',
        ]);

        // ── Exportación de datos ───────────────────────────────
        register_rest_route(self::NAMESPACE, '/export/csv', [
            'methods'             => 'GET',
            'callback'            => [$this, 'export_csv'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/export/txt', [
            'methods'             => 'GET',
            'callback'            => [$this, 'export_txt'],
            'permission_callback' => '__return_true',
        ]);

        // ── Consulta (Datos Abiertos — vigencia actual) ────────
        register_rest_route(self::NAMESPACE, '/consulta', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_consulta'],
            'permission_callback' => '__return_true',
            'args'                => [
                'page'     => ['default' => 1,   'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 100, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/consulta/csv', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_consulta_csv'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/consulta/txt', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_consulta_txt'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── Contratos ──────────────────────────────────────────────
    public function get_contracts(\WP_REST_Request $request): \WP_REST_Response
    {
        // FIX I2: rate limit por IP (reutiliza consulta_rate_limited — máx. 30 req/min)
        if ($this->consulta_rate_limited()) {
            return new \WP_REST_Response(['message' => 'Demasiadas solicitudes'], 429);
        }

        global $wpdb;
        $table = $this->db->get_table_name();

        $per_page = min($request->get_param('per_page'), 100);
        $page     = $request->get_param('page');
        $offset   = ($page - 1) * $per_page;

        $where  = ['1=1'];
        $values = [];

        if ($anno = $request->get_param('anno')) {
            $where[]  = 'YEAR(fecha_de_firma_del_contrato) = %s';
            $values[] = $anno;
        }
        if ($estado = $request->get_param('estado')) {
            $where[]  = 'estado_del_proceso = %s';
            $values[] = $estado;
        }
        if ($search = $request->get_param('search')) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = '(nom_raz_social_contratista LIKE %s OR objeto_del_proceso LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }
        if ($fecha_desde = $request->get_param('fecha_desde')) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
                $where[]  = 'fecha_de_firma_del_contrato >= %s';
                $values[] = $fecha_desde . ' 00:00:00';
            }
        }
        if ($fecha_hasta = $request->get_param('fecha_hasta')) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
                $where[]  = 'fecha_de_firma_del_contrato <= %s';
                $values[] = $fecha_hasta . ' 23:59:59';
            }
        }

        // v5.11.0: además de los alias legacy de arriba (anno/estado/search/fecha_*),
        // se admite el filtrado genérico por CUALQUIER columna de la tabla desde la URL
        // (=, _like, _min, _max), validado contra las columnas reales y sin PII.
        // order_by/order también validados; default fecha_de_firma_del_contrato DESC.
        [$filters, $fvals] = $this->url_field_filters($request, $table, self::PII_COLS);
        $where  = array_merge($where, $filters);
        $values = array_merge($values, $fvals);

        $where_sql = implode(' AND ', $where);
        $order_sql = $this->url_order($request, $table, 'fecha_de_firma_del_contrato', 'DESC');

        // El COUNT usa exactamente el mismo WHERE + params (sin LIMIT/OFFSET). Para la
        // consulta de datos se agregan per_page y offset al final, manteniendo el orden
        // de params alineado con los placeholders.
        $data_values   = array_merge($values, [$per_page, $offset]);

        $contracts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_sql} {$order_sql} LIMIT %d OFFSET %d",
            $data_values
        ));

        // Ley 1581: nunca exponer el documento del proveedor en el endpoint público.
        foreach ($contracts as $c) {
            unset($c->documento_proveedor, $c->tipo_documento_proveedor);
        }

        // Total filtrado (sin LIMIT/OFFSET) para paginación correcta
        if (!empty($values)) {
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
                $values
            ));
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}");
        }

        return new \WP_REST_Response([
            'data' => $contracts,
            'meta' => [
                'total'        => $total,
                'per_page'     => $per_page,
                'current_page' => $page,
                'total_pages'  => max(1, (int) ceil($total / $per_page)),
            ],
        ]);
    }

    public function get_contract(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $table = $this->db->get_table_name();
        $id    = (int) $request->get_param('id');

        $contract = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));

        if (!$contract) {
            return new \WP_REST_Response(['message' => 'Contrato no encontrado'], 404);
        }

        // Ley 1581: nunca exponer el documento del proveedor en el endpoint público.
        unset($contract->documento_proveedor, $contract->tipo_documento_proveedor);

        return new \WP_REST_Response($contract);
    }

    // ── Estadísticas ───────────────────────────────────────────
    public function get_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        // FIX I2: rate limit por IP (reutiliza consulta_rate_limited — máx. 30 req/min)
        if ($this->consulta_rate_limited()) {
            return new \WP_REST_Response(['message' => 'Demasiadas solicitudes'], 429);
        }

        global $wpdb;
        $table = $this->db->get_table_name();

        return new \WP_REST_Response([
            'total_contracts' => $this->db->get_total_records(),
            'total_value'     => $this->db->get_total_value(),
            'by_year'         => $wpdb->get_results("SELECT YEAR(fecha_de_firma_del_contrato) AS anno, COUNT(*) AS count, SUM(valor_contrato) AS total_value FROM {$table} WHERE fecha_de_firma_del_contrato IS NOT NULL GROUP BY YEAR(fecha_de_firma_del_contrato) ORDER BY anno DESC"),
            'by_status'       => $wpdb->get_results("SELECT estado_del_proceso AS estado, COUNT(*) AS count FROM {$table} WHERE estado_del_proceso IS NOT NULL GROUP BY estado_del_proceso"),
            'by_type'         => $wpdb->get_results("SELECT tipo_de_contrato, COUNT(*) AS count, SUM(valor_contrato) AS total_value FROM {$table} WHERE tipo_de_contrato IS NOT NULL GROUP BY tipo_de_contrato ORDER BY count DESC LIMIT 10"),
            'last_import'     => get_option(SECOP_SUITE_PREFIX . 'last_import'),
        ]);
    }

    // ── Datos de gráfica ───────────────────────────────────────
    public function get_chart_data(\WP_REST_Request $request): \WP_REST_Response
    {
        // FIX I2: rate limit por IP (reutiliza consulta_rate_limited — máx. 30 req/min)
        if ($this->consulta_rate_limited()) {
            return new \WP_REST_Response(['message' => 'Demasiadas solicitudes'], 429);
        }

        $chart_id = (int) $request->get_param('id');
        $config   = get_post_meta($chart_id, '_secop_chart_config', true);

        if (!$config) {
            return new \WP_REST_Response(['error' => 'Chart not found'], 404);
        }

        $visualizer = Plugin::get_instance()->visualizer();

        return new \WP_REST_Response([
            'data'   => $visualizer->get_chart_data($config),
            'config' => ['type' => $config['chart_type'], 'title' => get_the_title($chart_id)],
        ]);
    }

    public function get_chart_csv(\WP_REST_Request $request): void
    {
        // FIX I2: rate limit por IP (reutiliza consulta_rate_limited — máx. 30 req/min)
        if ($this->consulta_rate_limited()) {
            status_header(429);
            echo 'Demasiadas solicitudes';
            exit;
        }

        $chart_id = (int) $request->get_param('id');
        $config   = get_post_meta($chart_id, '_secop_chart_config', true);

        if (!$config) {
            status_header(404);
            echo 'Chart not found';
            exit;
        }

        $data = Plugin::get_instance()->visualizer()->get_chart_data($config);

        if (empty($data)) {
            status_header(404);
            echo 'No data';
            exit;
        }

        // Cabeceras para descarga CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="chart-' . intval($chart_id) . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        $output = fopen('php://output', 'w');
        // BOM para Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        // FIX I3: csv_safe on column headers and data cells
        fputcsv($output, array_map([self::class, 'csv_safe'], array_keys($data[0])));
        foreach ($data as $row) {
            fputcsv($output, array_map([self::class, 'csv_safe'], $row));
        }
        fclose($output);
        exit;
    }

    // ── Exportación completa de datos ──────────────────────────

    public function export_csv(\WP_REST_Request $request): void
    {
        // FIX C1: rate limit (reuse consulta_rate_limited — max 30 req/min per IP)
        if ($this->consulta_rate_limited()) {
            status_header(429);
            echo 'Demasiadas solicitudes';
            exit;
        }

        global $wpdb;
        $table      = $this->db->get_table_name();
        $batch_size = 2000;
        $offset     = 0;

        // v5.11.0: filtrado genérico por URL (=, _like, _min, _max) + order_by/order, sin PII.
        [$filters, $fvals] = $this->url_field_filters($request, $table, self::PII_COLS);
        $order_sql = $this->url_order($request, $table, 'fecha_de_firma_del_contrato', 'DESC');
        $where_sql = '1=1';
        if (!empty($filters)) {
            $where_sql .= ' AND ' . implode(' AND ', $filters);
        }

        // First batch — needed to detect empty data and write CSV column headers
        // Orden de params: [...filtros, LIMIT, OFFSET] — coincide con WHERE <filtros> ... LIMIT %d OFFSET %d.
        $first_batch = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_sql} {$order_sql} LIMIT %d OFFSET %d",
            array_merge($fvals, [$batch_size, $offset])
        ), ARRAY_A);

        if (empty($first_batch)) {
            status_header(404);
            echo 'No hay datos para exportar';
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="secop-contratos-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        // Ley 1581: lista ordenada de columnas SIN los datos personales del proveedor.
        // Se calcula una vez y se usa tanto para la cabecera como para cada fila, de modo
        // que las columnas queden alineadas y el documento del proveedor nunca se exporte.
        $columns = array_values(array_diff(array_keys($first_batch[0]), self::PII_COLS));

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        // FIX I3: csv_safe on column headers
        fputcsv($output, array_map([self::class, 'csv_safe'], $columns));
        // Stream all batches
        $batch = $first_batch;
        while (!empty($batch)) {
            foreach ($batch as $row) {
                // FIX I3 + Ley 1581: csv_safe en cada celda, en el mismo orden de columnas (sin PII)
                $cells = [];
                foreach ($columns as $col) {
                    $cells[] = self::csv_safe($row[$col] ?? '');
                }
                fputcsv($output, $cells);
            }
            if (count($batch) < $batch_size) {
                break;
            }
            $offset += $batch_size;
            $batch = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} {$order_sql} LIMIT %d OFFSET %d",
                array_merge($fvals, [$batch_size, $offset])
            ), ARRAY_A);
        }
        fclose($output);
        exit;
    }

    public function export_txt(\WP_REST_Request $request): void
    {
        // FIX C1: rate limit (reuse consulta_rate_limited — max 30 req/min per IP)
        if ($this->consulta_rate_limited()) {
            status_header(429);
            echo 'Demasiadas solicitudes';
            exit;
        }

        global $wpdb;
        $table      = $this->db->get_table_name();
        $batch_size = 2000;
        $offset     = 0;

        // v5.11.0: filtrado genérico por URL (=, _like, _min, _max) + order_by/order, sin PII.
        [$filters, $fvals] = $this->url_field_filters($request, $table, self::PII_COLS);
        $order_sql = $this->url_order($request, $table, 'fecha_de_firma_del_contrato', 'DESC');
        $where_sql = '1=1';
        if (!empty($filters)) {
            $where_sql .= ' AND ' . implode(' AND ', $filters);
        }

        // First batch — needed to check for data and compute column widths
        // Orden de params: [...filtros, LIMIT, OFFSET] — coincide con WHERE <filtros> ... LIMIT %d OFFSET %d.
        $first_batch = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_sql} {$order_sql} LIMIT %d OFFSET %d",
            array_merge($fvals, [$batch_size, $offset])
        ), ARRAY_A);

        if (empty($first_batch)) {
            status_header(404);
            echo 'No hay datos para exportar';
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="secop-contratos-' . date('Y-m-d') . '.txt"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        // Compute column widths from first batch only (acceptable for fixed-width TXT)
        // Ley 1581: se excluyen los datos personales del proveedor de la lista de columnas.
        $columns = array_values(array_diff(array_keys($first_batch[0]), self::PII_COLS));
        $widths  = [];
        foreach ($columns as $col) {
            $widths[$col] = max(mb_strlen($col), 15);
        }

        // Header row
        $line = '';
        foreach ($columns as $col) {
            $line .= str_pad($col, $widths[$col] + 2);
        }
        echo $line . "\n";
        echo str_repeat('=', mb_strlen($line)) . "\n";

        // Stream all batches
        $batch = $first_batch;
        while (!empty($batch)) {
            foreach ($batch as $row) {
                $line = '';
                foreach ($columns as $col) {
                    $val = (string) ($row[$col] ?? '');
                    if (mb_strlen($val) > $widths[$col]) {
                        $val = mb_substr($val, 0, $widths[$col] - 2) . '..';
                    }
                    $line .= str_pad($val, $widths[$col] + 2);
                }
                echo $line . "\n";
            }
            if (count($batch) < $batch_size) {
                break;
            }
            $offset += $batch_size;
            $batch = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} {$order_sql} LIMIT %d OFFSET %d",
                array_merge($fvals, [$batch_size, $offset])
            ), ARRAY_A);
        }
        exit;
    }

    // ── Consulta (Datos Abiertos — vigencia actual) ────────────

    /**
     * FIX 4: rate limit compartido para los tres endpoints /consulta*.
     * Permite hasta 30 solicitudes/minuto por IP.
     */
    private function consulta_rate_limited(): bool
    {
        $ip_key = 'secop_consulta_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        $count  = (int) get_transient($ip_key);
        if ($count > 30) {
            return true;
        }
        set_transient($ip_key, $count + 1, MINUTE_IN_SECONDS);
        return false;
    }

    /**
     * FIX 5: protección contra inyección de fórmulas CSV (Excel/LibreOffice).
     * Si el valor empieza con = + - @ o tabulador/retorno, se prefija con comilla simple.
     */
    private static function csv_safe(mixed $v): string
    {
        $str = (string) $v;
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }
        return $str;
    }

    /**
     * Construye filtros WHERE a partir de los parámetros de la URL contra las columnas
     * REALES de una tabla/vista. Soporta:
     *   ?columna=valor          → igualdad exacta
     *   ?columna_like=valor     → contiene (LIKE %valor%)
     *   ?columna_min=valor      → >= valor   (numérico/fecha)
     *   ?columna_max=valor      → <= valor
     * Columnas validadas contra get_table_columns(); valores vía $wpdb->prepare.
     * @return array{0:array<string>,1:array} [fragmentos WHERE, params]
     */
    private function url_field_filters(\WP_REST_Request $request, string $source, array $exclude = []): array
    {
        global $wpdb;
        $columns = $this->db->get_table_columns($source); // [col => type]
        $params  = $request->get_params(); // incluye query vars
        $where = []; $vals = [];
        foreach ($params as $key => $value) {
            if ($value === '' || is_array($value)) {
                continue;
            }
            // sufijos de operador
            $op = '='; $col = $key;
            if (str_ends_with($key, '_like')) { $op = 'LIKE'; $col = substr($key, 0, -5); }
            elseif (str_ends_with($key, '_min')) { $op = '>=';  $col = substr($key, 0, -4); }
            elseif (str_ends_with($key, '_max')) { $op = '<=';  $col = substr($key, 0, -4); }
            if (!isset($columns[$col]) || in_array($col, $exclude, true)) {
                continue;
            }
            if ($op === 'LIKE') {
                $where[] = "`{$col}` LIKE %s";
                $vals[]  = '%' . $wpdb->esc_like(sanitize_text_field((string) $value)) . '%';
            } else {
                $where[] = "`{$col}` {$op} %s";
                $vals[]  = sanitize_text_field((string) $value);
            }
        }
        return [$where, $vals];
    }

    /**
     * Devuelve una cláusula ORDER BY segura a partir de los parámetros `order_by`/`order`
     * de la URL. La columna se valida contra las columnas reales de la tabla/vista (y nunca
     * puede ser PII); la dirección se restringe a ASC|DESC. Si algo no valida, usa el default.
     */
    private function url_order(\WP_REST_Request $request, string $source, string $default_col, string $default_dir = 'DESC'): string
    {
        $columns  = $this->db->get_table_columns($source);
        $order_by = (string) $request->get_param('order_by');
        $order    = strtoupper((string) $request->get_param('order'));
        $dir      = in_array($order, ['ASC', 'DESC'], true) ? $order : $default_dir;

        $col = $default_col;
        if ($order_by !== '' && isset($columns[$order_by]) && !in_array($order_by, self::PII_COLS, true)) {
            $col = $order_by;
        }
        return "ORDER BY `{$col}` {$dir}";
    }

    public function get_consulta(\WP_REST_Request $request): \WP_REST_Response
    {
        // FIX 4: rate limit por IP
        if ($this->consulta_rate_limited()) {
            return new \WP_REST_Response(['message' => 'Demasiadas solicitudes'], 429);
        }
        global $wpdb;
        $view     = $this->db->get_view_name();
        $vigencia = (int) current_time('Y');
        $per_page = max(1, min((int) $request->get_param('per_page'), 1000));
        $page     = max(1, (int) $request->get_param('page'));
        $offset   = ($page - 1) * $per_page;

        // v5.11.0: filtrado genérico por cualquier columna de la vista desde la URL
        // (=, _like, _min, _max) + order_by/order, validados contra columnas reales y
        // sin exponer/filtrar datos personales (PII). El default de orden es valordebito DESC.
        [$filters, $fvals] = $this->url_field_filters($request, $view, self::PII_COLS);
        $order_sql = $this->url_order($request, $view, 'valordebito', 'DESC');

        $where_sql = 'YEAR(`fecha_de_firma_del_contrato`) = %d';
        if (!empty($filters)) {
            $where_sql .= ' AND ' . implode(' AND ', $filters);
        }

        // La clave de caché incluye el hash de TODOS los parámetros (filtros + orden),
        // de modo que distintas combinaciones de filtros se cachean por separado.
        $cache_key = 'secop_trk_' . md5('rest_consulta|' . $page . '|' . $per_page . '|' . $vigencia . '|' . md5(wp_json_encode($request->get_params())));
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return new \WP_REST_Response($cached);
        }

        // v5.9.0: la vista cambió (LEFT JOIN desde secop_contracts). La vigencia es
        // YEAR(fecha_de_firma_del_contrato); las columnas del asiento se renombraron a
        // *_asiento. Se conservan los alias `anio`/`mes` del payload para no romper a
        // los consumidores (anio = año de firma; mes = mes del asiento Sysman). Los
        // contratos sin cruce Sysman se etiquetan "No Registra SYSMAN".
        // Orden de params: [vigencia, ...filtros, per_page, offset] — coincide con los
        // placeholders: WHERE YEAR=%d AND <filtros %s...> ... LIMIT %d OFFSET %d.
        $params = array_merge([$vigencia], $fvals, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(NULLIF(`nombredependencia`,''),'No Registra SYSMAN') AS nombredependencia,
                    numero_de_proceso, numero_del_contrato,
                    COALESCE(NULLIF(`nombretercero`,''), NULLIF(`nom_raz_social_contratista`,''),'No Registra SYSMAN') AS nombretercero,
                    valordebito, valorcredito, saldoporejecutaresp,
                    valor_contrato, YEAR(`fecha_de_firma_del_contrato`) AS anio, mes_asiento AS mes
             FROM `{$view}` WHERE {$where_sql}
             {$order_sql} LIMIT %d OFFSET %d",
            $params
        ), ARRAY_A);

        $payload = [
            'vigencia' => $vigencia,
            'page'     => $page,
            'data'     => $rows ?: [],
        ];
        set_transient($cache_key, $payload, 30 * MINUTE_IN_SECONDS);

        return new \WP_REST_Response($payload);
    }

    public function get_consulta_csv(\WP_REST_Request $request): void
    {
        // FIX 4: rate limit por IP
        if ($this->consulta_rate_limited()) {
            status_header(429);
            echo 'Demasiadas solicitudes';
            exit;
        }
        global $wpdb;
        $view     = $this->db->get_view_name();
        $vigencia = (int) current_time('Y');

        // Exporta TODA la información de la vista (todas las columnas) para la vigencia actual.
        // v5.9.0: vigencia por año de firma del contrato.
        // v5.11.0: filtrado genérico por URL (=, _like, _min, _max) + order_by/order, sin PII.
        [$filters, $fvals] = $this->url_field_filters($request, $view, self::PII_COLS);
        $order_sql = $this->url_order($request, $view, 'valor_contrato', 'DESC');
        $where_sql = 'YEAR(`fecha_de_firma_del_contrato`) = %d';
        if (!empty($filters)) {
            $where_sql .= ' AND ' . implode(' AND ', $filters);
        }
        // Orden de params: [vigencia, ...filtros] — coincide con WHERE YEAR=%d AND <filtros>.
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$view}` WHERE {$where_sql} {$order_sql}",
            array_merge([$vigencia], $fvals)
        ), ARRAY_A);

        if (empty($data)) {
            status_header(404);
            echo 'No hay datos para exportar';
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="secop-consulta-' . $vigencia . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        // Ley 1581: lista ordenada de columnas SIN los datos personales del proveedor,
        // usada para la cabecera y para cada fila (columnas alineadas, sin documento del proveedor).
        $columns = array_values(array_diff(array_keys($data[0]), self::PII_COLS));

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        // FIX 5: cabecera de columnas (nombres de columna son internos, seguros; se sanitizan por si acaso)
        fputcsv($output, array_map([self::class, 'csv_safe'], $columns));
        foreach ($data as $row) {
            // FIX 5 + Ley 1581: csv_safe en cada celda, en el mismo orden de columnas (sin PII)
            $cells = [];
            foreach ($columns as $col) {
                $cells[] = self::csv_safe($row[$col] ?? '');
            }
            fputcsv($output, $cells);
        }
        fclose($output);
        exit;
    }

    public function get_consulta_txt(\WP_REST_Request $request): void
    {
        // FIX 4: rate limit por IP
        if ($this->consulta_rate_limited()) {
            status_header(429);
            echo 'Demasiadas solicitudes';
            exit;
        }
        global $wpdb;
        $view     = $this->db->get_view_name();
        $vigencia = (int) current_time('Y');

        // Exporta TODA la información de la vista (todas las columnas) para la vigencia actual.
        // v5.9.0: vigencia por año de firma del contrato.
        // v5.11.0: filtrado genérico por URL (=, _like, _min, _max) + order_by/order, sin PII.
        [$filters, $fvals] = $this->url_field_filters($request, $view, self::PII_COLS);
        $order_sql = $this->url_order($request, $view, 'valor_contrato', 'DESC');
        $where_sql = 'YEAR(`fecha_de_firma_del_contrato`) = %d';
        if (!empty($filters)) {
            $where_sql .= ' AND ' . implode(' AND ', $filters);
        }
        // Orden de params: [vigencia, ...filtros] — coincide con WHERE YEAR=%d AND <filtros>.
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$view}` WHERE {$where_sql} {$order_sql}",
            array_merge([$vigencia], $fvals)
        ), ARRAY_A);

        if (empty($data)) {
            status_header(404);
            echo 'No hay datos para exportar';
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="secop-consulta-' . $vigencia . '.txt"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        // Ley 1581: se excluyen los datos personales del proveedor de la lista de columnas.
        $columns = array_values(array_diff(array_keys($data[0]), self::PII_COLS));
        $widths  = [];
        foreach ($columns as $col) {
            $widths[$col] = max(mb_strlen($col), 15);
        }

        // Header row
        $line = '';
        foreach ($columns as $col) {
            $line .= str_pad($col, $widths[$col] + 2);
        }
        echo $line . "\n";
        echo str_repeat('=', mb_strlen($line)) . "\n";

        // Data rows
        foreach ($data as $row) {
            $line = '';
            foreach ($columns as $col) {
                $val = (string) ($row[$col] ?? '');
                if (mb_strlen($val) > $widths[$col]) {
                    $val = mb_substr($val, 0, $widths[$col] - 2) . '..';
                }
                $line .= str_pad($val, $widths[$col] + 2);
            }
            echo $line . "\n";
        }
        exit;
    }
}
