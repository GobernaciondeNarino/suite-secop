<?php
/**
 * Plugin Name: SECOP Suite
 * Plugin URI: https://github.com/GobernaciondeNarino/secop-suite
 * Description: Plugin integral para la importación, almacenamiento y visualización interactiva de datos contractuales del SECOP (Sistema Electrónico de Contratación Pública) de Colombia. Combina importación automatizada desde datos.gov.co con gráficas D3plus configurables mediante shortcodes.
 * Version: 5.15.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Jonnathan Bucheli Galindo - Gobernación de Nariño
 * Author URI: https://narino.gov.co
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: secop-suite
 * Domain Path: /languages
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

// ─── Constantes ────────────────────────────────────────────────
define('SECOP_SUITE_VERSION', '5.15.0');
define('SECOP_SUITE_DB_VERSION', '5.11.1');
define('SECOP_SUITE_DIR', plugin_dir_path(__FILE__));
define('SECOP_SUITE_URL', plugin_dir_url(__FILE__));
define('SECOP_SUITE_BASENAME', plugin_basename(__FILE__));
define('SECOP_SUITE_PREFIX', 'secop_suite_');

// ─── Autoload de clases ────────────────────────────────────────
spl_autoload_register(static function (string $class): void {
    $prefix = 'SecopSuite\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = SECOP_SUITE_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $relative)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ─── Clase principal ───────────────────────────────────────────
final class Plugin
{
    private static ?Plugin $instance = null;
    private Database $database;
    private Importer $importer;
    private Visualizer $visualizer;
    private Filter $filter;
    private Rest_Api $rest_api;
    private Updater $updater;
    private Tracking $tracking;

    private function __construct()
    {
        $this->database   = new Database();
        $this->importer   = new Importer($this->database);
        $this->visualizer = new Visualizer($this->database);
        $this->filter     = new Filter($this->database);
        $this->rest_api   = new Rest_Api($this->database);
        $this->updater    = new Updater();
        $this->tracking   = new Tracking($this->database);

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

    // ── Hooks ──────────────────────────────────────────────────
    private function register_hooks(): void
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_menu', [$this, 'sort_submenus'], 9999);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_contratacion_assets']);

        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action('secop_suite_scheduled_import', [$this->importer, 'run_scheduled']);

        // Background import hook
        add_action('secop_suite_run_import', [$this->importer, 'run_background']);

        // WP-CLI
        if (defined('WP_CLI') && \WP_CLI) {
            \WP_CLI::add_command('secop', Cli::class);
        }

        // Plugin action links
        add_filter('plugin_action_links_' . SECOP_SUITE_BASENAME, [$this, 'add_action_links']);

        add_action('admin_notices', [$this, 'maybe_sysman_notice']);
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
        $this->database->create_table();
        $this->set_default_options();
        $this->maybe_upgrade();

        // v5.1.0: crear VIEW del módulo de seguimiento (si hay tablas Sysman).
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
                $this->database->migrate_to_new_schema();
                // Actualizar URL de API por defecto
                update_option(SECOP_SUITE_PREFIX . 'api_url', 'https://www.datos.gov.co/resource/rpmr-utcd.json');
            } else {
                $this->database->create_table();
            }

            // v5.2.0: eliminar la vista huérfana antigua (renombrada a vista_secop_sysman).
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP VIEW IF EXISTS `" . $wpdb->prefix . "dat_seguimiento_dependencias`");

            // v5.9.0: la definición de vista_secop_sysman cambió estructuralmente
            // (LEFT JOIN desde secop_contracts, vigencia por fecha_de_firma, columnas
            // *_asiento/rubro_*). DROP explícito antes de recrearla para refrescar la
            // definición en la actualización (CREATE OR REPLACE también basta).
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP VIEW IF EXISTS `" . $this->database->get_view_name() . "`");

            // v5.1.0: crear VIEW del módulo de seguimiento (si hay tablas Sysman).
            $this->database->create_view();

            // v5.11.1: renombrar las cards «(auto) …» antiguas a nombres descriptivos.
            $this->tracking->retitle_auto_cards();

            do_action('secop_suite_after_upgrade', $current_version, SECOP_SUITE_DB_VERSION);

            update_option(SECOP_SUITE_PREFIX . 'db_version', SECOP_SUITE_DB_VERSION);
        }
    }

    public function deactivate(): void
    {
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

        add_submenu_page(
            'secop-suite',
            __('Importar Datos', 'secop-suite'),
            __('Importar Datos', 'secop-suite'),
            'manage_options',
            'secop-suite-import',
            [$this, 'render_import_page']
        );

        add_submenu_page(
            'secop-suite',
            __('Registros', 'secop-suite'),
            __('Registros', 'secop-suite'),
            'manage_options',
            'secop-suite-records',
            [$this, 'render_records_page']
        );

        add_submenu_page(
            'secop-suite',
            __('Logs', 'secop-suite'),
            __('Logs', 'secop-suite'),
            'manage_options',
            'secop-suite-logs',
            [$this, 'render_logs_page']
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
        usort($items, static function ($a, $b) {
            $ta = html_entity_decode(wp_strip_all_tags($a[0]));
            $tb = html_entity_decode(wp_strip_all_tags($b[0]));
            return strcasecmp($ta, $tb);
        });
        $submenu['secop-suite'] = array_values($items);
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
    }

    // ── Assets de administración ───────────────────────────────
    public function enqueue_admin_assets(string $hook): void
    {
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

    public function render_import_page(): void
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

    public function render_records_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name();

        // ── Tab activa ──────────────────────────────────────────
        $raw_tab = $_GET['tab'] ?? 'actual';
        $tab     = in_array($raw_tab, ['actual', 'consulta'], true) ? $raw_tab : 'actual';

        $per_page     = 50;
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $offset       = ($current_page - 1) * $per_page;

        $total_records = $this->database->get_total_records();
        $total_pages   = (int) ceil($total_records / $per_page);

        // Filtros (tab "actual")
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

        $where_sql      = implode(' AND ', $where_clauses);
        $where_values[] = $per_page;
        $where_values[] = $offset;

        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY fecha_de_firma_del_contrato DESC LIMIT %d OFFSET %d",
            $where_values
        ));

        $years   = $wpdb->get_col("SELECT DISTINCT YEAR(fecha_de_firma_del_contrato) AS y FROM {$table_name} WHERE fecha_de_firma_del_contrato IS NOT NULL ORDER BY y DESC");
        $estados = $wpdb->get_col("SELECT DISTINCT estado_del_proceso FROM {$table_name} WHERE estado_del_proceso IS NOT NULL ORDER BY estado_del_proceso");

        // ── Tab "consulta": datos del VIEW para la vigencia actual ──
        $consulta_rows = [];
        if ($tab === 'consulta') {
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

    public function render_logs_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }

        // Procesar limpieza de logs
        if (
            isset($_POST['secop_suite_action'], $_POST['secop_suite_logs_nonce']) &&
            $_POST['secop_suite_action'] === 'clear_logs' &&
            wp_verify_nonce($_POST['secop_suite_logs_nonce'], 'secop_suite_clear_logs') &&
            current_user_can('manage_options')
        ) {
            Logger::clear();
            wp_safe_redirect(admin_url('admin.php?page=secop-suite-logs&cleared=1'));
            exit;
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

    // ── Plugin action links ────────────────────────────────────
    public function add_action_links(array $links): array
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=secop-suite') . '">' . __('Configuración', 'secop-suite') . '</a>';
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

        // 2) Garantía adicional: si el VIEW no existe y hay tablas Sysman, crearlo
        //    (gateado por transient para no consultar la BD en cada carga de página).
        if (get_transient(SECOP_SUITE_PREFIX . 'view_checked')) return;
        set_transient(SECOP_SUITE_PREFIX . 'view_checked', 1, HOUR_IN_SECONDS);
        if (!$this->database->view_exists() && $this->database->sysman_tables_exist()) {
            $this->database->create_view();
        }
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

// ─── Inicializar ───────────────────────────────────────────────
Plugin::get_instance();
