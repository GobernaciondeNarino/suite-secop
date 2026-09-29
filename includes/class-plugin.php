<?php
/**
 * Plugin — Clase principal de SECOP Suite.
 *
 * Vive fuera de secop-suite.php a propósito: PHP declara las clases de un
 * archivo al compilarlo, ANTES de ejecutar su código. Si la clase estuviera en
 * el archivo principal, una segunda copia del plugin (otra carpeta) la
 * declararía aunque su guarda decidiera no cargar, y la copia antigua que se
 * cargara después fallaría con «Cannot declare class SecopSuite\Plugin».
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    /** Slug de la página «Configuración» (pestañas Importar · Registros · Depuración · Logs). */
    public const CONFIG_PAGE = 'secop-suite-config';

    /**
     * Páginas sueltas antiguas (antes de agruparlas en pestañas) → pestaña equivalente de Configuración.
     * Se conservan para redirigir marcadores y enlaces antiguos.
     */
    private const LEGACY_PAGES = [
        'secop-suite-import'     => 'importar',
        'secop-suite-records'    => 'registros',
        'secop-suite-depuracion' => 'depuracion',
        'secop-suite-logs'       => 'logs',
    ];

    private static ?Plugin $instance = null;
    private Database $database;
    private Importer $importer;
    private Visualizer $visualizer;
    private Filter $filter;
    private Rest_Api $rest_api;
    private Updater $updater;
    private Tracking $tracking;
    private Open_Data $open_data;
    private Deduplicator $deduplicator;
    private Config_Backup $config_backup;

    private function __construct()
    {
        $this->database   = new Database();
        $this->importer   = new Importer($this->database);
        $this->visualizer = new Visualizer($this->database);
        $this->filter     = new Filter($this->database);
        $this->rest_api   = new Rest_Api($this->database);
        $this->updater    = new Updater();
        $this->tracking   = new Tracking($this->database);
        $this->open_data  = new Open_Data($this->database);
        $this->deduplicator = new Deduplicator($this->database);
        $this->config_backup = new Config_Backup($this->database);

        $this->register_hooks();
    }

    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    // ── Getters públicos ───────────────────────────────────────
    public function database(): Database     { return $this->database; }
    public function importer(): Importer     { return $this->importer; }
    public function visualizer(): Visualizer { return $this->visualizer; }
    public function filter(): Filter         { return $this->filter; }
    public function tracking(): Tracking     { return $this->tracking; }
    public function open_data(): Open_Data   { return $this->open_data; }
    public function config_backup(): Config_Backup { return $this->config_backup; }

    // ── Hooks ──────────────────────────────────────────────────
    private function register_hooks(): void
    {
        register_activation_hook(SECOP_SUITE_FILE, [$this, 'activate']);
        register_deactivation_hook(SECOP_SUITE_FILE, [$this, 'deactivate']);

        add_action('init', [$this, 'load_textdomain']);
        // Prioridad 9: antes de _add_post_type_submenus (10). Si los CPT con
        // show_in_menu='secop-suite' se añaden primero, WordPress ya no crea la
        // entrada del panel en el submenú y el panel queda inaccesible desde el menú.
        add_action('admin_menu', [$this, 'register_admin_menu'], 9);
        add_action('admin_menu', [$this, 'sort_submenus'], 9999);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_contratacion_assets']);

        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action('secop_suite_scheduled_import', [$this->importer, 'run_scheduled']);

        // Reprogramar el cron cuando se guardan los ajustes de auto-actualización.
        add_action('update_option_' . SECOP_SUITE_PREFIX . 'auto_update_enabled', [$this, 'reschedule_import'], 10, 0);
        add_action('update_option_' . SECOP_SUITE_PREFIX . 'auto_update_frequency', [$this, 'reschedule_import'], 10, 0);
        add_action('add_option_' . SECOP_SUITE_PREFIX . 'auto_update_enabled', [$this, 'reschedule_import'], 10, 0);

        // Procesar el POST de limpieza de logs ANTES de que el admin envíe salida
        // (hacerlo dentro del render de la pestaña Logs provocaba "headers already sent").
        add_action('admin_init', [$this, 'maybe_clear_logs']);

        // Redirección de las páginas sueltas antiguas a su pestaña de Configuración.
        // Como esos slugs ya no están registrados, WordPress deniega el acceso en
        // wp-admin/includes/menu.php ANTES de admin_init y dispara
        // «admin_page_access_denied» justo antes de wp_die(): ese es el punto real
        // de redirección. El enganche en admin_init queda como respaldo por si otro
        // código registrara alguno de esos slugs.
        add_action('admin_page_access_denied', [$this, 'maybe_redirect_legacy_pages']);
        add_action('admin_init', [$this, 'maybe_redirect_legacy_pages'], 1);

        // Background import hook
        add_action('secop_suite_run_import', [$this->importer, 'run_background']);

        // WP-CLI
        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('secop', Cli::class);
        }

        // Plugin action links
        add_filter('plugin_action_links_' . SECOP_SUITE_BASENAME, [$this, 'add_action_links']);

        add_action('admin_notices', [$this, 'maybe_sysman_notice']);
        add_action('admin_notices', [$this, 'maybe_cron_notice']);
        add_action('admin_post_secop_suite_programar_importacion', [$this, 'handle_schedule_import']);
        add_action('admin_init', [$this, 'maybe_upgrade_on_load']);
    }

    // ── Internacionalización ─────────────────────────────────────
    public function load_textdomain(): void
    {
        load_plugin_textdomain('secop-suite', false, dirname(SECOP_SUITE_BASENAME) . '/languages');
    }

    // ── Activación / Desactivación ─────────────────────────────
    public function activate(): void
    {
        // Activar NUNCA modifica datos existentes: la tabla y la vista solo se
        // crean si no existen (antes cada activación aplicaba dbDelta a la tabla y
        // hacía CREATE OR REPLACE VIEW sobre la vista de producción).
        $this->database->ensure_table();
        $this->set_default_options();
        $this->maybe_upgrade();

        // v5.1.0: crear VIEW del módulo de seguimiento si no existe (si hay tablas Sysman).
        $this->database->create_view();

        if (get_option(SECOP_SUITE_PREFIX . 'auto_update_enabled', false)) {
            $this->schedule_import();
        }

        flush_rewrite_rules();
    }

    /**
     * Ejecutar migraciones pendientes al actualizar el plugin.
     */
    private function maybe_upgrade(): void
    {
        global $wpdb;
        $current_version = get_option(SECOP_SUITE_PREFIX . 'db_version', '0');

        if (version_compare($current_version, SECOP_SUITE_DB_VERSION, '<')) {
            // v5.0.0 cambio de API (rpmr-utcd): migración destructiva de schema
            if (version_compare($current_version, '5.0.0', '<') && $current_version !== '0') {
                if (!$this->database->migrate_to_new_schema()) {
                    return; // sin cambiar db_version: se reintenta en la próxima carga del administrador
                }
                // Actualizar URL de API por defecto
                update_option(SECOP_SUITE_PREFIX . 'api_url', 'https://www.datos.gov.co/resource/rpmr-utcd.json');
            } else {
                $this->database->create_table();
            }

            // v5.2.0: eliminar la vista huérfana antigua (renombrada a vista_secop_sysman).
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP VIEW IF EXISTS `" . $wpdb->prefix . "dat_seguimiento_dependencias`");

            // v5.9.0: la estructura de vista_secop_sysman cambió (LEFT JOIN desde
            // secop_contracts, columnas *_asiento/rubro_*). Una vista que ya tiene la
            // estructura vigente se CONSERVA tal cual (puede estar ajustada en
            // producción); solo una vista antigua se reemplaza, respaldando antes su
            // definición. Antes se hacía DROP VIEW en cada migración.
            if (!$this->database->view_has_current_structure()) {
                $this->database->create_view(true);
            }

            // v5.11.1: renombrar las cards «(auto) …» antiguas a nombres descriptivos.
            $this->tracking->retitle_auto_cards();

            do_action('secop_suite_after_upgrade', $current_version, SECOP_SUITE_DB_VERSION);

            update_option(SECOP_SUITE_PREFIX . 'db_version', SECOP_SUITE_DB_VERSION);
        }
    }

    public function deactivate(): void
    {
        // Respaldo de la configuración antes de desactivar: si luego se borra una
        // copia antigua cuyo desinstalador elimina datos, se puede restaurar.
        $this->config_backup->snapshot(__('Automático: al desactivar el plugin', 'secop-suite'));
        wp_clear_scheduled_hook('secop_suite_scheduled_import');
        delete_transient(SECOP_SUITE_PREFIX . 'import_progress');
        delete_transient(SECOP_SUITE_PREFIX . 'import_running');
    }

    private function set_default_options(): void
    {
        $defaults = [
            'api_url'               => 'https://www.datos.gov.co/resource/rpmr-utcd.json',
            'nit_entidad'           => '800103923',
            'fecha_inicio'          => '2016-01-01',
            'fecha_fin'             => date('Y-12-31'),
            'auto_update_enabled'   => false,
            'auto_update_frequency' => 'daily',
            'last_import'           => null,
            'total_records'         => 0,
        ];

        foreach ($defaults as $key => $value) {
            $option_name = SECOP_SUITE_PREFIX . $key;
            if (get_option($option_name) === false) {
                add_option($option_name, $value);
            }
        }
    }

    // ── Menú de administración ─────────────────────────────────
    public function register_admin_menu(): void
    {
        add_menu_page(
            __('SECOP Suite', 'secop-suite'),
            __('SECOP Suite', 'secop-suite'),
            'manage_options',
            'secop-suite',
            [$this, 'render_dashboard_page'],
            'dashicons-chart-area',
            21
        );

        // Primera entrada del submenú: el panel (mismo slug que el menú padre).
        add_submenu_page(
            'secop-suite',
            __('Panel de Control', 'secop-suite'),
            __('Panel de Control', 'secop-suite'),
            'manage_options',
            'secop-suite',
            [$this, 'render_dashboard_page']
        );

        // Importar datos, Registros, Depuración BD y Logs se agrupan como
        // pestañas de una sola página. Se registra aquí (después de add_menu_page)
        // para que el hookname tenga el padre correcto; antes Depuración BD se
        // registraba desde su propio admin_menu, ANTES que el menú padre, y en
        // producción su enlace apuntaba a /wp-admin/secop-suite-depuracion (404).
        add_submenu_page(
            'secop-suite',
            __('Configuración', 'secop-suite'),
            __('Configuración', 'secop-suite'),
            'manage_options',
            self::CONFIG_PAGE,
            [$this, 'render_config_page']
        );

        add_submenu_page(
            'secop-suite',
            __('Datos Abiertos', 'secop-suite'),
            __('Datos Abiertos', 'secop-suite'),
            'manage_options',
            'secop-suite-datos-abiertos',
            [$this, 'render_datos_abiertos_page']
        );

        add_submenu_page(
            'secop-suite',
            __('Contratación', 'secop-suite'),
            __('Contratación', 'secop-suite'),
            'manage_options',
            'secop-suite-contratacion',
            [$this, 'render_contratacion_catalog']
        );
    }

    // ── Ordenar submenús alfabéticamente ──────────────────────
    public function sort_submenus(): void
    {
        global $submenu;
        if (empty($submenu['secop-suite'])) return;
        $items = $submenu['secop-suite'];
        // El panel (slug del menú padre) va siempre primero; el resto, alfabético.
        $panel = array_values(array_filter($items, static fn($i) => ($i[2] ?? '') === 'secop-suite'));
        $items = array_filter($items, static fn($i) => ($i[2] ?? '') !== 'secop-suite');
        usort($items, static function ($a, $b) {
            $ta = html_entity_decode(wp_strip_all_tags($a[0]));
            $tb = html_entity_decode(wp_strip_all_tags($b[0]));
            return strcasecmp($ta, $tb);
        });
        $submenu['secop-suite'] = array_merge($panel, array_values($items));
    }

    // ── Registro de configuraciones ────────────────────────────
    public function register_settings(): void
    {
        $sanitize_date = static function (string $value): string {
            $value = sanitize_text_field($value);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : '';
        };

        $sanitize_frequency = static function (string $value): string {
            return in_array($value, ['daily', 'weekly', 'monthly'], true) ? $value : 'daily';
        };

        $fields = [
            'api_url'               => ['sanitize_callback' => 'esc_url_raw'],
            'nit_entidad'           => ['sanitize_callback' => 'sanitize_text_field'],
            'fecha_inicio'          => ['sanitize_callback' => $sanitize_date],
            'fecha_fin'             => ['sanitize_callback' => $sanitize_date],
            'auto_update_enabled'   => ['type' => 'boolean'],
            'auto_update_frequency' => ['sanitize_callback' => $sanitize_frequency],
        ];

        foreach ($fields as $key => $args) {
            register_setting('secop_suite_settings', SECOP_SUITE_PREFIX . $key, $args);
        }
        register_setting('secop_suite_settings', 'secop_suite_delete_data_on_uninstall', ['type' => 'boolean', 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean']);
    }

    // ── Assets de administración ───────────────────────────────
    public function enqueue_admin_assets(string $hook): void
    {
        // Vista previa de [secop_diccionario] en la página de Datos Abiertos.
        if (str_contains($hook, 'secop-suite-datos-abiertos')) {
            wp_enqueue_style('secop-suite-diccionario', SECOP_SUITE_URL . 'assets/css/diccionario.css', [], SECOP_SUITE_VERSION);
        }

        // Import pages
        if (str_contains($hook, 'secop-suite')) {
            wp_enqueue_style(
                'secop-suite-admin',
                SECOP_SUITE_URL . 'assets/css/admin.css',
                [],
                SECOP_SUITE_VERSION
            );

            wp_enqueue_script(
                'secop-suite-admin-import',
                SECOP_SUITE_URL . 'assets/js/admin-import.js',
                ['jquery'],
                SECOP_SUITE_VERSION,
                true
            );

            wp_localize_script('secop-suite-admin-import', 'secopSuiteAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('secop_suite_import'),
                'strings' => [
                    'importing'      => __('Importando...', 'secop-suite'),
                    'complete'       => __('Importación completada', 'secop-suite'),
                    'error'          => __('Error durante la importación', 'secop-suite'),
                    'confirm_cancel' => __('¿Está seguro de cancelar la importación?', 'secop-suite'),
                ],
            ]);
        }
    }

    // ── Render de páginas ──────────────────────────────────────
    public function render_dashboard_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name();

        $total_records = $this->database->get_total_records();
        $total_value   = $this->database->get_total_value();
        $last_import   = get_option(SECOP_SUITE_PREFIX . 'last_import');
        $is_importing  = (bool) get_transient(SECOP_SUITE_PREFIX . 'import_running');

        $total_charts  = (int) wp_count_posts('secop_chart')->publish + (int) wp_count_posts('secop_chart')->draft;
        $total_filters = (int) wp_count_posts('secop_filter')->publish + (int) wp_count_posts('secop_filter')->draft;

        $by_year = $wpdb->get_results("SELECT YEAR(fecha_de_firma_del_contrato) AS anno, COUNT(*) AS count, SUM(valor_contrato) AS total_value FROM {$table_name} WHERE fecha_de_firma_del_contrato IS NOT NULL GROUP BY YEAR(fecha_de_firma_del_contrato) ORDER BY anno DESC LIMIT 10");
        $by_type = $wpdb->get_results("SELECT tipo_de_contrato, COUNT(*) AS count, SUM(valor_contrato) AS total_value FROM {$table_name} WHERE tipo_de_contrato IS NOT NULL GROUP BY tipo_de_contrato ORDER BY count DESC LIMIT 10");

        include SECOP_SUITE_DIR . 'templates/admin/dashboard-page.php';
    }

    // ── Página «Configuración» con pestañas ────────────────────

    /**
     * Pestañas de la página Configuración, en orden (la primera es la de defecto).
     *
     * @return array<string,string> slug => etiqueta
     */
    public static function config_tabs(): array
    {
        return [
            'importar'   => __('Importar datos', 'secop-suite'),
            'registros'  => __('Registros', 'secop-suite'),
            'depuracion' => __('Depuración BD', 'secop-suite'),
            'respaldos'  => __('Respaldos', 'secop-suite'),
            'logs'       => __('Logs', 'secop-suite'),
        ];
    }

    /** Pestaña solicitada en la URL, validada contra la lista blanca. */
    public static function current_config_tab(): string
    {
        $tabs = array_keys(self::config_tabs());
        $raw  = isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        return in_array($raw, $tabs, true) ? $raw : $tabs[0];
    }

    /**
     * URL de una pestaña de Configuración (sin escapar: usar esc_url() al imprimir).
     *
     * @param array<string,scalar> $args Parámetros adicionales de la query.
     */
    public static function config_url(string $tab = 'importar', array $args = []): string
    {
        $query = array_merge(['page' => self::CONFIG_PAGE, 'tab' => $tab], $args);
        // http_build_query codifica claves y valores (también arrays anidados).
        return admin_url('admin.php') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function render_config_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        $tabs       = self::config_tabs();
        $active_tab = self::current_config_tab();
        $render_tab = match ($active_tab) {
            'registros'  => fn() => $this->render_records_tab(),
            'depuracion' => fn() => $this->deduplicator->render_tab(),
            'respaldos'  => fn() => $this->config_backup->render_tab(),
            'logs'       => fn() => $this->render_logs_tab(),
            default      => fn() => $this->render_import_tab(),
        };

        include SECOP_SUITE_DIR . 'templates/admin/config-page.php';
    }

    /**
     * Redirige admin.php?page=secop-suite-import|records|logs|depuracion a la
     * pestaña equivalente de Configuración, conservando el resto de la query.
     * En Registros, el antiguo parámetro de sub-vista «tab» pasa a «vista».
     */
    public function maybe_redirect_legacy_pages(): void
    {
        $page = isset($_GET['page']) && is_string($_GET['page']) ? wp_unslash($_GET['page']) : '';
        if (!isset(self::LEGACY_PAGES[$page])) {
            return;
        }
        // Solo navegación de lectura: un POST a la página antigua perdería su cuerpo,
        // y admin_init también corre en admin-ajax.php.
        if (wp_doing_ajax() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }

        $new_tab = self::LEGACY_PAGES[$page];
        $args    = wp_unslash($_GET);
        unset($args['page']);

        if ($new_tab === 'registros' && isset($args['tab'])) {
            if (!isset($args['vista'])) {
                $args['vista'] = $args['tab'];
            }
        }
        unset($args['tab']);

        wp_safe_redirect(self::config_url($new_tab, $args));
        exit;
    }

    private function render_import_tab(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        $total_records = $this->database->get_total_records();
        $last_import   = get_option(SECOP_SUITE_PREFIX . 'last_import');
        $is_importing  = (bool) get_transient(SECOP_SUITE_PREFIX . 'import_running');
        $total_value   = $this->database->get_total_value();

        include SECOP_SUITE_DIR . 'templates/admin/import-page.php';
    }

    private function render_records_tab(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name();

        // ── Sub-vista activa (el parámetro «tab» es la pestaña de Configuración) ──
        $raw_vista = $_GET['vista'] ?? 'actual';
        $vista     = in_array($raw_vista, ['actual', 'consulta'], true) ? $raw_vista : 'actual';

        $per_page     = 50;
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $offset       = ($current_page - 1) * $per_page;

        // Filtros (vista "actual")
        $where_clauses = ['1=1'];
        $where_values  = [];

        if (!empty($_GET['search'])) {
            $search          = '%' . $wpdb->esc_like(sanitize_text_field($_GET['search'])) . '%';
            $where_clauses[] = '(nom_raz_social_contratista LIKE %s OR objeto_del_proceso LIKE %s OR numero_del_contrato LIKE %s)';
            array_push($where_values, $search, $search, $search);
        }

        if (!empty($_GET['anno'])) {
            $where_clauses[] = 'YEAR(fecha_de_firma_del_contrato) = %s';
            $where_values[]  = sanitize_text_field($_GET['anno']);
        }

        if (!empty($_GET['estado'])) {
            $where_clauses[] = 'estado_del_proceso = %s';
            $where_values[]  = sanitize_text_field($_GET['estado']);
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Total y páginas con el MISMO WHERE que el listado: antes se usaba el
        // total sin filtrar y con un filtro activo aparecían páginas vacías.
        if (!empty($where_values)) {
            $total_records = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}",
                $where_values
            ));
        } else {
            $total_records = $this->database->get_total_records();
        }
        $total_pages = (int) ceil($total_records / $per_page);

        $where_values[] = $per_page;
        $where_values[] = $offset;

        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY fecha_de_firma_del_contrato DESC LIMIT %d OFFSET %d",
            $where_values
        ));

        $years   = $wpdb->get_col("SELECT DISTINCT YEAR(fecha_de_firma_del_contrato) AS y FROM {$table_name} WHERE fecha_de_firma_del_contrato IS NOT NULL ORDER BY y DESC");
        $estados = $wpdb->get_col("SELECT DISTINCT estado_del_proceso FROM {$table_name} WHERE estado_del_proceso IS NOT NULL ORDER BY estado_del_proceso");

        // ── Vista "consulta": datos del VIEW para la vigencia actual ──
        $consulta_rows = [];
        if ($vista === 'consulta') {
            if ($this->database->view_exists()) {
                $view = $this->database->get_view_name();
                // v5.9.0: vista con LEFT JOIN; vigencia por año de firma; columnas del
                // asiento renombradas a *_asiento. Se conservan los alias `anio`/`mes`
                // para la plantilla (anio = año de firma; mes = mes del asiento Sysman).
                // Contratos sin cruce Sysman → "No Registra SYSMAN".
                $consulta_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT COALESCE(NULLIF(`nombredependencia`,''),'No Registra SYSMAN') AS nombredependencia, numero_de_proceso, numero_del_contrato, COALESCE(NULLIF(`nombretercero`,''), NULLIF(`nom_raz_social_contratista`,''),'No Registra SYSMAN') AS nombretercero, valordebito, valorcredito, saldoporejecutaresp, valor_contrato, YEAR(`fecha_de_firma_del_contrato`) AS anio, mes_asiento AS mes FROM `{$view}` WHERE YEAR(`fecha_de_firma_del_contrato`) = %d ORDER BY valordebito DESC LIMIT 200",
                    (int) current_time('Y')
                ), ARRAY_A) ?: [];
            }
        }

        include SECOP_SUITE_DIR . 'templates/admin/records-page.php';
    }

    /**
     * Limpieza de logs, procesada en admin_init (antes de cualquier salida)
     * para que el redirect funcione.
     */
    public function maybe_clear_logs(): void
    {
        if (
            isset($_POST['secop_suite_action'], $_POST['secop_suite_logs_nonce']) &&
            $_POST['secop_suite_action'] === 'clear_logs' &&
            ($_GET['page'] ?? '') === self::CONFIG_PAGE &&
            self::current_config_tab() === 'logs' &&
            wp_verify_nonce($_POST['secop_suite_logs_nonce'], 'secop_suite_clear_logs') &&
            current_user_can('manage_options')
        ) {
            Logger::clear();
            wp_safe_redirect(self::config_url('logs', ['cleared' => 1]));
            exit;
        }
    }

    private function render_logs_tab(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        $logs = Logger::read();

        include SECOP_SUITE_DIR . 'templates/admin/logs-page.php';
    }

    public function render_datos_abiertos_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }
        include SECOP_SUITE_DIR . 'templates/admin/datos-abiertos-page.php';
    }

    public function render_contratacion_catalog(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sin permisos.', 'secop-suite'));
        }
        $tracking = $this->tracking();
        include SECOP_SUITE_DIR . 'templates/admin/contratacion-catalogo.php';
    }

    /**
     * Enqueue the frontend chart stack ONLY on the Contratación catalog page,
     * so the preset chart previews render via the Visualizer engine in admin.
     */
    public function enqueue_contratacion_assets(string $hook): void
    {
        if (!str_contains($hook, 'secop-suite-contratacion')) {
            return;
        }
        // El catálogo NO renderiza gráficas (solo lista + shortcodes), así que NO se
        // carga el stack d3plus (~1.6 MB) aquí — eso hacía lento abrir el módulo.
        wp_enqueue_style('secop-suite-admin', SECOP_SUITE_URL . 'assets/css/admin.css', [], SECOP_SUITE_VERSION);
    }

    // ── Cron ───────────────────────────────────────────────────
    public function add_cron_schedules(array $schedules): array
    {
        $schedules['weekly']  = ['interval' => WEEK_IN_SECONDS,  'display' => __('Semanalmente', 'secop-suite')];
        $schedules['monthly'] = ['interval' => MONTH_IN_SECONDS, 'display' => __('Mensualmente', 'secop-suite')];
        return $schedules;
    }

    public function schedule_import(): void
    {
        $frequency = get_option(SECOP_SUITE_PREFIX . 'auto_update_frequency', 'daily');
        if (!wp_next_scheduled('secop_suite_scheduled_import')) {
            wp_schedule_event(time(), $frequency, 'secop_suite_scheduled_import');
        }
    }

    /**
     * Reprogramar el cron al guardar los ajustes de actualización automática.
     * Antes solo se (des)programaba en activate/deactivate: activar la opción
     * desde la página de importación no programaba nada hasta reactivar el
     * plugin, cambiar la frecuencia nunca reprogramaba y desactivarla nunca
     * cancelaba el evento.
     */
    public function reschedule_import(): void
    {
        wp_clear_scheduled_hook('secop_suite_scheduled_import');
        if (get_option(SECOP_SUITE_PREFIX . 'auto_update_enabled', false)) {
            $this->schedule_import();
        }
    }

    // ── Plugin action links ────────────────────────────────────
    public function add_action_links(array $links): array
    {
        $settings_link = '<a href="' . esc_url(self::config_url()) . '">' . esc_html__('Configuración', 'secop-suite') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // ── Auto-upgrade al cargar (sin necesidad de reactivar) ────
    public function maybe_upgrade_on_load(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) return;

        // 1) Si la versión instalada quedó atrás (update por archivos sin reactivar), correr migraciones.
        $installed = get_option(SECOP_SUITE_PREFIX . 'db_version', '0');
        if (version_compare($installed, SECOP_SUITE_DB_VERSION, '<')) {
            $this->maybe_upgrade();
        }

        // 2) Actualización automática activada pero sin evento programado (la 5.15.0
        //    solo programaba al activar, o se desactivó otra copia): NO se programa
        //    sola —actualizar el plugin no debe iniciar importaciones—; se avisa con
        //    un botón para programarla (maybe_cron_notice()).

        // 3) Garantía adicional: si el VIEW no existe y hay tablas Sysman, crearlo
        //    (gateado por transient para no consultar la BD en cada carga de página).
        if (get_transient(SECOP_SUITE_PREFIX . 'view_checked')) return;
        set_transient(SECOP_SUITE_PREFIX . 'view_checked', 1, HOUR_IN_SECONDS);
        if (!$this->database->view_exists() && $this->database->sysman_tables_exist()) {
            $this->database->create_view();
        }
    }

    // ── Aviso: actualización automática sin programar ──────────

    /**
     * La actualización automática está activada pero no hay ninguna importación
     * programada: se avisa en las páginas del plugin y en Plugins, con un botón
     * para programarla. No se programa sola para que actualizar el plugin nunca
     * dispare una importación que el administrador no pidió.
     */
    public function maybe_cron_notice(): void
    {
        if (!current_user_can('manage_options') || !get_option(SECOP_SUITE_PREFIX . 'auto_update_enabled', false)
            || wp_next_scheduled('secop_suite_scheduled_import')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || (!str_contains($screen->id, 'secop-suite') && $screen->id !== 'plugins')) {
            return;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=secop_suite_programar_importacion'), 'secop_suite_programar_importacion');
        echo '<div class="notice notice-warning"><p>'
           . esc_html__('SECOP Suite: la actualización automática está activada, pero no hay ninguna importación programada, así que los datos no se actualizan solos.', 'secop-suite')
           . ' <a class="button button-primary" href="' . esc_url($url) . '">'
           . esc_html__('Programar la importación automática', 'secop-suite') . '</a></p></div>';
    }

    public function handle_schedule_import(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tiene permisos para programar la importación.', 'secop-suite'), '', ['response' => 403]);
        }
        check_admin_referer('secop_suite_programar_importacion');
        $this->schedule_import();
        Logger::info('Importación automática programada desde el aviso (usuario ' . get_current_user_id() . ')');
        wp_safe_redirect(wp_get_referer() ?: self::config_url('importar'));
        exit;
    }

    // ── Aviso Sysman / diagnóstico ──────────────────────────────
    public function maybe_sysman_notice(): void
    {
        if (!current_user_can('manage_options')) return;
        $screen = get_current_screen();
        if (!$screen || !str_contains($screen->id, 'secop-suite')) return;

        if (!$this->database->sysman_tables_exist()) {
            echo '<div class="notice notice-warning"><p>'
               . esc_html__('SECOP Suite: el módulo de Seguimiento de Dependencias requiere las tablas Sysman (sysman_auxiliar_cuentas y sysman_plan_presupuestal) en la base de datos. El VIEW no se puede crear hasta que existan esas tablas.', 'secop-suite')
               . '</p></div>';
            return;
        }

        // RENDIMIENTO (v5.2.0): el aviso solo ejecuta comprobaciones BARATAS
        // (SHOW TABLES). Los conteos con JOIN pesado se eliminaron porque se
        // ejecutaban en cada carga del admin y colgaban el panel.
        if (!$this->database->view_exists()) {
            $view_name = $this->database->get_view_name();
            echo '<div class="notice notice-warning"><p>'
               . sprintf(
                   /* translators: %s: nombre del VIEW */
                   esc_html__('SECOP Suite: la vista %s no existe en la base de datos. El módulo de Contratación necesita esa vista; el plugin intentará crearla automáticamente, o créela manualmente.', 'secop-suite'),
                   '<code>' . esc_html($view_name) . '</code>'
                 )
               . '</p></div>';
            return;
        }

        // Vista presente y tablas Sysman OK — no se muestra aviso.
    }

    // ── Prevenir clonación ─────────────────────────────────────
    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton'); }
}
