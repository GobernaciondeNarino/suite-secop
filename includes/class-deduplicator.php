<?php
/**
 * Deduplicator — Módulo de depuración de la base de datos (v5.17.0).
 *
 * Detecta y elimina registros duplicados en las tablas del plugin (contratos
 * SECOP, tablas Sysman y tablas dat_*). Principios de seguridad:
 *  - Solo tablas en lista blanca, con clave primaria de una columna.
 *  - Un "duplicado" es una fila cuyas columnas de criterio coinciden EXACTAMENTE
 *    con las de otra (huella SHA-256 con codificación sin ambigüedades: distingue
 *    NULL de vacío, mayúsculas, tildes y espacios; compara el valor completo,
 *    no solo los primeros 1024 bytes como haría un GROUP BY sobre TEXT).
 *  - Siempre se conserva una fila por grupo (la de mayor o menor ID).
 *  - Toda fila eliminada se copia antes a una tabla de respaldo y puede
 *    restaurarse por lote.
 *  - Solo administradores (manage_options) con nonce; un candado impide dos
 *    depuraciones simultáneas.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Deduplicator
{
    private const PAGE             = 'secop-suite-depuracion';
    private const NONCE            = 'secop_dedup';
    private const LOCK             = 'secop_suite_dedup_lock';
    private const BACKUP_VERSION   = '1';
    private const MAX_DELETE       = 20000;
    private const CHUNK            = 500;
    private const SAMPLE_GROUPS    = 25;
    private const MAX_CRITERIA     = 40;

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        foreach (['analyze', 'run', 'restore', 'purge', 'unique_index'] as $action) {
            add_action('wp_ajax_secop_dedup_' . $action, [$this, 'ajax_' . $action]);
        }
    }

    // ── Funciones puras (testeables sin WordPress) ─────────────

    /**
     * Expresión SQL de la huella de una fila según las columnas de criterio.
     * Cada valor se codifica como N (NULL) o V<longitud>:<valor>, de modo que dos
     * combinaciones distintas nunca producen la misma cadena antes del hash.
     *
     * @param array<int,string> $columns Columnas ya validadas contra la tabla.
     */
    public static function fingerprint_sql(array $columns): string
    {
        $parts = [];
        foreach ($columns as $col) {
            $val     = "CAST(`{$col}` AS CHAR)";
            $parts[] = "IF(`{$col}` IS NULL, 'N', CONCAT('V', CHAR_LENGTH({$val}), ':', {$val}))";
        }
        return 'SHA2(CONCAT_WS(\'|\', ' . implode(', ', $parts) . '), 256)';
    }

    /**
     * Condición "todas las columnas del criterio tienen valor" (ni NULL ni vacío).
     * Evita que filas con datos incompletos se tomen por duplicadas entre sí.
     */
    public static function non_empty_sql(array $columns): string
    {
        return implode(' AND ', array_map(
            static fn($c) => "(`{$c}` IS NOT NULL AND CAST(`{$c}` AS CHAR) <> '')",
            $columns
        ));
    }

    /**
     * IDs a eliminar a partir de los grupos duplicados: todos menos el que se
     * conserva (mayor ID con 'max', menor con 'min').
     *
     * @param array<int,array{ids:string}> $groups Filas con 'ids' = lista CSV ordenada de PKs.
     * @return array<int,string>
     */
    public static function ids_to_delete(array $groups, string $keep): array
    {
        $delete = [];
        foreach ($groups as $g) {
            $ids = array_values(array_filter(explode(',', (string) $g['ids']), static fn($v) => $v !== ''));
            if (count($ids) < 2) {
                continue;
            }
            // El orden viene de SQL (GROUP_CONCAT … ORDER BY pk), el mismo que usa el análisis.
            $keep === 'min' ? array_shift($ids) : array_pop($ids);
            foreach ($ids as $id) {
                $delete[] = $id;
            }
        }
        return $delete;
    }

    /**
     * Columnas excluidas por defecto del criterio "filas idénticas": la clave
     * primaria y las marcas de tiempo automáticas (difieren en cada importación).
     *
     * @param array<string,array{type:string,default:?string,extra:string}> $columns
     * @return array<int,string>
     */
    public static function auto_columns(array $columns, string $pk): array
    {
        $auto = [$pk];
        foreach ($columns as $name => $meta) {
            if (preg_match('/current_timestamp/i', (string) ($meta['default'] ?? '')) || preg_match('/on update/i', (string) ($meta['extra'] ?? ''))) {
                $auto[] = $name;
            }
        }
        return array_values(array_unique($auto));
    }

    // ── Tablas permitidas ──────────────────────────────────────

    /**
     * Tablas depurables: contratos SECOP, tablas Sysman y dat_*, solo si son
     * tablas base (no vistas) con clave primaria de una sola columna.
     *
     * @return array<string,array{label:string,pk:string,columns:array<string,array{type:string,default:?string,extra:string}>}>
     */
    public function tables(): array
    {
        global $wpdb;
        $candidates = [
            $this->db->get_table_name()                   => __('Contratos SECOP', 'secop-suite'),
            $wpdb->prefix . 'sysman_auxiliar_cuentas'     => __('Sysman — Auxiliar de cuentas (asientos presupuestales)', 'secop-suite'),
            $wpdb->prefix . 'sysman_plan_presupuestal'    => __('Sysman — Plan presupuestal (rubros)', 'secop-suite'),
        ];
        $dat = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'dat_') . '%')) ?: [];
        foreach ($dat as $t) {
            $candidates[$t] = $t;
        }

        $tables = [];
        foreach ($candidates as $table => $label) {
            $type = $wpdb->get_row($wpdb->prepare('SHOW FULL TABLES LIKE %s', $wpdb->esc_like($table)), ARRAY_N);
            if (!$type || ($type[1] ?? '') !== 'BASE TABLE') {
                continue;
            }
            $columns = $this->describe($table);
            $pk      = $this->primary_key($columns);
            if ($pk === null) {
                continue;
            }
            $tables[$table] = ['label' => $label, 'pk' => $pk, 'columns' => $columns];
        }
        return $tables;
    }

    /** @return array<string,array{type:string,default:?string,extra:string,key:string}> */
    private function describe(string $table): array
    {
        global $wpdb;
        $columns = [];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla de la lista blanca
        foreach ((array) $wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A) as $row) {
            $columns[$row['Field']] = [
                'type'    => (string) $row['Type'],
                'default' => $row['Default'] === null ? null : (string) $row['Default'],
                'extra'   => (string) $row['Extra'],
                'key'     => (string) $row['Key'],
            ];
        }
        return $columns;
    }

    private function primary_key(array $columns): ?string
    {
        $pri = array_keys(array_filter($columns, static fn($c) => $c['key'] === 'PRI'));
        return count($pri) === 1 ? $pri[0] : null;
    }

    /**
     * Criterios predefinidos por tabla (solo con columnas existentes).
     *
     * @return array<string,array{label:string,columns:array<int,string>,skip_empty:bool}>
     */
    public function presets(string $table, array $meta): array
    {
        global $wpdb;
        $cols  = $meta['columns'];
        $exact = array_values(array_diff(array_keys($cols), self::auto_columns($cols, $meta['pk'])));

        $presets = [
            'exacto' => [
                'label'      => __('Filas idénticas (todas las columnas, salvo el ID y las fechas automáticas de importación)', 'secop-suite'),
                'columns'    => $exact,
                'skip_empty' => false,
            ],
        ];

        $candidates = [];
        if ($table === $this->db->get_table_name()) {
            $candidates['contrato_repetido'] = [
                __('Mismo contrato registrado con dos números: igual proceso, contratista, valor y fecha de firma', 'secop-suite'),
                ['numero_de_proceso', 'nom_raz_social_contratista', 'valor_contrato', 'fecha_de_firma_del_contrato'],
            ];
        } elseif ($table === $wpdb->prefix . 'sysman_auxiliar_cuentas') {
            $candidates['asiento_repetido'] = [
                __('Mismo asiento: igual documento, tipo, número de comprobante, rubro, fecha y valores', 'secop-suite'),
                ['nrodocumento', 'tipocpte', 'numero', 'rubro', 'fecha', 'valordebito', 'valorcredito'],
            ];
        } elseif ($table === $wpdb->prefix . 'sysman_plan_presupuestal') {
            $rubro = ['codigo'];
            foreach (['anio', 'vigencia'] as $year_col) {
                if (isset($cols[$year_col])) {
                    $rubro[] = $year_col;
                }
            }
            $candidates['rubro_repetido'] = [
                __('Mismo código de rubro (un rubro repetido multiplica las filas de la vista de consulta)', 'secop-suite'),
                $rubro,
            ];
        }

        foreach ($candidates as $key => [$label, $columns]) {
            if (count(array_intersect($columns, array_keys($cols))) === count($columns)) {
                $presets[$key] = ['label' => $label, 'columns' => $columns, 'skip_empty' => true];
            }
        }
        return $presets;
    }

    /** Criterio validado (columnas reales, sin la PK) o null. */
    private function validate_criteria(array $meta, $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $criteria = [];
        foreach ($raw as $col) {
            $col = sanitize_text_field(wp_unslash((string) $col));
            if (isset($meta['columns'][$col]) && $col !== $meta['pk'] && !in_array($col, $criteria, true)) {
                $criteria[] = $col;
            }
        }
        return ($criteria && count($criteria) <= self::MAX_CRITERIA) ? $criteria : null;
    }

    // ── Detección ──────────────────────────────────────────────

    /**
     * Grupos de filas duplicadas: huella, cantidad y lista CSV de PKs.
     *
     * @return array<int,array{k:string,n:string,ids:string}>
     */
    private function find_groups(string $table, string $pk, array $criteria, bool $skip_empty): array
    {
        global $wpdb;
        $wpdb->query('SET SESSION group_concat_max_len = 16777216');
        $fp    = self::fingerprint_sql($criteria);
        $where = $skip_empty ? ' WHERE ' . self::non_empty_sql($criteria) : '';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identificadores validados
        $sql = "SELECT k, COUNT(*) AS n, GROUP_CONCAT(`{$pk}` ORDER BY `{$pk}` SEPARATOR ',') AS ids
                FROM (SELECT `{$pk}`, {$fp} AS k FROM `{$table}`{$where}) x
                GROUP BY k HAVING COUNT(*) > 1 ORDER BY n DESC";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if ($rows === null && $wpdb->last_error !== '') {
            throw new \RuntimeException($wpdb->last_error);
        }
        return $rows ?: [];
    }

    /** Resumen del análisis con una muestra de grupos para revisión humana. */
    public function analyze(string $table, array $meta, array $criteria, string $keep, bool $skip_empty = false): array
    {
        global $wpdb;
        $pk     = $meta['pk'];
        $groups = $this->find_groups($table, $pk, $criteria, $skip_empty);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

        $redundant = 0;
        foreach ($groups as $g) {
            $redundant += (int) $g['n'] - 1;
        }

        $sample      = [];
        $sample_rows = array_slice($groups, 0, self::SAMPLE_GROUPS);
        $keep_ids    = [];
        foreach ($sample_rows as $g) {
            $ids        = explode(',', $g['ids']);
            $kept       = $keep === 'min' ? $ids[0] : $ids[count($ids) - 1];
            $keep_ids[] = $kept;
            $sample[]   = [
                'count'   => (int) $g['n'],
                'keep'    => $kept,
                'delete'  => array_slice(array_values(array_diff($ids, [$kept])), 0, 10),
                'values'  => [],
            ];
        }

        if ($keep_ids) {
            $show   = array_slice($criteria, 0, 8);
            $cols   = implode(', ', array_map(static fn($c) => "`{$c}`", array_merge([$pk], $show)));
            $in     = implode(', ', array_fill(0, count($keep_ids), '%s'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows   = $wpdb->get_results($wpdb->prepare("SELECT {$cols} FROM `{$table}` WHERE `{$pk}` IN ({$in})", $keep_ids), ARRAY_A) ?: [];
            $by_id  = [];
            foreach ($rows as $r) {
                $by_id[(string) $r[$pk]] = $r;
            }
            foreach ($sample as &$s) {
                $row = $by_id[(string) $s['keep']] ?? [];
                foreach ($show as $c) {
                    $v = $row[$c] ?? null;
                    $s['values'][$c] = $v === null ? null : mb_substr((string) $v, 0, 120);
                }
            }
            unset($s);
        }

        return [
            'table'     => $table,
            'criteria'  => $criteria,
            'keep'      => $keep,
            'skip_empty'=> $skip_empty,
            'total'     => $total,
            'groups'    => count($groups),
            'redundant' => $redundant,
            'max_run'   => self::MAX_DELETE,
            'sample'    => $sample,
        ];
    }

    // ── Eliminación con respaldo ───────────────────────────────

    public function backup_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'secop_dedup_backup';
    }

    private function ensure_backup_table(): void
    {
        if (get_option(SECOP_SUITE_PREFIX . 'dedup_backup_version') === self::BACKUP_VERSION) {
            return;
        }
        global $wpdb;
        $table   = $this->backup_table();
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_id VARCHAR(32) NOT NULL,
            table_name VARCHAR(191) NOT NULL,
            row_pk VARCHAR(191) NOT NULL,
            row_data LONGTEXT NOT NULL,
            criteria TEXT NOT NULL,
            deleted_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            deleted_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_batch (batch_id),
            KEY idx_table (table_name)
        ) {$charset};");
        update_option(SECOP_SUITE_PREFIX . 'dedup_backup_version', self::BACKUP_VERSION, false);
    }

    /**
     * Elimina los duplicados (máx. MAX_DELETE por ejecución) guardando cada fila
     * en el respaldo antes de borrarla. Devuelve el resumen del lote.
     */
    public function run(string $table, array $meta, array $criteria, string $keep, bool $skip_empty = false): array
    {
        global $wpdb;
        $this->ensure_backup_table();
        @set_time_limit(300);

        $pk        = $meta['pk'];
        $all       = self::ids_to_delete($this->find_groups($table, $pk, $criteria, $skip_empty), $keep);
        $pending   = count($all);
        $ids       = array_slice($all, 0, self::MAX_DELETE);
        $batch_id  = str_replace('-', '', wp_generate_uuid4());
        $backup    = $this->backup_table();
        $user      = get_current_user_id();
        $now       = current_time('mysql');
        $crit_json = (string) wp_json_encode(['columns' => $criteria, 'keep' => $keep, 'skip_empty' => $skip_empty]);
        $deleted   = 0;

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $in = implode(', ', array_fill(0, count($chunk), '%s'));
            $wpdb->query('START TRANSACTION');

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` WHERE `{$pk}` IN ({$in})", $chunk), ARRAY_A) ?: [];
            if (!$rows) {
                $wpdb->query('COMMIT');
                continue;
            }

            $values = [];
            $place  = [];
            foreach ($rows as $row) {
                $json = wp_json_encode($row);
                if (!is_string($json) || $json === '') {
                    // Nunca borrar una fila cuyo respaldo no se pudo serializar.
                    $wpdb->query('ROLLBACK');
                    return $this->run_result($batch_id, $deleted, $pending, sprintf(__('La fila %s no se pudo respaldar (datos no UTF-8); depuración detenida sin eliminarla.', 'secop-suite'), (string) $row[$pk]));
                }
                $place[] = '(%s, %s, %s, %s, %s, %d, %s)';
                array_push($values, $batch_id, $table, (string) $row[$pk], $json, $crit_json, $user, $now);
            }
            $ok_backup = $wpdb->query($wpdb->prepare(
                "INSERT INTO `{$backup}` (batch_id, table_name, row_pk, row_data, criteria, deleted_by, deleted_at) VALUES " . implode(', ', $place),
                $values
            ));
            if ($ok_backup === false) {
                $wpdb->query('ROLLBACK');
                return $this->run_result($batch_id, $deleted, $pending, __('No se pudo escribir el respaldo; no se eliminó nada de este lote.', 'secop-suite') . ' ' . $wpdb->last_error);
            }

            $backed = array_map(static fn($r) => (string) $r[$pk], $rows);
            $in2    = implode(', ', array_fill(0, count($backed), '%s'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $ok_delete = $wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE `{$pk}` IN ({$in2})", $backed));
            if ($ok_delete === false) {
                $error = $wpdb->last_error;
                $wpdb->query('ROLLBACK');
                // Compensación para tablas no transaccionales (MyISAM): el respaldo
                // de este lote no debe quedar si las filas siguen en la tabla.
                $wpdb->query($wpdb->prepare("DELETE FROM `{$backup}` WHERE batch_id = %s AND row_pk IN ({$in2})", array_merge([$batch_id], $backed)));
                return $this->run_result($batch_id, $deleted, $pending, __('Error al eliminar; el lote se revirtió.', 'secop-suite') . ' ' . $error);
            }

            $wpdb->query('COMMIT');
            $deleted += (int) $ok_delete;
        }

        $this->after_change($table);
        Logger::warning(sprintf(
            'DEPURACIÓN: %d filas duplicadas eliminadas de %s (criterio: %s; conserva %s; lote %s; usuario %d)',
            $deleted, $table, implode(',', $criteria), $keep, $batch_id, $user
        ));

        return $this->run_result($batch_id, $deleted, $pending, '');
    }

    private function run_result(string $batch_id, int $deleted, int $pending, string $error): array
    {
        return [
            'batch_id'  => $batch_id,
            'deleted'   => $deleted,
            'remaining' => max(0, $pending - $deleted),
            'error'     => $error,
        ];
    }

    /** Restaura un lote del respaldo. Las filas que ya no caben (PK/único ocupados) se conservan en el respaldo. */
    public function restore(string $batch_id): array
    {
        global $wpdb;
        $this->ensure_backup_table();
        $backup = $this->backup_table();
        $tables = $this->tables();
        $rows   = $wpdb->get_results($wpdb->prepare("SELECT id, table_name, row_data FROM `{$backup}` WHERE batch_id = %s ORDER BY id", $batch_id), ARRAY_A) ?: [];

        $restored = 0;
        $failed   = 0;
        $touched  = [];
        foreach ($rows as $r) {
            $table = (string) $r['table_name'];
            $data  = json_decode((string) $r['row_data'], true);
            if (!isset($tables[$table]) || !is_array($data)) {
                $failed++;
                continue;
            }
            $data = array_intersect_key($data, $tables[$table]['columns']);
            if ($wpdb->insert($table, $data) === false) {
                $failed++;
                continue;
            }
            $wpdb->delete($backup, ['id' => (int) $r['id']], ['%d']);
            $restored++;
            $touched[$table] = true;
        }

        foreach (array_keys($touched) as $table) {
            $this->after_change($table);
        }
        Logger::warning(sprintf('DEPURACIÓN: lote %s restaurado (%d filas, %d fallidas; usuario %d)', $batch_id, $restored, $failed, get_current_user_id()));
        return ['restored' => $restored, 'failed' => $failed];
    }

    public function purge(string $batch_id): int
    {
        global $wpdb;
        $this->ensure_backup_table();
        $n = (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$this->backup_table()}` WHERE batch_id = %s", $batch_id));
        Logger::info(sprintf('DEPURACIÓN: respaldo del lote %s eliminado (%d filas; usuario %d)', $batch_id, $n, get_current_user_id()));
        return $n;
    }

    /** @return array<int,array<string,mixed>> */
    public function batches(): array
    {
        global $wpdb;
        $backup = $this->backup_table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($backup))) !== $backup) {
            return [];
        }
        return $wpdb->get_results(
            "SELECT batch_id, table_name, COUNT(*) AS filas, MIN(deleted_at) AS fecha, MAX(criteria) AS criteria, MAX(deleted_by) AS usuario
             FROM `{$backup}` GROUP BY batch_id, table_name ORDER BY fecha DESC LIMIT 50",
            ARRAY_A
        ) ?: [];
    }

    private function after_change(string $table): void
    {
        Plugin::get_instance()->importer()->invalidate_chart_cache();
        if ($table === $this->db->get_table_name()) {
            update_option(SECOP_SUITE_PREFIX . 'total_records', $this->db->get_total_records());
        }
    }

    // ── Diagnóstico ────────────────────────────────────────────

    /** Indicadores baratos (sin huellas) para la cabecera de la página. */
    public function diagnostics(array $tables): array
    {
        global $wpdb;
        $secop = $this->db->get_table_name();
        $diag  = ['tables' => [], 'checks' => []];

        foreach ($tables as $table => $meta) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $diag['tables'][] = ['table' => $table, 'label' => $meta['label'], 'rows' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`")];
        }

        if (isset($tables[$secop])) {
            $has_unique = (bool) $wpdb->get_var("SHOW INDEX FROM `{$secop}` WHERE Key_name = 'unique_contract'");
            $dup_nums   = (int) $wpdb->get_var("SELECT COUNT(*) FROM (SELECT numero_del_contrato FROM `{$secop}` GROUP BY numero_del_contrato HAVING COUNT(*) > 1) t");
            $diag['unique_index'] = $has_unique;
            $diag['checks'][] = [
                'ok'    => $has_unique,
                'label' => __('Índice único por número de contrato', 'secop-suite'),
                'text'  => $has_unique
                    ? __('Presente: la tabla no puede tener dos contratos con el mismo número.', 'secop-suite')
                    : sprintf(__('Ausente. Números de contrato repetidos: %d. Depure con un criterio sobre numero_del_contrato y luego restaure el índice.', 'secop-suite'), $dup_nums),
            ];
            $diag['dup_numbers'] = $dup_nums;

            $shared = (int) $wpdb->get_var("SELECT COUNT(*) FROM (SELECT numero_de_proceso FROM `{$secop}` WHERE numero_de_proceso IS NOT NULL AND numero_de_proceso <> '' GROUP BY numero_de_proceso HAVING COUNT(*) > 1) t");
            $diag['checks'][] = [
                'ok'    => $shared === 0,
                'label' => __('Procesos con varios contratos', 'secop-suite'),
                'text'  => sprintf(__('Procesos con más de un contrato: %d. En la vista de consulta los asientos de Sysman se cruzan por número de proceso, así que esos contratos comparten los mismos asientos. Si son el mismo contrato registrado dos veces, depure con el criterio "Mismo contrato registrado con dos números".', 'secop-suite'), $shared),
            ];
        }

        $plan = $wpdb->prefix . 'sysman_plan_presupuestal';
        if (isset($tables[$plan]['columns']['codigo'])) {
            $rep = (int) $wpdb->get_var("SELECT COUNT(*) FROM (SELECT codigo FROM `{$plan}` GROUP BY codigo HAVING COUNT(*) > 1) t");
            $diag['checks'][] = [
                'ok'    => $rep === 0,
                'label' => __('Rubros repetidos en el plan presupuestal', 'secop-suite'),
                'text'  => sprintf(__('Códigos de rubro que aparecen más de una vez: %d. Cada repetición multiplica las filas de la vista de consulta para los asientos de ese rubro.', 'secop-suite'), $rep),
            ];
        }

        if ($this->db->view_exists()) {
            $view = $this->db->get_view_name();
            $vr   = $wpdb->get_row("SELECT COUNT(*) AS filas, COUNT(DISTINCT numero_del_contrato) AS contratos FROM `{$view}`", ARRAY_A) ?: ['filas' => 0, 'contratos' => 0];
            $diag['checks'][] = [
                'ok'    => true,
                'label' => __('Vista de consulta (vigencia actual)', 'secop-suite'),
                'text'  => sprintf(__('Filas: %1$d · contratos distintos: %2$d. Es normal que un contrato tenga varias filas (una por asiento presupuestal); las APIs de Datos Abiertos ya entregan una fila por contrato y sin repetidos.', 'secop-suite'), (int) $vr['filas'], (int) $vr['contratos']),
            ];
        }
        return $diag;
    }

    /** Restaura el índice único por número de contrato si falta y no hay repetidos. */
    public function add_unique_index(): array
    {
        global $wpdb;
        $secop = $this->db->get_table_name();
        if ($wpdb->get_var("SHOW INDEX FROM `{$secop}` WHERE Key_name = 'unique_contract'")) {
            return ['ok' => true, 'message' => __('El índice único ya existe.', 'secop-suite')];
        }
        $dups = (int) $wpdb->get_var("SELECT COUNT(*) FROM (SELECT numero_del_contrato FROM `{$secop}` GROUP BY numero_del_contrato HAVING COUNT(*) > 1) t");
        if ($dups > 0) {
            return ['ok' => false, 'message' => sprintf(__('Números de contrato repetidos: %d. Depúrelos antes de crear el índice.', 'secop-suite'), $dups)];
        }
        $ok = $wpdb->query("ALTER TABLE `{$secop}` ADD UNIQUE KEY unique_contract (numero_del_contrato)");
        Logger::warning('DEPURACIÓN: índice único unique_contract ' . ($ok === false ? 'NO creado: ' . $wpdb->last_error : 'creado'));
        return $ok === false
            ? ['ok' => false, 'message' => __('No se pudo crear el índice: ', 'secop-suite') . $wpdb->last_error]
            : ['ok' => true, 'message' => __('Índice único creado.', 'secop-suite')];
    }

    // ── Admin: menú, assets y página ───────────────────────────

    public function register_menu(): void
    {
        add_submenu_page(
            'secop-suite',
            __('Depuración BD', 'secop-suite'),
            __('Depuración BD', 'secop-suite'),
            'manage_options',
            self::PAGE,
            [$this, 'render_page']
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if (!str_contains($hook, self::PAGE)) {
            return;
        }
        wp_enqueue_script('secop-suite-dedup', SECOP_SUITE_URL . 'assets/js/admin-depuracion.js', ['jquery'], SECOP_SUITE_VERSION, true);

        $tables = [];
        foreach ($this->tables() as $table => $meta) {
            $cols = [];
            foreach ($meta['columns'] as $name => $c) {
                if ($name !== $meta['pk']) {
                    $cols[] = ['name' => $name, 'type' => $c['type']];
                }
            }
            $tables[$table] = [
                'label'   => $meta['label'],
                'pk'      => $meta['pk'],
                'columns' => $cols,
                'presets' => $this->presets($table, $meta),
            ];
        }

        wp_localize_script('secop-suite-dedup', 'secopDedup', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
            'tables'  => $tables,
            'strings' => [
                'analyzing'  => __('Analizando…', 'secop-suite'),
                'deleting'   => __('Eliminando duplicados…', 'secop-suite'),
                'noDups'     => __('No se encontraron duplicados con este criterio.', 'secop-suite'),
                'confirmRun' => __('Se eliminarán las filas duplicadas (se conservará una por grupo y todo quedará respaldado). ¿Continuar?', 'secop-suite'),
                'confirmRestore' => __('¿Restaurar todas las filas de este lote?', 'secop-suite'),
                'confirmPurge'   => __('El respaldo de este lote se borrará definitivamente y ya no podrá restaurarse. ¿Continuar?', 'secop-suite'),
                'pickColumns'    => __('Seleccione al menos una columna para el criterio.', 'secop-suite'),
                'criterion'      => __('Criterio de duplicado', 'secop-suite'),
                'custom'         => __('Personalizado (elija las columnas abajo)', 'secop-suite'),
                'error'          => __('Error de comunicación con el servidor.', 'secop-suite'),
            ],
        ]);
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tiene permisos para acceder a esta página.', 'secop-suite'));
        }
        $tables  = $this->tables();
        $diag    = $this->diagnostics($tables);
        $batches = $this->batches();
        include SECOP_SUITE_DIR . 'templates/admin/depuracion-page.php';
    }

    // ── AJAX ───────────────────────────────────────────────────

    /** Verifica nonce + capacidad y resuelve la tabla/criterio de la petición. */
    private function request_context(bool $needs_criteria = true): array
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'secop-suite')], 403);
        }
        if (!$needs_criteria) {
            return [];
        }
        $tables = $this->tables();
        $table  = sanitize_text_field(wp_unslash($_POST['table'] ?? ''));
        if (!isset($tables[$table])) {
            wp_send_json_error(['message' => __('Tabla no permitida', 'secop-suite')]);
        }
        $criteria = $this->validate_criteria($tables[$table], $_POST['columns'] ?? null);
        if ($criteria === null) {
            wp_send_json_error(['message' => __('Criterio inválido: seleccione entre 1 y 40 columnas de la tabla.', 'secop-suite')]);
        }
        $keep       = ($_POST['keep'] ?? 'max') === 'min' ? 'min' : 'max';
        $skip_empty = ($_POST['skip_empty'] ?? '') === '1';
        return [$table, $tables[$table], $criteria, $keep, $skip_empty];
    }

    public function ajax_analyze(): void
    {
        [$table, $meta, $criteria, $keep, $skip_empty] = $this->request_context();
        try {
            wp_send_json_success($this->analyze($table, $meta, $criteria, $keep, $skip_empty));
        } catch (\RuntimeException $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_run(): void
    {
        [$table, $meta, $criteria, $keep, $skip_empty] = $this->request_context();
        if (($_POST['confirm'] ?? '') !== '1') {
            wp_send_json_error(['message' => __('Debe confirmar la eliminación.', 'secop-suite')]);
        }
        if (get_transient(self::LOCK)) {
            wp_send_json_error(['message' => __('Ya hay una depuración en curso. Intente en unos minutos.', 'secop-suite')]);
        }
        if (get_transient(SECOP_SUITE_PREFIX . 'import_running')) {
            wp_send_json_error(['message' => __('Hay una importación en curso; espere a que termine.', 'secop-suite')]);
        }
        set_transient(self::LOCK, 1, 10 * MINUTE_IN_SECONDS);
        try {
            $result = $this->run($table, $meta, $criteria, $keep, $skip_empty);
        } catch (\RuntimeException $e) {
            delete_transient(self::LOCK);
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        delete_transient(self::LOCK);
        $result['error'] === '' ? wp_send_json_success($result) : wp_send_json_error(['message' => $result['error']] + $result);
    }

    private function request_batch(): string
    {
        $this->request_context(false);
        $batch = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_POST['batch'] ?? '')));
        if (strlen((string) $batch) !== 32) {
            wp_send_json_error(['message' => __('Lote inválido', 'secop-suite')]);
        }
        return (string) $batch;
    }

    public function ajax_restore(): void
    {
        wp_send_json_success($this->restore($this->request_batch()));
    }

    public function ajax_purge(): void
    {
        wp_send_json_success(['purged' => $this->purge($this->request_batch())]);
    }

    public function ajax_unique_index(): void
    {
        $this->request_context(false);
        $r = $this->add_unique_index();
        $r['ok'] ? wp_send_json_success($r) : wp_send_json_error($r);
    }
}
