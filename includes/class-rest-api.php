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

    /** Columnas de datos personales — Ley 1581 (nunca se exponen en endpoints públicos). */
    private const PII_COLS = Open_Data::PII_COLS;

    /** Filas por lote en las descargas CSV/TXT. */
    private const EXPORT_BATCH = 2000;

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
        // agrupar=contrato (predeterminado, una fila por contrato) | detalle.
        $agrupar_arg = ['default' => 'contrato', 'sanitize_callback' => 'sanitize_key'];
        register_rest_route(self::NAMESPACE, '/consulta', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_consulta'],
            'permission_callback' => '__return_true',
            'args'                => [
                'page'     => ['default' => 1,   'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 100, 'sanitize_callback' => 'absint'],
                'agrupar'  => $agrupar_arg,
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/consulta/csv', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_consulta_csv'],
            'permission_callback' => '__return_true',
            'args'                => ['agrupar' => $agrupar_arg],
        ]);

        register_rest_route(self::NAMESPACE, '/consulta/txt', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_consulta_txt'],
            'permission_callback' => '__return_true',
            'args'                => ['agrupar' => $agrupar_arg],
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

        // max(1, ...): per_page=0 provocaba DivisionByZeroError en total_pages y
        // page=0 un OFFSET negativo (error SQL). Igual que en get_consulta().
        $per_page = max(1, min((int) $request->get_param('per_page'), 100));
        $page     = max(1, (int) $request->get_param('page'));
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

    /**
     * Devuelve la config de la gráfica SOLO si el post es una gráfica/card
     * publicada. Sin esto, cualquier visitante podía ejecutar configuraciones
     * de posts en borrador, privados o en papelera.
     */
    private function published_chart_config(int $chart_id): array|false
    {
        $post = get_post($chart_id);
        if (!$post
            || !in_array($post->post_type, ['secop_chart', 'secop_dep_card'], true)
            || $post->post_status !== 'publish') {
            return false;
        }
        $config = get_post_meta($chart_id, '_secop_chart_config', true);
        return is_array($config) && $config ? $config : false;
    }

    public function get_chart_data(\WP_REST_Request $request): \WP_REST_Response
    {
        // FIX I2: rate limit por IP (reutiliza consulta_rate_limited — máx. 30 req/min)
        if ($this->consulta_rate_limited()) {
            return new \WP_REST_Response(['message' => 'Demasiadas solicitudes'], 429);
        }

        $chart_id = (int) $request->get_param('id');
        $config   = $this->published_chart_config($chart_id);

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
        $config   = $this->published_chart_config($chart_id);

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
        fputcsv($output, array_map([self::class, 'csv_safe'], array_keys($data[0])), ',', '"', '');
        foreach ($data as $row) {
            fputcsv($output, array_map([self::class, 'csv_safe'], $row), ',', '"', '');
        }
        fclose($output);
        exit;
    }

    // ── Exportación completa de datos ──────────────────────────

    public function export_csv(\WP_REST_Request $request): void
    {
        $this->export_contracts($request, 'csv');
    }

    public function export_txt(\WP_REST_Request $request): void
    {
        $this->export_contracts($request, 'txt');
    }

    /**
     * Descarga de la tabla de contratos (un registro por número de contrato,
     * garantizado por el índice único). Filtros y orden por URL, sin PII.
     */
    private function export_contracts(\WP_REST_Request $request, string $format): void
    {
        // FIX C1: rate limit (reuse consulta_rate_limited — max 30 req/min per IP)
        if ($this->consulta_rate_limited()) {
            status_header(429);
            echo 'Demasiadas solicitudes';
            exit;
        }

        global $wpdb;
        $table = $this->db->get_table_name();

        // v5.11.0: filtrado genérico por URL (=, _like, _min, _max) + order_by/order, sin PII.
        [$filters, $fvals] = $this->url_field_filters($request, $table, self::PII_COLS);
        $order_sql = $this->url_order($request, $table, 'fecha_de_firma_del_contrato', 'DESC');
        $where_sql = $filters ? implode(' AND ', $filters) : '1=1';

        $this->stream_download(
            $format,
            'secop-contratos-' . date('Y-m-d'),
            static function (int $offset, int $limit) use ($wpdb, $table, $where_sql, $order_sql, $fvals): array {
                // Orden de params: [...filtros, LIMIT, OFFSET].
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table} WHERE {$where_sql} {$order_sql} LIMIT %d OFFSET %d",
                    array_merge($fvals, [$limit, $offset])
                ), ARRAY_A) ?: [];
            }
        );
    }

    /**
     * Escribe una descarga CSV o TXT por lotes. $fetch(offset, limit) devuelve
     * filas ARRAY_A con un orden total (sin empates), de modo que ningún
     * registro se repita ni se omita entre lotes. Las columnas PII se descartan.
     */
    private function stream_download(string $format, string $basename, callable $fetch): void
    {
        $batch = $fetch(0, self::EXPORT_BATCH);
        if (empty($batch)) {
            status_header(404);
            echo 'No hay datos para exportar';
            exit;
        }

        $is_csv = $format === 'csv';
        header('Content-Type: ' . ($is_csv ? 'text/csv' : 'text/plain') . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($basename) . '.' . ($is_csv ? 'csv' : 'txt') . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Content-Type-Options: nosniff');

        // Ley 1581: columnas fijadas una vez (cabecera y filas alineadas, sin PII).
        $columns = array_values(array_diff(array_keys($batch[0]), self::PII_COLS));

        $output = fopen('php://output', 'w');
        if ($is_csv) {
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
            // FIX I3: csv_safe también en la cabecera. Escape vacío = RFC 4180.
            fputcsv($output, array_map([self::class, 'csv_safe'], $columns), ',', '"', '');
        } else {
            $widths = [];
            foreach ($columns as $col) {
                $widths[$col] = max(mb_strlen($col), 15);
            }
            $line = '';
            foreach ($columns as $col) {
                $line .= str_pad($col, $widths[$col] + 2);
            }
            fwrite($output, $line . "\n" . str_repeat('=', mb_strlen($line)) . "\n");
        }

        $offset = 0;
        while (!empty($batch)) {
            foreach ($batch as $row) {
                if ($is_csv) {
                    $cells = [];
                    foreach ($columns as $col) {
                        $cells[] = self::csv_safe($row[$col] ?? '');
                    }
                    fputcsv($output, $cells, ',', '"', '');
                    continue;
                }
                $line = '';
                foreach ($columns as $col) {
                    $val = preg_replace('/\s+/u', ' ', (string) ($row[$col] ?? ''));
                    if (mb_strlen($val) > $widths[$col]) {
                        $val = mb_substr($val, 0, $widths[$col] - 2) . '..';
                    }
                    $line .= str_pad($val, $widths[$col] + 2);
                }
                fwrite($output, $line . "\n");
            }
            if (count($batch) < self::EXPORT_BATCH) {
                break;
            }
            $offset += self::EXPORT_BATCH;
            $batch = $fetch($offset, self::EXPORT_BATCH);
        }
        fclose($output);
        exit;
    }

    // ── Consulta (Datos Abiertos — vigencia actual) ────────────

    /**
     * FIX 4: rate limit compartido para los tres endpoints /consulta*.
     * Permite hasta 30 solicitudes/minuto por IP.
     */
    private function consulta_rate_limited(): bool
    {
        return Rate_Limiter::limited('consulta', 30);
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
        // Tie-breaker estable: sin él, la exportación por lotes con LIMIT/OFFSET
        // sobre una columna con empates puede duplicar u omitir filas entre lotes.
        $tie = '';
        if ($col !== 'id' && isset($columns['id'])) {
            $tie = ', `id` DESC';
        }
        return "ORDER BY `{$col}` {$dir}{$tie}";
    }

    /**
     * Prepara la consulta deduplicada de /consulta a partir de la URL.
     * Devuelve null si el VIEW no existe.
     *
     * @return array{select:string,count:string,columns:array<int,string>,order:string,params:array,grouping:string,vigencia:int}|null
     */
    private function consulta_query(\WP_REST_Request $request, string $default_order): ?array
    {
        if (!$this->db->view_exists()) {
            return null;
        }
        global $wpdb;
        $view     = $this->db->get_view_name();
        $columns  = $this->db->get_table_columns($view);
        $grouping = (string) $request->get_param('agrupar');
        $grouping = in_array($grouping, Open_Data::GROUPINGS, true) ? $grouping : 'contrato';
        $vigencia = (int) current_time('Y');

        // v5.11.0: filtros por cualquier columna del VIEW (validados, sin PII). En la
        // agrupación por contrato se aplican a las filas de detalle antes de agrupar.
        [$filters, $fvals] = $this->url_field_filters($request, $view, self::PII_COLS);

        $sql   = Open_Data::consulta_sql($view, $columns, $grouping, $filters);
        $order = Open_Data::consulta_order(
            $sql['columns'],
            $grouping,
            (string) $request->get_param('order_by'),
            (string) ($request->get_param('order') ?: 'DESC'),
            $grouping === 'detalle' ? 'valordebito' : $default_order
        );

        // Las listas de dependencias/rubros usan GROUP_CONCAT (1024 bytes por defecto).
        $wpdb->query('SET SESSION group_concat_max_len = 65535');

        return $sql + [
            'order'    => $order,
            // Orden de params: [vigencia, ...filtros] — WHERE YEAR=%d AND <filtros>.
            'params'   => array_merge([$vigencia], $fvals),
            'grouping' => $grouping,
            'vigencia' => $vigencia,
        ];
    }

    /**
     * JSON paginado de la vigencia actual SIN duplicados. Por defecto una fila por
     * contrato (agrupar=contrato); agrupar=detalle entrega un asiento distinto por fila.
     */
    public function get_consulta(\WP_REST_Request $request): \WP_REST_Response
    {
        // FIX 4: rate limit por IP
        if ($this->consulta_rate_limited()) {
            return new \WP_REST_Response(['message' => 'Demasiadas solicitudes'], 429);
        }
        global $wpdb;
        $per_page = max(1, min((int) $request->get_param('per_page'), 1000));
        $page     = max(1, (int) $request->get_param('page'));
        $offset   = ($page - 1) * $per_page;

        // La clave de caché incluye TODOS los parámetros (filtros, orden, agrupación).
        $cache_key = 'secop_trk_' . md5('rest_consulta_v2|' . $page . '|' . $per_page . '|' . current_time('Y') . '|' . md5((string) wp_json_encode($request->get_params())));
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return new \WP_REST_Response($cached);
        }

        $q = $this->consulta_query($request, 'valor_efectivo');
        if ($q === null) {
            return new \WP_REST_Response(['message' => 'La vista de consulta no está disponible'], 503);
        }

        $total = (int) $wpdb->get_var($wpdb->prepare($q['count'], $q['params']));
        $rows  = $wpdb->get_results($wpdb->prepare(
            "{$q['select']} {$q['order']} LIMIT %d OFFSET %d",
            array_merge($q['params'], [$per_page, $offset])
        ), ARRAY_A);

        $payload = [
            'vigencia'    => $q['vigencia'],
            'agrupacion'  => $q['grouping'],
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => max(1, (int) ceil($total / $per_page)),
            'data'        => $rows ?: [],
        ];
        set_transient($cache_key, $payload, 30 * MINUTE_IN_SECONDS);

        return new \WP_REST_Response($payload);
    }

    public function get_consulta_csv(\WP_REST_Request $request): void
    {
        $this->export_consulta($request, 'csv');
    }

    public function get_consulta_txt(\WP_REST_Request $request): void
    {
        $this->export_consulta($request, 'txt');
    }

    /** Descarga completa de la vigencia actual SIN duplicados (misma lógica que el JSON). */
    private function export_consulta(\WP_REST_Request $request, string $format): void
    {
        // FIX 4: rate limit por IP
        if ($this->consulta_rate_limited()) {
            status_header(429);
            echo 'Demasiadas solicitudes';
            exit;
        }

        $q = $this->consulta_query($request, 'valor_efectivo');
        if ($q === null) {
            status_header(404);
            echo 'No hay datos para exportar';
            exit;
        }

        global $wpdb;
        $suffix = $q['grouping'] === 'detalle' ? '-detalle' : '';
        $this->stream_download(
            $format,
            'secop-consulta-' . $q['vigencia'] . $suffix,
            static function (int $offset, int $limit) use ($wpdb, $q): array {
                return $wpdb->get_results($wpdb->prepare(
                    "{$q['select']} {$q['order']} LIMIT %d OFFSET %d",
                    array_merge($q['params'], [$limit, $offset])
                ), ARRAY_A) ?: [];
            }
        );
    }
}
