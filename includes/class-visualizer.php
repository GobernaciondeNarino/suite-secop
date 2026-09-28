<?php
/**
 * Visualizer — CPT de gráficas, shortcodes y generación de datos.
 *
 * Mejoras de seguridad respecto al plugin original:
 *  - Validación de nombres de tabla contra whitelist (get_available_tables)
 *  - Validación de nombres de columna contra DESCRIBE real de la tabla
 *  - Las queries personalizadas se restringen a SELECT y se validan
 *  - Funciones de agregación contra whitelist
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Visualizer
{
    private Database $db;
    private const POST_TYPE = 'secop_chart';

    /** Funciones de agregación permitidas */
    private const ALLOWED_AGGREGATES = ['SUM', 'COUNT', 'COUNT_DISTINCT', 'AVG', 'MAX', 'MIN'];

    /** Operadores de filtro permitidos */
    private const ALLOWED_OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'LIKE'];

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->register_hooks();
    }

    private function register_hooks(): void
    {
        // CPT
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_chart_meta'], 10, 2);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Shortcodes
        add_shortcode('sdv_chart',    [$this, 'render_chart_shortcode']);
        add_shortcode('secop_chart',  [$this, 'render_chart_shortcode']);
        add_shortcode('secop_export', [$this, 'render_export_shortcode']);

        // AJAX
        add_action('wp_ajax_secop_suite_get_chart_data',    [$this, 'ajax_get_chart_data']);
        add_action('wp_ajax_nopriv_secop_suite_get_chart_data', [$this, 'ajax_get_chart_data']);
        add_action('wp_ajax_secop_suite_get_table_columns', [$this, 'ajax_get_table_columns']);
        add_action('wp_ajax_secop_suite_preview_data',      [$this, 'ajax_preview_data']);

        // Admin columns
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);

        // Compartir librerías d3/d3plus con otros módulos.
        add_action('secop_suite_enqueue_chart_libs', [$this, 'enqueue_chart_libraries']);
    }

    // ── CPT ────────────────────────────────────────────────────
    public function register_post_type(): void
    {
        $labels = [
            'name'               => __('Gráficas SECOP', 'secop-suite'),
            'singular_name'      => __('Gráfica', 'secop-suite'),
            'menu_name'          => __('Gráficas', 'secop-suite'),
            'add_new'            => __('Nueva Gráfica', 'secop-suite'),
            'add_new_item'       => __('Añadir Nueva Gráfica', 'secop-suite'),
            'edit_item'          => __('Editar Gráfica', 'secop-suite'),
            'new_item'           => __('Nueva Gráfica', 'secop-suite'),
            'view_item'          => __('Ver Gráfica', 'secop-suite'),
            'search_items'       => __('Buscar Gráficas', 'secop-suite'),
            'not_found'          => __('No se encontraron gráficas', 'secop-suite'),
            'not_found_in_trash' => __('No hay gráficas en la papelera', 'secop-suite'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'secop-suite',
            'query_var'           => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_icon'           => 'dashicons-chart-bar',
            'supports'            => ['title'],
            'show_in_rest'        => true,
        ]);
    }

    // ── Meta Boxes ─────────────────────────────────────────────
    public function add_meta_boxes(): void
    {
        add_meta_box('secop_chart_config',    __('Configuración de la Gráfica', 'secop-suite'), [$this, 'render_config_metabox'],    self::POST_TYPE, 'normal', 'high');
        add_meta_box('secop_chart_shortcode', __('Shortcode', 'secop-suite'),                   [$this, 'render_shortcode_metabox'], self::POST_TYPE, 'side',   'high');
        add_meta_box('secop_chart_preview',   __('Vista Previa', 'secop-suite'),                [$this, 'render_preview_metabox'],   self::POST_TYPE, 'normal', 'default');
    }

    public function render_config_metabox(\WP_Post $post): void
    {
        wp_nonce_field('secop_suite_chart_config', 'secop_suite_chart_nonce');
        $config = get_post_meta($post->ID, '_secop_chart_config', true) ?: [];
        $tables = $this->db->get_available_tables();
        include SECOP_SUITE_DIR . 'templates/admin/chart-config.php';
    }

    public function render_shortcode_metabox(\WP_Post $post): void
    {
        ?>
        <div class="ss-shortcode-box">
            <p><?php _e('Copia este shortcode para insertar la gráfica:', 'secop-suite'); ?></p>
            <code id="ss-shortcode-display">[secop_chart id="<?php echo esc_attr($post->ID); ?>"]</code>
            <button type="button" class="button ss-copy-shortcode" data-clipboard-target="#ss-shortcode-display">
                <span class="dashicons dashicons-clipboard"></span> <?php _e('Copiar', 'secop-suite'); ?>
            </button>
        </div>
        <?php
    }

    public function render_preview_metabox(\WP_Post $post): void
    {
        ?>
        <div class="ss-preview-container">
            <button type="button" class="button button-primary" id="ss-refresh-preview">
                <span class="dashicons dashicons-update"></span> <?php _e('Actualizar Vista Previa', 'secop-suite'); ?>
            </button>
            <div id="ss-chart-preview" class="ss-chart-wrapper">
                <p class="ss-preview-placeholder"><?php _e('Configure la gráfica y haga clic en "Actualizar Vista Previa"', 'secop-suite'); ?></p>
            </div>
        </div>
        <?php
    }

    // ── Guardar meta ───────────────────────────────────────────
    public function save_chart_meta(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['secop_suite_chart_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['secop_suite_chart_nonce'] ?? '')), 'secop_suite_chart_config')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $config = [
            'chart_type'       => sanitize_text_field($_POST['ss_chart_type'] ?? 'bar'),
            'table_name'       => sanitize_text_field($_POST['ss_table_name'] ?? ''),
            'x_field'          => sanitize_text_field($_POST['ss_x_field'] ?? ''),
            'x_date_grouping'  => sanitize_text_field($_POST['ss_x_date_grouping'] ?? ''),
            'y_field'          => sanitize_text_field($_POST['ss_y_field'] ?? ''),
            'y_fields'         => $this->sanitize_y_fields($_POST['ss_y_fields'] ?? []),
            'group_by'         => sanitize_text_field($_POST['ss_group_by'] ?? ''),
            'aggregate'        => sanitize_text_field($_POST['ss_aggregate'] ?? 'SUM'),
            'color_field'      => sanitize_text_field($_POST['ss_color_field'] ?? ''),
            'filters'          => $this->sanitize_filters($_POST['ss_filters'] ?? []),
            'date_field'       => sanitize_text_field($_POST['ss_date_field'] ?? ''),
            'date_from'        => sanitize_text_field($_POST['ss_date_from'] ?? ''),
            'date_to'          => sanitize_text_field($_POST['ss_date_to'] ?? ''),
            'limit'            => intval($_POST['ss_limit'] ?? 0),
            'order_by'         => sanitize_text_field($_POST['ss_order_by'] ?? ''),
            'order_dir'        => sanitize_text_field($_POST['ss_order_dir'] ?? 'DESC'),
            'colors'           => $this->sanitize_colors($_POST['ss_colors'] ?? ''),
            'show_legend'      => isset($_POST['ss_show_legend']),
            'legend_mode'      => sanitize_text_field($_POST['ss_legend_mode'] ?? 'text'),
            'legend_position'  => sanitize_text_field($_POST['ss_legend_position'] ?? 'bottom'),
            'show_timeline'    => isset($_POST['ss_show_timeline']),
            'show_toolbar'     => isset($_POST['ss_show_toolbar']),
            'toolbar_options'  => array_map('sanitize_text_field', $_POST['ss_toolbar_options'] ?? ['share', 'data', 'image', 'download']),
            'chart_height'     => intval($_POST['ss_chart_height'] ?? 400),
            'y_axis_title'     => sanitize_text_field($_POST['ss_y_axis_title'] ?? ''),
            'x_axis_title'     => sanitize_text_field($_POST['ss_x_axis_title'] ?? ''),
            'number_format'    => sanitize_text_field($_POST['ss_number_format'] ?? 'colombiano'),
            // ★ SEGURIDAD (C1): la query personalizada SOLO la pueden guardar
            // administradores (manage_options), que de por sí ya pueden ejecutar SQL.
            // Cualquier otro rol (incl. quien tenga edit_posts vía el CPT) recibe ''.
            'custom_query'     => (current_user_can('manage_options') && isset($_POST['ss_use_custom_query']))
                ? $this->sanitize_custom_query($_POST['ss_custom_query'] ?? '')
                : '',
        ];

        update_post_meta($post_id, '_secop_chart_config', $config);
    }

    private function sanitize_filters(array $filters): array
    {
        $sanitized = [];
        foreach ($filters as $filter) {
            if (!empty($filter['field'])) {
                $sanitized[] = [
                    'field'    => sanitize_text_field($filter['field']),
                    'operator' => sanitize_text_field($filter['operator'] ?? '='),
                    'value'    => sanitize_text_field($filter['value'] ?? ''),
                ];
            }
        }
        return $sanitized;
    }

    private function sanitize_colors(string $colors): array
    {
        $arr = array_filter(array_map('trim', explode(',', $colors)));
        return array_map(fn($c) => preg_match('/^#[a-fA-F0-9]{6}$/', $c) ? $c : '#0082c6', $arr);
    }

    private function sanitize_y_fields(array $fields): array
    {
        $sanitized = [];
        foreach ($fields as $field) {
            if (!empty($field['column'])) {
                $sanitized[] = [
                    'column' => sanitize_text_field($field['column']),
                    'label'  => sanitize_text_field($field['label'] ?? ''),
                ];
            }
        }
        return $sanitized;
    }

    /**
     * ★ SEGURIDAD: Validar que la query personalizada sea solo SELECT.
     * Se eliminan comentarios SQL, se normalizan espacios, y se aplica
     * una lista extensa de palabras y funciones prohibidas.
     */
    private function sanitize_custom_query(string $query): string
    {
        $trimmed = trim($query);

        if (empty($trimmed)) {
            return '';
        }

        // Eliminar comentarios SQL (/* ... */, --, #) para evitar evasión
        $cleaned = preg_replace('/\/\*.*?\*\//s', ' ', $trimmed);
        $cleaned = preg_replace('/--.*$/m', ' ', $cleaned);
        $cleaned = preg_replace('/#.*$/m', ' ', $cleaned);
        // Normalizar espacios
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        // Solo permitir SELECT al inicio
        if (!preg_match('/^SELECT\s/i', $cleaned)) {
            Logger::log('SEGURIDAD: Query personalizada rechazada — no inicia con SELECT');
            return '';
        }

        // Prohibir palabras y funciones peligrosas
        $forbidden = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE',
            'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', 'UNION', 'INTO\s+OUTFILE',
            'INTO\s+DUMPFILE', 'LOAD_FILE', 'LOAD\s+DATA', 'BENCHMARK', 'SLEEP',
            'EXTRACTVALUE', 'UPDATEXML', 'EXP\s*\(', 'GET_LOCK', 'RELEASE_LOCK',
            'IS_FREE_LOCK', 'MASTER_POS_WAIT', 'PG_SLEEP', 'WAITFOR', 'HANDLER',
            'RENAME', 'REPLACE', 'SET\s', 'SHOW\s+GRANTS', 'FILE\s*\(',
        ];
        foreach ($forbidden as $word) {
            if (preg_match('/\b' . $word . '\b/i', $cleaned)) {
                Logger::log("SEGURIDAD: Query personalizada rechazada — contiene '{$word}'");
                return '';
            }
        }

        // Prohibir punto y coma (múltiples sentencias)
        if (str_contains($cleaned, ';')) {
            Logger::log('SEGURIDAD: Query personalizada rechazada — contiene punto y coma');
            return '';
        }

        // Prohibir subconsultas anidadas (máximo 1 nivel de paréntesis con SELECT)
        if (preg_match('/\(\s*SELECT\b/i', $cleaned)) {
            Logger::log('SEGURIDAD: Query personalizada rechazada — contiene subconsulta');
            return '';
        }

        // Prohibir acceso a tablas del sistema
        if (preg_match('/\b(information_schema|mysql|performance_schema|sys)\b/i', $cleaned)) {
            Logger::log('SEGURIDAD: Query personalizada rechazada — acceso a tablas del sistema');
            return '';
        }

        // ★ SEGURIDAD (C1): bloquear tablas sensibles de WordPress sin importar el
        // prefijo (wp_users, wp_usermeta, wp_options y variantes prefijadas). Estas
        // denegaciones se evalúan ANTES del check de whitelist por substring (que es
        // evadible) para evitar la lectura de hashes/credenciales/secretos.
        $forbidden_tables = [
            '/\b\w*users\b/i',
            '/\b\w*usermeta\b/i',
            '/\b\w*options\b/i',
        ];
        foreach ($forbidden_tables as $pattern) {
            if (preg_match($pattern, $cleaned)) {
                Logger::log('SEGURIDAD: Query personalizada rechazada — referencia a tabla sensible');
                return '';
            }
        }

        // Validar que la tabla referenciada esté en la whitelist
        $available_tables = $this->db->get_available_tables();
        $has_valid_table  = false;
        foreach (array_keys($available_tables) as $table) {
            if (stripos($cleaned, $table) !== false) {
                $has_valid_table = true;
                break;
            }
        }

        if (!$has_valid_table) {
            Logger::log('SEGURIDAD: Query personalizada rechazada — tabla no autorizada');
            return '';
        }

        return $cleaned;
    }

    // ── Assets ─────────────────────────────────────────────────
    public function enqueue_frontend_assets(): void
    {
        global $post;
        if (!is_a($post, 'WP_Post') ||
            (!has_shortcode($post->post_content, 'sdv_chart') &&
             !has_shortcode($post->post_content, 'secop_chart') &&
             !has_shortcode($post->post_content, 'secop_dep_chart') &&
             !has_shortcode($post->post_content, 'secop_seguimiento'))) {
            return;
        }

        $this->enqueue_chart_libraries();

        wp_enqueue_style('secop-suite-frontend', SECOP_SUITE_URL . 'assets/css/frontend.css', [], SECOP_SUITE_VERSION);
        wp_enqueue_script('secop-suite-frontend', SECOP_SUITE_URL . 'assets/js/frontend.js', ['jquery', 'd3plus'], SECOP_SUITE_VERSION, true);

        wp_localize_script('secop-suite-frontend', 'secopSuiteChart', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('secop-suite/v1/'),
            'nonce'   => wp_create_nonce('secop_suite_frontend'),
            'strings' => [
                'loading'  => __('Cargando datos...', 'secop-suite'),
                'error'    => __('Error al cargar los datos', 'secop-suite'),
                'noData'   => __('No hay datos disponibles', 'secop-suite'),
                'share'    => __('Compartir', 'secop-suite'),
                'data'     => __('Datos', 'secop-suite'),
                'image'    => __('Imagen', 'secop-suite'),
                'download' => __('Descarga', 'secop-suite'),
                'detail'   => __('Detalle', 'secop-suite'),
                'close'    => __('Cerrar', 'secop-suite'),
                'copied'   => __('¡Enlace copiado!', 'secop-suite'),
            ],
        ]);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        global $post_type;
        if ($post_type !== self::POST_TYPE) return;

        $this->enqueue_chart_libraries();

        wp_enqueue_style('secop-suite-admin-charts', SECOP_SUITE_URL . 'assets/css/admin.css', [], SECOP_SUITE_VERSION);
        wp_enqueue_script('secop-suite-admin-charts', SECOP_SUITE_URL . 'assets/js/admin-charts.js', ['jquery', 'd3plus', 'wp-color-picker'], SECOP_SUITE_VERSION, true);
        wp_enqueue_style('wp-color-picker');

        wp_localize_script('secop-suite-admin-charts', 'secopSuiteChartAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('secop_suite_chart_admin'),
            'strings' => [
                'selectTable'    => __('Seleccione una tabla primero', 'secop-suite'),
                'loadingColumns' => __('Cargando columnas...', 'secop-suite'),
                'previewError'   => __('Error al generar la vista previa', 'secop-suite'),
            ],
        ]);
    }

    public function enqueue_chart_libraries(): void
    {
        $vendor_url = SECOP_SUITE_URL . 'assets/js/vendor/';

        // Librerías servidas localmente para evitar dependencia de CDNs externos
        // y cumplir con políticas de privacidad (GDPR).
        // Si los archivos locales no existen, usar CDN como fallback.
        $libs = [
            'd3'          => ['file' => 'd3.v5.min.js',       'cdn' => 'https://d3js.org/d3.v5.min.js',                                              'deps' => [],     'ver' => '5.16.0'],
            'd3plus'      => ['file' => 'd3plus.full.min.js', 'cdn' => 'https://cdn.jsdelivr.net/npm/d3plus@2/build/d3plus.full.min.js',              'deps' => ['d3'], 'ver' => '2.1.3'],
            'topojson'    => ['file' => 'topojson.v2.min.js',  'cdn' => 'https://d3js.org/topojson.v2.min.js',                                        'deps' => ['d3'], 'ver' => '2.0.0'],
            'html2canvas' => ['file' => 'html2canvas.min.js',  'cdn' => 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js','deps' => [],     'ver' => '1.4.1'],
        ];

        foreach ($libs as $handle => $lib) {
            $local_path = SECOP_SUITE_DIR . 'assets/js/vendor/' . $lib['file'];
            $url = file_exists($local_path) ? $vendor_url . $lib['file'] : $lib['cdn'];
            wp_enqueue_script($handle, $url, $lib['deps'], $lib['ver'], true);
        }
    }

    /**
     * Enqueue the full frontend chart stack (libraries + frontend.js + CSS)
     * outside of the normal `wp_enqueue_scripts` flow — used by admin screens
     * (e.g. the Contratación catalog) that render chart previews via shortcode.
     */
    public function enqueue_frontend_chart_stack(): void
    {
        $this->enqueue_chart_libraries();
        wp_enqueue_style('secop-suite-frontend', SECOP_SUITE_URL . 'assets/css/frontend.css', [], SECOP_SUITE_VERSION);
        wp_enqueue_script('secop-suite-frontend', SECOP_SUITE_URL . 'assets/js/frontend.js', ['jquery', 'd3plus'], SECOP_SUITE_VERSION, true);
        if (!wp_script_is('secop-suite-frontend', 'done')) {
            wp_localize_script('secop-suite-frontend', 'secopSuiteChart', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => rest_url('secop-suite/v1/'),
                'nonce'   => wp_create_nonce('secop_suite_frontend'),
                'strings' => [
                    'loading'  => __('Cargando datos...', 'secop-suite'),
                    'error'    => __('Error al cargar los datos', 'secop-suite'),
                    'noData'   => __('No hay datos disponibles', 'secop-suite'),
                    'share'    => __('Compartir', 'secop-suite'),
                    'data'     => __('Datos', 'secop-suite'),
                    'image'    => __('Imagen', 'secop-suite'),
                    'download' => __('Descarga', 'secop-suite'),
                    'detail'   => __('Detalle', 'secop-suite'),
                    'close'    => __('Cerrar', 'secop-suite'),
                    'copied'   => __('¡Enlace copiado!', 'secop-suite'),
                ],
            ]);
        }
    }

    // ── Shortcode ──────────────────────────────────────────────
    public function render_chart_shortcode(array $atts): string
    {
        $atts = shortcode_atts(['id' => 0, 'height' => null, 'class' => ''], $atts, 'secop_chart');
        $chart_id = intval($atts['id']);

        if (!$chart_id) {
            return '<p class="ss-error">' . esc_html__('ID de gráfica no válido', 'secop-suite') . '</p>';
        }

        $post = get_post($chart_id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return '<p class="ss-error">' . esc_html__('Gráfica no encontrada', 'secop-suite') . '</p>';
        }

        $config = get_post_meta($chart_id, '_secop_chart_config', true) ?: [];
        if (empty($config['chart_type']) || empty($config['table_name'])) {
            return '<p class="ss-error">' . esc_html__('Gráfica no configurada correctamente', 'secop-suite') . '</p>';
        }

        if ($atts['height']) {
            $config['chart_height'] = intval($atts['height']);
        }

        $unique_id   = 'ss-chart-' . $chart_id . '-' . wp_unique_id();
        $extra_class = !empty($atts['class']) ? ' ' . esc_attr($atts['class']) : '';

        // ROBUSTEZ: encolar el motor al renderizar (cubre page builders/bloques/plantillas
        // donde has_shortcode($post->post_content) no detecta el shortcode).
        $this->enqueue_frontend_chart_stack();

        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/chart.php';
        return ob_get_clean();
    }

    // ── Shortcode: Export ──────────────────────────────────────
    public function render_export_shortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'title' => __('Datos Abiertos SECOP', 'secop-suite'),
            'class' => '',
        ], $atts, 'secop_export');

        $total = $this->db->get_total_records();
        $rest_url = rest_url('secop-suite/v1/');
        $extra_class = !empty($atts['class']) ? ' ' . esc_attr($atts['class']) : '';

        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/export.php';
        return ob_get_clean();
    }

    // ── AJAX ───────────────────────────────────────────────────
    public function ajax_get_chart_data(): void
    {
        $chart_id = intval($_POST['chart_id'] ?? 0);

        // Nonce específico por chart para mayor seguridad
        $nonce_action = 'secop_suite_chart_' . $chart_id;
        if (!wp_verify_nonce(sanitize_text_field($_POST['nonce'] ?? ''), $nonce_action)) {
            // Fallback al nonce global por compatibilidad
            check_ajax_referer('secop_suite_frontend', 'nonce');
        }

        if (!$chart_id) {
            wp_send_json_error(['message' => 'ID inválido']);
        }

        // Rate limiting básico por IP (máx. 60 requests/minuto)
        $ip_key = 'secop_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        $requests = (int) get_transient($ip_key);
        if ($requests > 60) {
            wp_send_json_error(['message' => 'Demasiadas solicitudes. Intente más tarde.'], 429);
        }
        set_transient($ip_key, $requests + 1, MINUTE_IN_SECONDS);

        $config = get_post_meta($chart_id, '_secop_chart_config', true);
        if (!$config) {
            wp_send_json_error(['message' => 'Configuración no encontrada']);
        }

        // Optional dependencia filter — only applies when the chart targets the VIEW.
        // Reemplaza cualquier filtro previo de nombredependencia (evita acumular dos y dar 0 filas).
        $dependencia = sanitize_text_field($_POST['dependencia'] ?? '');
        if ($dependencia !== '' && ($config['table_name'] ?? '') === $this->db->get_view_name()) {
            $config['filters'] = array_values(array_filter(
                $config['filters'] ?? [],
                static fn($f) => ($f['field'] ?? '') !== 'nombredependencia'
            ));
            $config['filters'][] = ['field' => 'nombredependencia', 'operator' => '=', 'value' => $dependencia];
        }

        $data = $this->get_chart_data($config);

        // ★ SEGURIDAD (M5): no divulgar claves internas (SQL crudo) al cliente público.
        unset($config['custom_query']);

        wp_send_json_success([
            'data'   => $data,
            'config' => $config,
        ]);
    }

    public function ajax_get_table_columns(): void
    {
        // Accept nonce from both chart admin and filter admin contexts
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'secop_suite_chart_admin') &&
            !wp_verify_nonce($nonce, 'secop_suite_filter_admin')) {
            wp_send_json_error(['message' => 'Nonce inválido']);
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }

        $table = sanitize_text_field($_POST['table'] ?? '');

        // ★ SEGURIDAD: Validar tabla contra whitelist
        $tables = $this->db->get_available_tables();
        if (!isset($tables[$table])) {
            wp_send_json_error(['message' => 'Tabla no válida']);
        }

        $columns = $this->db->get_table_columns($table);
        $formatted = [];
        foreach ($columns as $name => $type) {
            $formatted[] = [
                'name'     => $name,
                'type'     => $type,
                'nullable' => true,
            ];
        }

        wp_send_json_success(['columns' => $formatted]);
    }

    public function ajax_preview_data(): void
    {
        check_ajax_referer('secop_suite_chart_admin', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }

        $config = [
            'chart_type'      => sanitize_text_field($_POST['chart_type'] ?? 'bar'),
            'table_name'      => sanitize_text_field($_POST['table_name'] ?? ''),
            'x_field'         => sanitize_text_field($_POST['x_field'] ?? ''),
            'x_date_grouping' => sanitize_text_field($_POST['x_date_grouping'] ?? ''),
            'y_field'         => sanitize_text_field($_POST['y_field'] ?? ''),
            'y_fields'        => $this->sanitize_y_fields($_POST['y_fields'] ?? []),
            'group_by'        => sanitize_text_field($_POST['group_by'] ?? ''),
            'aggregate'       => sanitize_text_field($_POST['aggregate'] ?? 'SUM'),
            'color_field'     => sanitize_text_field($_POST['color_field'] ?? ''),
            'date_field'      => sanitize_text_field($_POST['date_field'] ?? ''),
            'date_from'       => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to'         => sanitize_text_field($_POST['date_to'] ?? ''),
            'limit'           => intval($_POST['limit'] ?? 100),
            'filters'         => $this->sanitize_filters($_POST['filters'] ?? []),
        ];

        $data = $this->get_chart_data($config);
        wp_send_json_success([
            'data'  => $data,
            'count' => count($data),
        ]);
    }

    // ── Generación de datos para la gráfica ────────────────────
    public function get_chart_data(array $config): array
    {
        global $wpdb;

        // Cache de resultados (invalidado tras importación)
        $cache_key = 'secop_chart_' . md5(wp_json_encode($config));
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $result = $this->build_chart_query($config);

        // Cachear por 15 minutos
        set_transient($cache_key, $result, 15 * MINUTE_IN_SECONDS);
        return $result;
    }

    /**
     * Ejecuta la query de la gráfica SIN caché y devuelve los datos junto con
     * el SQL realmente ejecutado (vía $wpdb->last_query). Reutiliza exactamente
     * el mismo constructor de query (build_chart_query), de modo que el SQL
     * mostrado es el real. Pensado para la vista previa en vivo del editor.
     *
     * @param array $config Configuración de gráfica (formato _secop_chart_config).
     * @return array{data: array, sql: string}
     */
    public function get_chart_data_with_sql(array $config): array
    {
        global $wpdb;
        $data = $this->build_chart_query($config); // private, misma clase — OK llamarla
        return ['data' => $data, 'sql' => $wpdb->last_query];
    }

    /**
     * Construir y ejecutar la query para datos de gráfica.
     */
    private function build_chart_query(array $config): array
    {
        global $wpdb;

        // Query personalizada (validada)
        if (!empty($config['custom_query'])) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results($config['custom_query'], ARRAY_A);
            return $results ?: [];
        }

        $table = $config['table_name'] ?? '';

        // ★ SEGURIDAD: Validar tabla
        $tables = $this->db->get_available_tables();
        if (!isset($tables[$table])) {
            return [];
        }

        $x_field         = $config['x_field'] ?? '';
        $y_field         = $config['y_field'] ?? '';
        $y_fields        = $config['y_fields'] ?? [];
        $group_by        = $config['group_by'] ?? '';
        $aggregate       = strtoupper($config['aggregate'] ?? 'SUM');
        $x_date_grouping = $config['x_date_grouping'] ?? '';

        // ★ SEGURIDAD: Validar campos contra columnas reales
        $valid_columns = $this->db->get_table_columns($table);
        if ($x_field && !isset($valid_columns[$x_field])) return [];
        if ($y_field && !isset($valid_columns[$y_field])) return [];
        if ($group_by && !isset($valid_columns[$group_by])) return [];

        // ★ SEGURIDAD: Validar función de agregación
        if (!in_array($aggregate, self::ALLOWED_AGGREGATES, true)) {
            $aggregate = 'SUM';
        }

        // ── Multi-Y fields mode ───────────────────────────────────
        // When y_fields has entries, run one query per Y column and merge
        // results into [{x_value, y_value, group_value (= label)}]
        $valid_y_fields = [];
        foreach ($y_fields as $yf) {
            if (!empty($yf['column']) && isset($valid_columns[$yf['column']])) {
                $valid_y_fields[] = $yf;
            }
        }

        if (!empty($valid_y_fields) && $x_field) {
            return $this->build_multi_y_query($config, $table, $x_field, $x_date_grouping, $valid_y_fields, $aggregate, $valid_columns);
        }

        // Construir expresión X con agrupación de fecha
        $x_expression = $this->build_date_expression($x_field, $x_date_grouping);

        // SELECT
        $select_parts = [];
        if ($x_field) {
            $select_parts[] = "{$x_expression} AS x_value";
        }
        if ($group_by && $group_by !== $x_field) {
            $select_parts[] = "`{$group_by}` AS group_value";
        }
        if ($y_field) {
            // Regla contable (Contratación): el VALOR EFECTIVO del contrato es
            // valordebito y, solo cuando es NULL, valor_contrato. card_to_chart_config
            // activa 'value_efectivo' para la métrica de valor; el resto de gráficas
            // ([secop_chart], conteos, etc.) no se ven afectadas.
            if (!empty($config['value_efectivo']) && $aggregate === 'SUM'
                && isset($valid_columns['valordebito'], $valid_columns['valor_contrato'])) {
                $select_parts[] = 'SUM(COALESCE(`valordebito`, `valor_contrato`)) AS y_value';
            } else {
                // COUNT_DISTINCT no es una función SQL real: se emite como COUNT(DISTINCT col).
                $select_parts[] = ($aggregate === 'COUNT_DISTINCT')
                    ? "COUNT(DISTINCT `{$y_field}`) AS y_value"
                    : "{$aggregate}(`{$y_field}`) AS y_value";
            }
            // v5.3.2: columna secundaria con el conteo de contratos de cada categoría,
            // para el tooltip configurable. Solo se añade cuando la card lo solicita
            // (tooltip_count) y la columna existe → [secop_chart] no se ve afectado.
            if (!empty($config['tooltip_count']) && isset($valid_columns['numero_del_contrato'])) {
                $select_parts[] = "COUNT(DISTINCT `numero_del_contrato`) AS y_count";
            }
        }

        if (empty($select_parts)) return [];
        $select = implode(', ', $select_parts);

        // WHERE
        $where_clauses = ['1=1'];
        $where_values  = [];

        if (!empty($config['date_field']) && isset($valid_columns[$config['date_field']])) {
            if (!empty($config['date_from'])) {
                $where_clauses[] = "`{$config['date_field']}` >= %s";
                $where_values[]  = $config['date_from'] . ' 00:00:00';
            }
            if (!empty($config['date_to'])) {
                $where_clauses[] = "`{$config['date_field']}` <= %s";
                $where_values[]  = $config['date_to'] . ' 23:59:59';
            }
        }

        // Filtros personalizados
        if (!empty($config['filters'])) {
            foreach ($config['filters'] as $filter) {
                if (empty($filter['field']) || !isset($filter['value'])) continue;

                // ★ SEGURIDAD: Validar columna del filtro
                if (!isset($valid_columns[$filter['field']])) continue;

                $operator = in_array($filter['operator'], self::ALLOWED_OPERATORS, true)
                    ? $filter['operator']
                    : '=';

                if ($operator === 'LIKE') {
                    $where_clauses[] = "`{$filter['field']}` LIKE %s";
                    $where_values[]  = '%' . $wpdb->esc_like($filter['value']) . '%';
                } else {
                    $where_clauses[] = "`{$filter['field']}` {$operator} %s";
                    $where_values[]  = $filter['value'];
                }
            }
        }

        $where = implode(' AND ', $where_clauses);

        // GROUP BY
        $group_parts = [];
        if ($x_field) $group_parts[] = $x_expression;
        if ($group_by && $group_by !== $x_field) $group_parts[] = "`{$group_by}`";
        $group_sql = !empty($group_parts) ? 'GROUP BY ' . implode(', ', $group_parts) : '';

        // ORDER BY
        $order_sql = '';
        if (($config['order_by'] ?? '') === '__value__') {
            // Sentinela: ordenar por la métrica agregada (alias y_value).
            $dir = ($config['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $order_sql = "ORDER BY y_value {$dir}";
        } elseif (!empty($config['order_by']) && isset($valid_columns[$config['order_by']])) {
            $dir = ($config['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $order_field = ($config['order_by'] === $x_field && $x_date_grouping)
                ? $x_expression
                : "`{$config['order_by']}`";
            $order_sql = "ORDER BY {$order_field} {$dir}";
        } elseif ($x_date_grouping) {
            $order_sql = "ORDER BY {$x_expression} ASC";
        }

        // LIMIT
        $limit_sql = '';
        if (!empty($config['limit']) && $config['limit'] > 0) {
            $limit_sql = 'LIMIT ' . intval($config['limit']);
        }

        // Build & execute
        $sql = "SELECT {$select} FROM `{$table}` WHERE {$where} {$group_sql} {$order_sql} {$limit_sql}";

        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare($sql, ...$where_values);
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        // Post-procesar nombres de meses
        if ($x_date_grouping === 'month_name' && $results) {
            $month_names = [
                '01' => 'Enero',    '02' => 'Febrero',   '03' => 'Marzo',
                '04' => 'Abril',    '05' => 'Mayo',       '06' => 'Junio',
                '07' => 'Julio',    '08' => 'Agosto',     '09' => 'Septiembre',
                '10' => 'Octubre',  '11' => 'Noviembre',  '12' => 'Diciembre',
            ];
            foreach ($results as &$row) {
                if (isset($month_names[$row['x_value']])) {
                    $row['x_value'] = $month_names[$row['x_value']];
                }
            }
        }

        return $results ?: [];
    }

    private function build_date_expression(string $field, string $grouping): string
    {
        return match ($grouping) {
            'full'       => "DATE_FORMAT(`{$field}`, '%Y-%m-%d')",
            'year'       => "YEAR(`{$field}`)",
            'month'      => "DATE_FORMAT(`{$field}`, '%Y-%m')",
            'month_name' => "DATE_FORMAT(`{$field}`, '%m')",
            'quarter'    => "CONCAT(YEAR(`{$field}`), '-Q', QUARTER(`{$field}`))",
            'week'       => "CONCAT(YEAR(`{$field}`), '-W', LPAD(WEEK(`{$field}`), 2, '0'))",
            default      => "`{$field}`",
        };
    }

    /**
     * Build query for multi-Y fields mode.
     * Each Y column becomes a separate series identified by its label.
     * Returns data in format: [{x_value, y_value, group_value}]
     */
    private function build_multi_y_query(array $config, string $table, string $x_field, string $x_date_grouping, array $y_fields, string $aggregate, array $valid_columns): array
    {
        global $wpdb;

        $x_expression = $this->build_date_expression($x_field, $x_date_grouping);

        // Build WHERE clause (shared across all Y fields)
        $where_clauses = ['1=1'];
        $where_values  = [];

        if (!empty($config['date_field']) && isset($valid_columns[$config['date_field']])) {
            if (!empty($config['date_from'])) {
                $where_clauses[] = "`{$config['date_field']}` >= %s";
                $where_values[]  = $config['date_from'] . ' 00:00:00';
            }
            if (!empty($config['date_to'])) {
                $where_clauses[] = "`{$config['date_field']}` <= %s";
                $where_values[]  = $config['date_to'] . ' 23:59:59';
            }
        }

        if (!empty($config['filters'])) {
            foreach ($config['filters'] as $filter) {
                if (empty($filter['field']) || !isset($filter['value'])) continue;
                if (!isset($valid_columns[$filter['field']])) continue;

                $operator = in_array($filter['operator'], self::ALLOWED_OPERATORS, true)
                    ? $filter['operator']
                    : '=';

                if ($operator === 'LIKE') {
                    $where_clauses[] = "`{$filter['field']}` LIKE %s";
                    $where_values[]  = '%' . $wpdb->esc_like($filter['value']) . '%';
                } else {
                    $where_clauses[] = "`{$filter['field']}` {$operator} %s";
                    $where_values[]  = $filter['value'];
                }
            }
        }

        $where_sql = implode(' AND ', $where_clauses);

        // ORDER
        $order_sql = '';
        if ($x_date_grouping) {
            $order_sql = "ORDER BY {$x_expression} ASC";
        }

        // LIMIT
        $limit_sql = '';
        if (!empty($config['limit']) && $config['limit'] > 0) {
            $limit_sql = 'LIMIT ' . intval($config['limit']);
        }

        // Run one query per Y field and merge results
        $all_results = [];

        foreach ($y_fields as $yf) {
            $column = $yf['column'];
            $label  = $yf['label'] ?: ucfirst(str_replace('_', ' ', $column));

            $sql = "SELECT {$x_expression} AS x_value, {$aggregate}(`{$column}`) AS y_value FROM `{$table}` WHERE {$where_sql} GROUP BY {$x_expression} {$order_sql} {$limit_sql}";

            if (!empty($where_values)) {
                $rows = $wpdb->get_results($wpdb->prepare($sql, ...$where_values), ARRAY_A);
            } else {
                $rows = $wpdb->get_results($sql, ARRAY_A);
            }

            if ($rows) {
                foreach ($rows as $row) {
                    $all_results[] = [
                        'x_value'     => $row['x_value'],
                        'y_value'     => $row['y_value'],
                        'group_value' => $label,
                    ];
                }
            }
        }

        // Post-process month names
        if ($x_date_grouping === 'month_name' && $all_results) {
            $month_names = [
                '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
                '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
                '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
                '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
            ];
            foreach ($all_results as &$row) {
                if (isset($month_names[$row['x_value']])) {
                    $row['x_value'] = $month_names[$row['x_value']];
                }
            }
        }

        return $all_results;
    }

    // ── Admin columns ──────────────────────────────────────────
    public function add_admin_columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $k => $v) {
            $new[$k] = $v;
            if ($k === 'title') {
                $new['chart_type'] = __('Tipo', 'secop-suite');
                $new['shortcode']  = __('Shortcode', 'secop-suite');
            }
        }
        return $new;
    }

    public function render_admin_columns(string $column, int $post_id): void
    {
        $config = get_post_meta($post_id, '_secop_chart_config', true);
        $types  = [
            'bar' => 'Barras', 'line' => 'Líneas', 'pie' => 'Pie', 'treemap' => 'Treemap',
            'tree' => 'Árbol', 'pack' => 'Burbujas', 'network' => 'Red',
            'donut' => 'Donut', 'area' => 'Área', 'stacked_bar' => 'Apiladas', 'grouped_bar' => 'Agrupadas',
        ];

        match ($column) {
            'chart_type' => print esc_html($types[$config['chart_type'] ?? 'bar'] ?? '-'),
            'shortcode'  => print '<code>[secop_chart id="' . $post_id . '"]</code>',
            default      => null,
        };
    }
}
