<?php
/**
 * Config_Backup — Respaldos de la configuración de SECOP Suite.
 *
 * Guarda instantáneas de todo lo que se configura a mano en el plugin, para
 * poder recuperarlo si una instalación o una desinstalación lo altera:
 *  - las opciones del plugin (API, NIT, fechas, actualización automática…);
 *  - las gráficas, filtros y cards (post, estado y TODOS sus metadatos) con su
 *    ID original, que es el que usan los shortcodes [secop_chart id="…"];
 *  - la definición de la vista de Contratación (SHOW CREATE VIEW).
 * No guarda los contratos (se reimportan desde datos.gov.co) ni las tablas Sysman.
 *
 * Las instantáneas viven en la tabla {prefijo}secop_respaldos, que el
 * desinstalador de las versiones 5.15.0–5.17.0 no conoce y por tanto no borra.
 * Se crean solas al cargar cada versión nueva, al desactivar el plugin y antes
 * de cualquier cambio que reemplace la vista o migre la tabla de contratos.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Config_Backup
{
    public const FORMAT          = 'secop-suite-respaldo';
    private const FORMAT_VERSION = 1;
    private const KEEP_AUTO      = 25;
    private const MAX_IMPORT     = 20 * 1024 * 1024;
    private const NONCE          = 'secop_respaldos';
    /** Sin el prefijo «secop_suite_» a propósito: el desinstalador antiguo borra ese prefijo. */
    private const VERSION_OPTION = 'secop_respaldos_ultima_version';
    private const LOCK           = 'secop_respaldos_lock';
    private const POST_TYPES     = ['secop_chart', 'secop_filter', 'secop_dep_card'];
    private const POST_STATUSES  = ['publish', 'draft', 'pending', 'private', 'future', 'trash'];
    private const SKIP_META      = ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date'];
    private const FAIL_BACKOFF   = 'secop_respaldos_fallo';
    private const SHORTCODE_RE   = '/\[(secop_[a-z_]+|sdv_chart)\b([^\]]*)\]/i';
    /** Shortcodes que apuntan a un elemento guardado: etiqueta => [atributo, tipo de post]. */
    private const SHORTCODE_REFS = [
        'secop_chart'     => ['id', 'secop_chart'],
        'sdv_chart'       => ['id', 'secop_chart'],
        'secop_filter'    => ['id', 'secop_filter'],
        'secop_dep_chart' => ['card', 'secop_dep_card'],
    ];
    /**
     * Opciones que describen los DATOS (no la configuración) o el esquema: no se
     * restauran. Restaurar db_version, por ejemplo, volvería a ejecutar migraciones.
     */
    private const RESTORE_SKIP_OPTIONS = [
        'secop_suite_db_version', 'secop_suite_total_records', 'secop_suite_last_import',
        'secop_suite_import_progress', 'secop_suite_import_running', 'secop_suite_view_checked',
        'secop_suite_dedup_backup_version', 'secop_suite_log_dir_suffix',
    ];

    private Database $db;
    private bool $table_ready = false;

    public function __construct(Database $db)
    {
        $this->db = $db;
        add_action('init', [$this, 'maybe_snapshot_on_version_change'], 20);
        add_action('secop_suite_antes_de_cambio', [$this, 'snapshot_before_change'], 10, 1);
        foreach (['crear', 'descargar', 'restaurar', 'eliminar', 'importar'] as $action) {
            add_action('admin_post_secop_respaldo_' . $action, [$this, 'handle_' . $action]);
        }
    }

    // ── Almacenamiento ─────────────────────────────────────────

    public function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'secop_respaldos';
    }

    private function ensure_table(): bool
    {
        if ($this->table_ready) {
            return true;
        }
        global $wpdb;
        $table   = $this->table();
        $charset = $wpdb->get_charset_collate();
        // CREATE TABLE IF NOT EXISTS (no dbDelta): nunca altera una tabla existente.
        $ok = $wpdb->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            reason VARCHAR(191) NOT NULL,
            is_auto TINYINT(1) NOT NULL DEFAULT 1,
            plugin_version VARCHAR(20) NOT NULL,
            summary TEXT NOT NULL,
            data LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_created (created_at)
        ) {$charset}");
        $this->table_ready = $ok !== false;
        return $this->table_ready;
    }

    // ── Instantánea ────────────────────────────────────────────

    /** Recoge la configuración actual del plugin. */
    public function collect(): array
    {
        global $wpdb;

        $options = [];
        $names   = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
            $wpdb->esc_like('secop_suite_') . '%'
        )) ?: [];
        foreach ($names as $name) {
            if (!str_contains($name, '_lock')) {
                $options[$name] = get_option($name);
            }
        }

        // Consulta directa (sin WP_Query): no depende de filtros de otros plugins
        // ni de que los tipos de post ya estén registrados.
        $posts = [];
        $types = "'" . implode("','", self::POST_TYPES) . "'";
        $ids   = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$types})
             AND post_status NOT IN ('auto-draft','inherit') ORDER BY ID"
        ) ?: [];
        foreach ($ids as $id) {
            $p = get_post((int) $id);
            if (!$p) {
                continue;
            }
            $meta = [];
            foreach (array_keys((array) get_post_meta($p->ID)) as $key) {
                if (!in_array($key, self::SKIP_META, true)) {
                    $meta[$key] = get_post_meta($p->ID, $key, false);
                }
            }
            $posts[] = [
                'ID'            => (int) $p->ID,
                'post_type'     => $p->post_type,
                'post_title'    => $p->post_title,
                'post_status'   => $p->post_status,
                'post_name'     => $p->post_name,
                'post_content'  => $p->post_content,
                'post_excerpt'  => $p->post_excerpt,
                'post_date'     => $p->post_date,
                'post_date_gmt' => $p->post_date_gmt,
                'menu_order'    => (int) $p->menu_order,
                'meta'          => $meta,
            ];
        }

        return [
            'formato'         => self::FORMAT,
            'version_formato' => self::FORMAT_VERSION,
            'plugin_version'  => defined('SECOP_SUITE_VERSION') ? SECOP_SUITE_VERSION : '',
            'fecha'           => current_time('mysql'),
            'sitio'           => home_url('/'),
            'prefijo'         => $wpdb->prefix,
            'opciones'        => $options,
            'posts'           => $posts,
            'vista'           => ['nombre' => $this->db->get_view_name(), 'sql' => $this->db->view_definition()],
            'paginas'         => $this->shortcode_pages(),
            'contratos'       => $this->db->table_exists() ? $this->db->get_total_records() : null,
        ];
    }

    /** Resumen legible de una instantánea. */
    private function summarize(array $data): array
    {
        $count = array_fill_keys(self::POST_TYPES, 0);
        foreach ((array) ($data['posts'] ?? []) as $p) {
            if (isset($count[$p['post_type'] ?? ''])) {
                $count[$p['post_type']]++;
            }
        }
        return [
            'opciones'  => count((array) ($data['opciones'] ?? [])),
            'graficas'  => $count['secop_chart'],
            'filtros'   => $count['secop_filter'],
            'cards'     => $count['secop_dep_card'],
            'vista'     => !empty($data['vista']['sql']),
            'paginas'   => count((array) ($data['paginas'] ?? [])),
            'contratos' => $data['contratos'] ?? null,
        ];
    }

    /** Crea una instantánea. Devuelve su ID (0 si no se pudo guardar). */
    public function snapshot(string $reason, bool $auto = true): int
    {
        if (!$this->ensure_table()) {
            Logger::error('Respaldo de configuración: no se pudo crear la tabla ' . $this->table());
            return 0;
        }
        global $wpdb;
        $data = $this->collect();
        $json = wp_json_encode($data);
        if (!is_string($json)) {
            Logger::error('Respaldo de configuración: no se pudo serializar la configuración');
            return 0;
        }
        $ok = $wpdb->insert($this->table(), [
            'created_at'     => current_time('mysql'),
            'reason'         => mb_substr($reason, 0, 190),
            'is_auto'        => $auto ? 1 : 0,
            'plugin_version' => defined('SECOP_SUITE_VERSION') ? SECOP_SUITE_VERSION : '',
            'summary'        => (string) wp_json_encode($this->summarize($data)),
            'data'           => $json,
            'created_by'     => get_current_user_id(),
        ], ['%s', '%s', '%d', '%s', '%s', '%s', '%d']);
        if ($ok === false) {
            Logger::error('Respaldo de configuración: error al guardar — ' . $wpdb->last_error);
            return 0;
        }
        $id = (int) $wpdb->insert_id;
        $this->prune();
        Logger::info("Respaldo de configuración #{$id} creado: {$reason}");
        return $id;
    }

    /** Conserva las últimas KEEP_AUTO instantáneas automáticas (las manuales nunca se borran solas). */
    private function prune(): void
    {
        global $wpdb;
        $table = $this->table();
        $ids   = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE is_auto = 1 ORDER BY id DESC LIMIT 18446744073709551615 OFFSET %d",
            self::KEEP_AUTO
        )) ?: [];
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            $wpdb->query("DELETE FROM `{$table}` WHERE id IN ({$in})");
        }
    }

    /** Instantánea automática la primera vez que se carga cada versión del plugin. */
    public function maybe_snapshot_on_version_change(): void
    {
        $last = (string) get_option(self::VERSION_OPTION, '');
        if ($last === SECOP_SUITE_VERSION || get_transient(self::LOCK) || get_transient(self::FAIL_BACKOFF)) {
            return;
        }
        set_transient(self::LOCK, 1, MINUTE_IN_SECONDS);
        $reason = $last !== ''
            ? sprintf(__('Automático: primera carga tras actualizar de %1$s a %2$s', 'secop-suite'), $last, SECOP_SUITE_VERSION)
            : sprintf(__('Automático: primera carga de la versión %s', 'secop-suite'), SECOP_SUITE_VERSION);
        if ($this->snapshot($reason) > 0) {
            // Autocargada: evita una consulta extra en cada petición.
            update_option(self::VERSION_OPTION, SECOP_SUITE_VERSION, true);
        } else {
            set_transient(self::FAIL_BACKOFF, 1, HOUR_IN_SECONDS); // reintentar en una hora, sin llenar el log
        }
        delete_transient(self::LOCK);
    }

    /** Hook «secop_suite_antes_de_cambio»: instantánea antes de un cambio en la vista o la tabla. */
    public function snapshot_before_change($reason = ''): void
    {
        $this->snapshot(sprintf(__('Automático: antes de «%s»', 'secop-suite'), (string) $reason));
    }

    // ── Lectura ────────────────────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    public function list(): array
    {
        global $wpdb;
        $table = $this->table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return [];
        }
        return $wpdb->get_results(
            "SELECT id, created_at, reason, is_auto, plugin_version, summary, created_by, LENGTH(data) AS bytes
             FROM `{$table}` ORDER BY id DESC LIMIT 200",
            ARRAY_A
        ) ?: [];
    }

    public function get(int $id): ?array
    {
        global $wpdb;
        if (!$this->ensure_table()) {
            return null;
        }
        $json = $wpdb->get_var($wpdb->prepare("SELECT data FROM `{$this->table()}` WHERE id = %d", $id));
        $data = is_string($json) ? json_decode($json, true) : null;
        return is_array($data) ? $data : null;
    }

    /**
     * Páginas y entradas que usan shortcodes del plugin, con las referencias a
     * gráficas/filtros que ya no existen (p. ej. tras un borrado).
     *
     * @return array<int,array{ID:int,titulo:string,tipo:string,estado:string,shortcodes:array<int,string>,rotos:array<int,string>}>
     */
    public function shortcode_pages(): array
    {
        global $wpdb;
        // Contenido de la página y, para Elementor, sus datos en _elementor_data.
        $rows = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_type, p.post_status, p.post_content, m.meta_value AS extra
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
             WHERE p.post_status IN ('publish','draft','pending','private','future')
               AND p.post_type NOT IN ('revision','nav_menu_item','secop_chart','secop_filter','secop_dep_card')
               AND (p.post_content LIKE '%[secop\\_%' OR p.post_content LIKE '%[sdv\\_chart%'
                    OR m.meta_value LIKE '%[secop\\_%' OR m.meta_value LIKE '%[sdv\\_chart%')
             ORDER BY p.post_type, p.post_title, p.ID LIMIT 500",
            ARRAY_A
        ) ?: [];
        $by_id = [];
        foreach ($rows as $r) {
            $id = (int) $r['ID'];
            $by_id[$id] ??= $r + ['contenido' => (string) $r['post_content']];
            $by_id[$id]['contenido'] .= ' ' . (string) ($r['extra'] ?? '');
        }

        $pages = [];
        foreach ($by_id as $r) {
            $found = self::parse_shortcodes((string) $r['contenido']);
            if (!$found) {
                continue;
            }
            $codes = [];
            $rotos = [];
            foreach ($found as $sc) {
                $codes[] = $sc['code'];
                if ($sc['type'] === null || $sc['id'] === null) {
                    continue; // p. ej. [secop_consulta] o [secop_dep_chart preset="…"]
                }
                $post = get_post($sc['id']);
                if (!$post || $post->post_type !== $sc['type'] || $post->post_status === 'trash') {
                    $rotos[] = $sc['code'];
                }
            }
            $pages[] = [
                'ID'         => (int) $r['ID'],
                'titulo'     => (string) $r['post_title'],
                'tipo'       => (string) $r['post_type'],
                'estado'     => (string) $r['post_status'],
                'shortcodes' => array_values(array_unique($codes)),
                'rotos'      => array_values(array_unique($rotos)),
            ];
        }
        return $pages;
    }

    /**
     * Shortcodes del plugin presentes en un contenido. Para los que apuntan a un
     * elemento guardado devuelve el tipo de post esperado y su ID.
     *
     * @return array<int,array{code:string,type:?string,id:?int}>
     */
    public static function parse_shortcodes(string $content): array
    {
        if (!preg_match_all(self::SHORTCODE_RE, $content, $m, PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        foreach ($m as $sc) {
            $tag  = strtolower($sc[1]);
            $type = null;
            $id   = null;
            if (isset(self::SHORTCODE_REFS[$tag])) {
                [$attr, $type] = self::SHORTCODE_REFS[$tag];
                // Admite comillas simples, dobles o escapadas (Elementor guarda \").
                $id = preg_match('/(?<![\w-])' . $attr . '\s*=\s*[\\\\"\']*(\d+)/i', $sc[2], $idm) ? (int) $idm[1] : null;
            }
            $out[] = ['code' => stripslashes($sc[0]), 'type' => $type, 'id' => $id];
        }
        return $out;
    }

    /**
     * Cuerpo SELECT de una definición guardada con SHOW CREATE VIEW, o null si no
     * es una única sentencia CREATE … VIEW sobre la vista $view. DEFINER y SQL
     * SECURITY se descartan (exigen privilegios especiales).
     */
    public static function view_select_body(string $sql, string $view): ?string
    {
        $sql = trim($sql);
        if ($sql === '' || $view === '' || str_contains($sql, ';')) {
            return null;
        }
        $pattern = '/^CREATE\s+(?:OR\s+REPLACE\s+)?(?:ALGORITHM\s*=\s*\w+\s+)?(?:DEFINER\s*=\s*\S+\s+)?'
                 . '(?:SQL\s+SECURITY\s+\w+\s+)?VIEW\s+`?' . preg_quote($view, '/') . '`?\s+AS\s+((?:select\b|with\b|\().+)$/is';
        return preg_match($pattern, $sql, $m) ? $m[1] : null;
    }

    // ── Restauración ───────────────────────────────────────────

    public function is_valid(mixed $data): bool
    {
        return is_array($data)
            && ($data['formato'] ?? '') === self::FORMAT
            && is_array($data['opciones'] ?? null)
            && is_array($data['posts'] ?? null);
    }

    /**
     * Restaura una instantánea. Las gráficas, filtros y cards conservan su ID
     * (si el ID está libre se recrea con el mismo número), de modo que los
     * shortcodes de las páginas vuelven a funcionar sin editarlas. No borra
     * nada que no esté en el respaldo. Antes crea un respaldo del estado actual.
     *
     * @return array{ok:bool,valido:bool,antes:int,opciones:int,actualizados:int,creados:int,ids_cambiados:array<int,int>,vista:bool,errores:array<int,string>}
     */
    public function restore(array $data, bool $restore_view): array
    {
        $res = ['ok' => false, 'valido' => false, 'antes' => 0, 'opciones' => 0, 'actualizados' => 0, 'creados' => 0, 'ids_cambiados' => [], 'vista' => false, 'errores' => []];
        if (!$this->is_valid($data)) {
            $res['errores'][] = __('El archivo no es un respaldo válido de SECOP Suite.', 'secop-suite');
            return $res;
        }
        $res['valido'] = true;

        $res['antes'] = $this->snapshot(__('Automático: antes de restaurar un respaldo', 'secop-suite'));

        foreach ($data['opciones'] as $name => $value) {
            if (!is_string($name) || !str_starts_with($name, 'secop_suite_') || str_contains($name, '_lock')
                || in_array($name, self::RESTORE_SKIP_OPTIONS, true)) {
                continue;
            }
            update_option($name, $value);
            $res['opciones']++;
        }

        foreach ($data['posts'] as $p) {
            if (!is_array($p) || !in_array($p['post_type'] ?? '', self::POST_TYPES, true)) {
                continue;
            }
            $old_id  = (int) ($p['ID'] ?? 0);
            $status  = in_array($p['post_status'] ?? '', self::POST_STATUSES, true) ? $p['post_status'] : 'draft';
            $postarr = wp_slash([
                'post_type'    => $p['post_type'],
                'post_title'   => (string) ($p['post_title'] ?? ''),
                'post_status'  => $status,
                'post_name'    => (string) ($p['post_name'] ?? ''),
                'post_content' => (string) ($p['post_content'] ?? ''),
                'post_excerpt' => (string) ($p['post_excerpt'] ?? ''),
                'menu_order'   => (int) ($p['menu_order'] ?? 0),
            ]);

            $existing = $old_id > 0 ? get_post($old_id) : null;
            if ($existing && $existing->post_type === $p['post_type']) {
                $postarr['ID'] = $old_id;
                $result        = wp_update_post($postarr, true);
                $target        = is_wp_error($result) ? 0 : $old_id;
                $target && $res['actualizados']++;
            } else {
                if (!$existing && $old_id > 0) {
                    $postarr['import_id'] = $old_id; // conserva el ID si está libre
                }
                if (!empty($p['post_date'])) {
                    $postarr['post_date']     = (string) $p['post_date'];
                    $postarr['post_date_gmt'] = (string) ($p['post_date_gmt'] ?? '');
                }
                $result = wp_insert_post($postarr, true);
                $target = is_wp_error($result) ? 0 : (int) $result;
                if ($target) {
                    $res['creados']++;
                    if ($target !== $old_id) {
                        $res['ids_cambiados'][$old_id] = $target;
                    }
                }
            }
            if (!$target) {
                $res['errores'][] = sprintf(__('No se pudo restaurar «%s».', 'secop-suite'), (string) ($p['post_title'] ?? $old_id));
                continue;
            }

            foreach ((array) ($p['meta'] ?? []) as $key => $values) {
                if (!is_string($key) || $key === '' || in_array($key, self::SKIP_META, true)) {
                    continue;
                }
                delete_post_meta($target, $key);
                foreach ((array) $values as $value) {
                    add_post_meta($target, $key, wp_slash($value));
                }
            }
        }

        $this->sync_cron();

        if ($restore_view && !empty($data['vista']['sql'])) {
            $res['vista'] = $this->restore_view((string) $data['vista']['sql']);
            if (!$res['vista']) {
                $res['errores'][] = __('No se pudo restaurar la vista de Contratación (definición no válida o sin permisos).', 'secop-suite');
            }
        }

        Plugin::get_instance()->importer()->invalidate_chart_cache();
        wp_cache_delete('secop_cols_' . md5($this->db->get_view_name()), 'secop_suite');
        $res['ok'] = empty($res['errores']);
        Logger::warning(sprintf(
            'Respaldo de configuración restaurado: %d opciones, %d actualizados, %d creados, %d IDs cambiados, vista %s (usuario %d)',
            $res['opciones'], $res['actualizados'], $res['creados'], count($res['ids_cambiados']), $res['vista'] ? 'sí' : 'no', get_current_user_id()
        ));
        return $res;
    }

    /**
     * Deja la importación programada como indican las opciones restauradas
     * (activa o no, y con su frecuencia), sin reprogramarla si ya coincide.
     */
    private function sync_cron(): void
    {
        $hook = 'secop_suite_scheduled_import';
        if (!get_option('secop_suite_auto_update_enabled', false)) {
            wp_clear_scheduled_hook($hook);
            return;
        }
        $event = wp_get_scheduled_event($hook);
        if (!$event || $event->schedule !== (string) get_option('secop_suite_auto_update_frequency', 'daily')) {
            Plugin::get_instance()->reschedule_import();
        }
    }

    /**
     * Vuelve a crear la vista a partir de un SHOW CREATE VIEW guardado. Solo se
     * acepta una única sentencia CREATE … VIEW sobre la vista del plugin; se
     * quitan DEFINER y SQL SECURITY (exigen privilegios especiales). La
     * definición anterior ya quedó en el respaldo que restore() crea al empezar.
     */
    private function restore_view(string $sql): bool
    {
        global $wpdb;
        $view = $this->db->get_view_name();
        $body = self::view_select_body($sql, $view);
        if ($body === null) {
            return false;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- definición validada en view_select_body()
        return $wpdb->query("CREATE OR REPLACE VIEW `{$view}` AS {$body}") !== false;
    }

    // ── Acciones del administrador ─────────────────────────────

    private function guard(string $action): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tiene permisos para gestionar los respaldos.', 'secop-suite'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE . '_' . $action);
    }

    private function back(array $notice): never
    {
        set_transient('secop_respaldos_aviso_' . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(Plugin::config_url('respaldos'));
        exit;
    }

    public function handle_crear(): void
    {
        $this->guard('crear');
        $id = $this->snapshot(__('Manual', 'secop-suite'), false);
        $this->back($id > 0
            ? ['tipo' => 'success', 'texto' => sprintf(__('Respaldo #%d creado.', 'secop-suite'), $id)]
            : ['tipo' => 'error', 'texto' => __('No se pudo crear el respaldo; revise los logs.', 'secop-suite')]);
    }

    public function handle_descargar(): void
    {
        $this->guard('descargar');
        $id   = absint($_GET['id'] ?? 0);
        $data = $this->get($id);
        if ($data === null) {
            $this->back(['tipo' => 'error', 'texto' => __('Respaldo no encontrado.', 'secop-suite')]);
        }
        $file = 'secop-suite-respaldo-' . $id . '-' . preg_replace('/[^0-9]/', '', (string) ($data['fecha'] ?? '')) . '.json';
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('X-Content-Type-Options: nosniff');
        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function handle_restaurar(): void
    {
        $this->guard('restaurar');
        $data = $this->get(absint($_POST['id'] ?? 0));
        if ($data === null) {
            $this->back(['tipo' => 'error', 'texto' => __('Respaldo no encontrado.', 'secop-suite')]);
        }
        $this->back($this->restore_notice($this->restore($data, !empty($_POST['vista']))));
    }

    public function handle_importar(): void
    {
        $this->guard('importar');
        $file = $_FILES['respaldo'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            $this->back(['tipo' => 'error', 'texto' => __('Seleccione un archivo de respaldo (.json).', 'secop-suite')]);
        }
        if ((int) $file['size'] > self::MAX_IMPORT) {
            $this->back(['tipo' => 'error', 'texto' => __('El archivo es demasiado grande.', 'secop-suite')]);
        }
        $data = json_decode((string) file_get_contents((string) $file['tmp_name']), true);
        $this->back($this->restore_notice($this->restore(is_array($data) ? $data : [], !empty($_POST['vista']))));
    }

    public function handle_eliminar(): void
    {
        $this->guard('eliminar');
        global $wpdb;
        $id = absint($_POST['id'] ?? 0);
        if ($this->ensure_table() && $id > 0) {
            $wpdb->delete($this->table(), ['id' => $id], ['%d']);
        }
        $this->back(['tipo' => 'success', 'texto' => sprintf(__('Respaldo #%d eliminado.', 'secop-suite'), $id)]);
    }

    private function restore_notice(array $r): array
    {
        if (empty($r['valido'])) {
            return ['tipo' => 'error', 'texto' => implode(' ', $r['errores'])];
        }
        $texto = sprintf(
            __('Restauración: %1$d opciones, %2$d gráficas/filtros/cards actualizados y %3$d recreados%4$s. Antes se guardó el estado anterior como respaldo #%5$d.', 'secop-suite'),
            $r['opciones'], $r['actualizados'], $r['creados'],
            $r['vista'] ? __('; vista de Contratación restaurada', 'secop-suite') : '',
            $r['antes']
        );
        if ($r['ids_cambiados']) {
            $pairs = [];
            foreach ($r['ids_cambiados'] as $old => $new) {
                $pairs[] = "{$old} → {$new}";
            }
            $texto .= ' ' . sprintf(__('Estos elementos recibieron un ID nuevo porque el original estaba ocupado; actualice los shortcodes que los usan: %s.', 'secop-suite'), implode(', ', $pairs));
        }
        if ($r['errores']) {
            $texto .= ' ' . implode(' ', $r['errores']);
        }
        return ['tipo' => $r['ok'] ? 'success' : 'error', 'texto' => $texto];
    }

    // ── Pestaña «Respaldos» ────────────────────────────────────

    public function render_tab(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }
        $key    = 'secop_respaldos_aviso_' . get_current_user_id();
        $notice = get_transient($key);
        if (is_array($notice)) {
            delete_transient($key);
        }
        $backups = $this->list();
        $pages   = $this->shortcode_pages();
        $nonce   = self::NONCE;
        include SECOP_SUITE_DIR . 'templates/admin/respaldos-page.php';
    }
}
