<?php
/**
 * Filter — CPT de filtros de búsqueda, shortcodes y generación de resultados.
 *
 * Permite crear filtros configurables (input, select, rango, checkbox)
 * que se insertan via shortcode y muestran resultados en lista con
 * enlace al proceso (url_contrato).
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Filter
{
    private Database $db;
    private const POST_TYPE = 'secop_filter';

    /** Tipos de campo de filtro permitidos */
    private const ALLOWED_FIELD_TYPES = ['input', 'select', 'range', 'checkbox'];

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
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_filter_meta'], 10, 2);

        // Shortcodes
        add_shortcode('secop_filter', [$this, 'render_filter_shortcode']);

        // AJAX
        add_action('wp_ajax_secop_suite_filter_search', [$this, 'ajax_filter_search']);
        add_action('wp_ajax_nopriv_secop_suite_filter_search', [$this, 'ajax_filter_search']);
        add_action('wp_ajax_secop_suite_filter_options', [$this, 'ajax_get_filter_options']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Admin columns
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
    }

    // ── CPT ────────────────────────────────────────────────────
    public function register_post_type(): void
    {
        $labels = [
            'name'               => __('Filtros SECOP', 'secop-suite'),
            'singular_name'      => __('Filtro', 'secop-suite'),
            'menu_name'          => __('Filtros', 'secop-suite'),
            'add_new'            => __('Nuevo Filtro', 'secop-suite'),
            'add_new_item'       => __('Añadir Nuevo Filtro', 'secop-suite'),
            'edit_item'          => __('Editar Filtro', 'secop-suite'),
            'new_item'           => __('Nuevo Filtro', 'secop-suite'),
            'view_item'          => __('Ver Filtro', 'secop-suite'),
            'search_items'       => __('Buscar Filtros', 'secop-suite'),
            'not_found'          => __('No se encontraron filtros', 'secop-suite'),
            'not_found_in_trash' => __('No hay filtros en la papelera', 'secop-suite'),
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
            'menu_icon'           => 'dashicons-filter',
            'supports'            => ['title'],
            'show_in_rest'        => true,
        ]);
    }

    // ── Meta Boxes ─────────────────────────────────────────────
    public function add_meta_boxes(): void
    {
        add_meta_box(
            'secop_filter_config',
            __('Configuración del Filtro', 'secop-suite'),
            [$this, 'render_config_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'secop_filter_shortcode',
            __('Shortcode', 'secop-suite'),
            [$this, 'render_shortcode_metabox'],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    public function render_config_metabox(\WP_Post $post): void
    {
        wp_nonce_field('secop_suite_filter_config', 'secop_suite_filter_nonce');
        $config = get_post_meta($post->ID, '_secop_filter_config', true) ?: [];
        $tables = $this->db->get_available_tables();
        include SECOP_SUITE_DIR . 'templates/admin/filter-config.php';
    }

    public function render_shortcode_metabox(\WP_Post $post): void
    {
        ?>
        <div class="ss-shortcode-box">
            <p><?php _e('Copia este shortcode para insertar el filtro:', 'secop-suite'); ?></p>
            <code id="ss-filter-shortcode-display">[secop_filter id="<?php echo esc_attr($post->ID); ?>"]</code>
            <button type="button" class="button ss-copy-shortcode" data-clipboard-target="#ss-filter-shortcode-display">
                <span class="dashicons dashicons-clipboard"></span> <?php _e('Copiar', 'secop-suite'); ?>
            </button>
        </div>
        <?php
    }

    // ── Guardar meta ───────────────────────────────────────────
    public function save_filter_meta(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['secop_suite_filter_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['secop_suite_filter_nonce'] ?? '')), 'secop_suite_filter_config')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $config = [
            'table_name'      => sanitize_text_field($_POST['ss_filter_table_name'] ?? ''),
            'fields'          => $this->sanitize_filter_fields($_POST['ss_filter_fields'] ?? []),
            'result_columns'  => array_map('sanitize_text_field', $_POST['ss_result_columns'] ?? []),
            'results_per_page'=> intval($_POST['ss_results_per_page'] ?? 20),
            'order_by'        => sanitize_text_field($_POST['ss_filter_order_by'] ?? 'fecha_de_firma_del_contrato'),
            'order_dir'       => in_array($_POST['ss_filter_order_dir'] ?? 'DESC', ['ASC', 'DESC'], true) ? $_POST['ss_filter_order_dir'] : 'DESC',
            'show_url_link'   => isset($_POST['ss_show_url_link']),
            'url_field'       => sanitize_text_field($_POST['ss_url_field'] ?? 'url_contrato'),
        ];

        update_post_meta($post_id, '_secop_filter_config', $config);
    }

    private function sanitize_filter_fields(array $fields): array
    {
        $sanitized = [];
        foreach ($fields as $field) {
            if (!empty($field['column'])) {
                $sanitized[] = [
                    'column'      => sanitize_text_field($field['column']),
                    'label'       => sanitize_text_field($field['label'] ?? ''),
                    'type'        => in_array($field['type'] ?? 'input', self::ALLOWED_FIELD_TYPES, true)
                        ? $field['type']
                        : 'input',
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'operator'    => in_array($field['operator'] ?? '=', self::ALLOWED_OPERATORS, true)
                        ? $field['operator']
                        : '=',
                ];
            }
        }
        return $sanitized;
    }

    // ── Assets ─────────────────────────────────────────────────
    public function enqueue_frontend_assets(): void
    {
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'secop_filter')) {
            return;
        }

        wp_enqueue_style(
            'secop-suite-filters',
            SECOP_SUITE_URL . 'assets/css/frontend.css',
            [],
            SECOP_SUITE_VERSION
        );

        wp_enqueue_script(
            'secop-suite-filters',
            SECOP_SUITE_URL . 'assets/js/frontend-filters.js',
            ['jquery'],
            SECOP_SUITE_VERSION,
            true
        );

        wp_localize_script('secop-suite-filters', 'secopSuiteFilter', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('secop_suite_filter'),
            'strings' => [
                'loading'   => __('Buscando...', 'secop-suite'),
                'noResults' => __('No se encontraron resultados', 'secop-suite'),
                'error'     => __('Error al realizar la búsqueda', 'secop-suite'),
                'viewProcess' => __('Ver proceso', 'secop-suite'),
                'showing'   => __('Mostrando', 'secop-suite'),
                'of'        => __('de', 'secop-suite'),
                'results'   => __('resultados', 'secop-suite'),
                'prev'      => __('Anterior', 'secop-suite'),
                'next'      => __('Siguiente', 'secop-suite'),
                'search'    => __('Buscar', 'secop-suite'),
                'clear'     => __('Limpiar', 'secop-suite'),
            ],
        ]);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        global $post_type;
        if ($post_type !== self::POST_TYPE) return;

        wp_enqueue_style('secop-suite-admin-filters', SECOP_SUITE_URL . 'assets/css/admin.css', [], SECOP_SUITE_VERSION);
        wp_enqueue_script(
            'secop-suite-admin-filters',
            SECOP_SUITE_URL . 'assets/js/admin-filters.js',
            ['jquery'],
            SECOP_SUITE_VERSION,
            true
        );

        wp_localize_script('secop-suite-admin-filters', 'secopSuiteFilterAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('secop_suite_filter_admin'),
            'strings' => [
                'selectTable'    => __('Seleccione una tabla primero', 'secop-suite'),
                'loadingColumns' => __('Cargando columnas...', 'secop-suite'),
            ],
        ]);
    }

    // ── Shortcode ──────────────────────────────────────────────
    public function render_filter_shortcode(array $atts): string
    {
        $atts = shortcode_atts(['id' => 0, 'class' => ''], $atts, 'secop_filter');
        $filter_id = intval($atts['id']);

        if (!$filter_id) {
            return '<p class="ss-error">' . esc_html__('ID de filtro no válido', 'secop-suite') . '</p>';
        }

        $post = get_post($filter_id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return '<p class="ss-error">' . esc_html__('Filtro no encontrado', 'secop-suite') . '</p>';
        }

        $config = get_post_meta($filter_id, '_secop_filter_config', true) ?: [];
        if (empty($config['table_name']) || empty($config['fields'])) {
            return '<p class="ss-error">' . esc_html__('Filtro no configurado correctamente', 'secop-suite') . '</p>';
        }

        $unique_id   = 'ss-filter-' . $filter_id . '-' . wp_unique_id();
        $extra_class = !empty($atts['class']) ? ' ' . esc_attr($atts['class']) : '';

        // Populate DISTINCT options for select/checkbox fields server-side so public
        // visitors get working selects/checkboxes without needing privileged AJAX.
        $table            = $config['table_name'];
        $available_tables = $this->db->get_available_tables();
        if (isset($available_tables[$table])) {
            $valid_columns  = $this->db->get_table_columns($table);
            global $wpdb;
            $enriched_fields = [];
            foreach ($config['fields'] as $field) {
                $field['options'] = [];
                if (in_array($field['type'], ['select', 'checkbox'], true) && !empty($field['column'])) {
                    $col = $field['column'];
                    if (isset($valid_columns[$col])) {
                        // Column and table are validated above; backtick-quoted identifiers.
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $field['options'] = (array) $wpdb->get_col(
                            "SELECT DISTINCT `{$col}` FROM `{$table}` WHERE `{$col}` IS NOT NULL AND `{$col}` <> '' ORDER BY `{$col}` LIMIT 200"
                        );
                    }
                }
                $enriched_fields[] = $field;
            }
            $config['fields'] = $enriched_fields;
        }

        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/filter.php';
        return ob_get_clean();
    }

    // ── AJAX: Búsqueda ─────────────────────────────────────────
    public function ajax_filter_search(): void
    {
        check_ajax_referer('secop_suite_filter', 'nonce');

        $filter_id = intval($_POST['filter_id'] ?? 0);
        if (!$filter_id) {
            wp_send_json_error(['message' => 'ID inválido']);
        }

        // Rate limiting
        $ip_key = 'secop_frl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        $requests = (int) get_transient($ip_key);
        if ($requests > 60) {
            wp_send_json_error(['message' => 'Demasiadas solicitudes. Intente más tarde.'], 429);
        }
        set_transient($ip_key, $requests + 1, MINUTE_IN_SECONDS);

        $config = get_post_meta($filter_id, '_secop_filter_config', true);
        if (!$config) {
            wp_send_json_error(['message' => 'Configuración no encontrada']);
        }

        $table = $config['table_name'];

        // Validar tabla
        $tables = $this->db->get_available_tables();
        if (!isset($tables[$table])) {
            wp_send_json_error(['message' => 'Tabla no válida']);
        }

        $valid_columns = $this->db->get_table_columns($table);
        $filter_values = $_POST['filters'] ?? [];
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = max(1, min(100, intval($config['results_per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;

        global $wpdb;

        // Build WHERE clauses
        $where_clauses = ['1=1'];
        $where_values  = [];

        foreach ($config['fields'] as $field_config) {
            $column = $field_config['column'];
            if (!isset($valid_columns[$column])) continue;

            $value = $filter_values[$column] ?? '';
            if ($value === '' || $value === null) continue;

            $operator = in_array($field_config['operator'], self::ALLOWED_OPERATORS, true)
                ? $field_config['operator']
                : '=';

            if ($field_config['type'] === 'range') {
                // Range: expect value_from and value_to
                $from = $filter_values[$column . '_from'] ?? '';
                $to = $filter_values[$column . '_to'] ?? '';
                if ($from !== '') {
                    $where_clauses[] = "`{$column}` >= %s";
                    $where_values[] = $from;
                }
                if ($to !== '') {
                    $where_clauses[] = "`{$column}` <= %s";
                    $where_values[] = $to;
                }
                continue;
            }

            if ($field_config['type'] === 'checkbox') {
                // Checkbox: multiple values
                if (is_array($value)) {
                    $placeholders = implode(', ', array_fill(0, count($value), '%s'));
                    $where_clauses[] = "`{$column}` IN ({$placeholders})";
                    foreach ($value as $v) {
                        $where_values[] = sanitize_text_field($v);
                    }
                }
                continue;
            }

            if ($operator === 'LIKE') {
                $where_clauses[] = "`{$column}` LIKE %s";
                $where_values[] = '%' . $wpdb->esc_like(sanitize_text_field($value)) . '%';
            } else {
                $where_clauses[] = "`{$column}` {$operator} %s";
                $where_values[] = sanitize_text_field($value);
            }
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Result columns
        $select_columns = ['*'];
        if (!empty($config['result_columns'])) {
            $valid_result_cols = array_filter($config['result_columns'], function ($col) use ($valid_columns) {
                return isset($valid_columns[$col]);
            });
            if (!empty($valid_result_cols)) {
                // Always include id and urlproceso if show_url_link
                $select_columns = array_unique(array_merge(['id'], $valid_result_cols));
                if (!empty($config['show_url_link']) && !empty($config['url_field']) && isset($valid_columns[$config['url_field']])) {
                    $select_columns[] = $config['url_field'];
                    $select_columns = array_unique($select_columns);
                }
            }
        }
        $select_sql = implode(', ', array_map(fn($c) => "`{$c}`", $select_columns));

        // Order
        $order_by = $config['order_by'] ?? 'fecha_de_firma_del_contrato';
        if (!isset($valid_columns[$order_by])) {
            $order_by = 'id';
        }
        $order_dir = ($config['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        // Count total
        $count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
        if (!empty($where_values)) {
            $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$where_values));
        } else {
            $total = (int) $wpdb->get_var($count_sql);
        }

        // Fetch results
        $all_values = array_merge($where_values, [$per_page, $offset]);
        $sql = "SELECT {$select_sql} FROM `{$table}` WHERE {$where_sql} ORDER BY `{$order_by}` {$order_dir} LIMIT %d OFFSET %d";
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$all_values), ARRAY_A);

        wp_send_json_success([
            'data'       => $results ?: [],
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages'=> max(1, (int) ceil($total / $per_page)),
            'url_field'  => (!empty($config['show_url_link']) && !empty($config['url_field'])) ? $config['url_field'] : null,
        ]);
    }

    // ── AJAX: Opciones para select/checkbox ────────────────────
    public function ajax_get_filter_options(): void
    {
        check_ajax_referer('secop_suite_filter_admin', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permisos insuficientes']);
        }

        $table = sanitize_text_field($_POST['table'] ?? '');
        $column = sanitize_text_field($_POST['column'] ?? '');

        $tables = $this->db->get_available_tables();
        if (!isset($tables[$table])) {
            wp_send_json_error(['message' => 'Tabla no válida']);
        }

        $valid_columns = $this->db->get_table_columns($table);
        if (!isset($valid_columns[$column])) {
            wp_send_json_error(['message' => 'Columna no válida']);
        }

        global $wpdb;
        $options = $wpdb->get_col("SELECT DISTINCT `{$column}` FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` != '' ORDER BY `{$column}` LIMIT 200");

        wp_send_json_success(['options' => $options]);
    }

    // ── Admin columns ──────────────────────────────────────────
    public function add_admin_columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $k => $v) {
            $new[$k] = $v;
            if ($k === 'title') {
                $new['filter_fields'] = __('Campos', 'secop-suite');
                $new['shortcode']     = __('Shortcode', 'secop-suite');
            }
        }
        return $new;
    }

    public function render_admin_columns(string $column, int $post_id): void
    {
        $config = get_post_meta($post_id, '_secop_filter_config', true);

        match ($column) {
            'filter_fields' => print esc_html(count($config['fields'] ?? []) . ' ' . __('campos', 'secop-suite')),
            'shortcode'     => print '<code>[secop_filter id="' . $post_id . '"]</code>',
            default         => null,
        };
    }
}
