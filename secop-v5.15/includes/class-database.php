<?php
/**
 * Database — Gestión de la tabla de contratos y operaciones de datos.
 *
 * API: https://www.datos.gov.co/resource/rpmr-utcd.json
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Database
{
    private string $table_name;

    /** Campos de tipo fecha */
    private const DATE_FIELDS = [
        'fecha_de_firma_del_contrato',
        'fecha_inicio_ejecucion',
        'fecha_fin_ejecucion',
    ];

    /** Campos de tipo moneda / decimal */
    private const CURRENCY_FIELDS = [
        'valor_contrato',
    ];

    /** Campos enteros */
    private const INTEGER_FIELDS = [];

    /**
     * Mapeo API SECOP (rpmr-utcd) → columna de la BD.
     * Las claves con `_` al final en la API (ej. modalidad_de_contrataci_n)
     * representan caracteres especiales (ó) decodificados por Socrata.
     */
    private const FIELD_MAPPING = [
        'nivel_entidad'              => 'nivel_entidad',
        'codigo_entidad_en_secop'    => 'codigo_entidad_en_secop',
        'nombre_de_la_entidad'       => 'nombre_de_la_entidad',
        'nit_de_la_entidad'          => 'nit_de_la_entidad',
        'departamento_entidad'       => 'departamento_entidad',
        'municipio_entidad'          => 'municipio_entidad',
        'estado_del_proceso'         => 'estado_del_proceso',
        'modalidad_de_contrataci_n'  => 'modalidad_de_contratacion',
        'objeto_a_contratar'         => 'objeto_a_contratar',
        'objeto_del_proceso'         => 'objeto_del_proceso',
        'tipo_de_contrato'           => 'tipo_de_contrato',
        'fecha_de_firma_del_contrato'=> 'fecha_de_firma_del_contrato',
        'fecha_inicio_ejecuci_n'     => 'fecha_inicio_ejecucion',
        'fecha_fin_ejecuci_n'        => 'fecha_fin_ejecucion',
        'numero_del_contrato'        => 'numero_del_contrato',
        'numero_de_proceso'          => 'numero_de_proceso',
        'valor_contrato'             => 'valor_contrato',
        'nom_raz_social_contratista' => 'nom_raz_social_contratista',
        'url_contrato'               => 'url_contrato',
        'origen'                     => 'origen',
        'tipo_documento_proveedor'   => 'tipo_documento_proveedor',
        'documento_proveedor'        => 'documento_proveedor',
    ];

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'secop_contracts';
    }

    public function get_table_name(): string
    {
        return $this->table_name;
    }

    public function get_field_mapping(): array
    {
        return self::FIELD_MAPPING;
    }

    // ── Creación/migración de tabla ────────────────────────────
    public function create_table(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nivel_entidad VARCHAR(50) DEFAULT NULL,
            codigo_entidad_en_secop VARCHAR(50) DEFAULT NULL,
            nombre_de_la_entidad VARCHAR(255) DEFAULT NULL,
            nit_de_la_entidad VARCHAR(20) DEFAULT NULL,
            departamento_entidad VARCHAR(100) DEFAULT NULL,
            municipio_entidad VARCHAR(100) DEFAULT NULL,
            estado_del_proceso VARCHAR(50) DEFAULT NULL,
            modalidad_de_contratacion VARCHAR(100) DEFAULT NULL,
            objeto_a_contratar TEXT DEFAULT NULL,
            objeto_del_proceso TEXT DEFAULT NULL,
            tipo_de_contrato VARCHAR(100) DEFAULT NULL,
            fecha_de_firma_del_contrato DATETIME DEFAULT NULL,
            fecha_inicio_ejecucion DATETIME DEFAULT NULL,
            fecha_fin_ejecucion DATETIME DEFAULT NULL,
            numero_del_contrato VARCHAR(100) NOT NULL,
            numero_de_proceso VARCHAR(100) DEFAULT NULL,
            valor_contrato DECIMAL(20,2) DEFAULT 0,
            nom_raz_social_contratista VARCHAR(255) DEFAULT NULL,
            url_contrato VARCHAR(500) DEFAULT NULL,
            origen VARCHAR(50) DEFAULT NULL,
            tipo_documento_proveedor VARCHAR(50) DEFAULT NULL,
            documento_proveedor VARCHAR(50) DEFAULT NULL,
            fecha_importacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_contract (numero_del_contrato),
            KEY idx_nit_entidad (nit_de_la_entidad),
            KEY idx_estado_proceso (estado_del_proceso),
            KEY idx_fecha_firma (fecha_de_firma_del_contrato),
            KEY idx_proveedor (documento_proveedor),
            KEY idx_tipo_contrato (tipo_de_contrato),
            KEY idx_modalidad (modalidad_de_contratacion),
            KEY idx_origen (origen),
            KEY idx_nivel (nivel_entidad)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(SECOP_SUITE_PREFIX . 'db_version', SECOP_SUITE_DB_VERSION);
    }

    /**
     * Migración destructiva desde schema antiguo (pre-5.0.0) al nuevo.
     * Elimina la tabla antigua y crea la nueva.
     */
    public function migrate_to_new_schema(): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        $this->create_table();
        update_option(SECOP_SUITE_PREFIX . 'total_records', 0);
        delete_option(SECOP_SUITE_PREFIX . 'last_import');
        Logger::info("Migración v5.0.0 completada: tabla recreada con nuevo schema");
    }

    // ── Estadísticas ───────────────────────────────────────────
    public function get_total_records(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    public function get_total_value(): float
    {
        global $wpdb;
        return (float) $wpdb->get_var("SELECT COALESCE(SUM(valor_contrato), 0) FROM {$this->table_name}");
    }

    // ── Procesamiento de registros ─────────────────────────────
    public function process_record(array $item): ?string
    {
        global $wpdb;

        if (empty($item['numero_del_contrato'])) {
            return null;
        }

        $data    = [];
        $formats = [];

        foreach (self::FIELD_MAPPING as $api_field => $db_field) {
            $value = $this->extract_value($item, $api_field);
            if ($value !== null) {
                $data[$db_field]    = $this->sanitize_value($db_field, $value);
                $formats[]          = $this->get_format($db_field);
            }
        }

        if (empty($data)) {
            return null;
        }

        $columns      = array_keys($data);
        $placeholders = implode(', ', $formats);
        $col_list     = '`' . implode('`, `', $columns) . '`';

        $update_parts = [];
        foreach ($columns as $col) {
            if ($col !== 'numero_del_contrato') {
                $update_parts[] = "`{$col}` = VALUES(`{$col}`)";
            }
        }
        $update_sql = implode(', ', $update_parts);

        $sql = "INSERT INTO {$this->table_name} ({$col_list}) VALUES ({$placeholders})
                ON DUPLICATE KEY UPDATE {$update_sql}";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query($wpdb->prepare($sql, array_values($data)));

        if ($result === false) {
            return null;
        }

        return $result === 1 ? 'inserted' : 'updated';
    }

    private function extract_value(array $item, string $field): mixed
    {
        return $item[$field] ?? null;
    }

    public function sanitize_value(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            $ts = strtotime((string) $value);
            return $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
        }

        if (in_array($field, self::CURRENCY_FIELDS, true)) {
            return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
        }

        if (in_array($field, self::INTEGER_FIELDS, true)) {
            return (int) $value;
        }

        return sanitize_text_field((string) $value);
    }

    public function get_format(string $field): string
    {
        if (in_array($field, self::INTEGER_FIELDS, true)) {
            return '%d';
        }
        if (in_array($field, self::CURRENCY_FIELDS, true)) {
            return '%f';
        }
        return '%s';
    }

    // ── Validación de columnas ─────────────────────────────────
    public function get_table_columns(string $table): array
    {
        $cache_key = 'secop_cols_' . md5($table);
        $cached    = wp_cache_get($cache_key, 'secop_suite');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $columns = [];
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        foreach ($rows as $row) {
            $columns[$row['Field']] = $row['Type'];
        }

        wp_cache_set($cache_key, $columns, 'secop_suite', 300);
        return $columns;
    }

    public function is_valid_column(string $table, string $column): bool
    {
        $columns = $this->get_table_columns($table);
        return isset($columns[$column]);
    }

    public function get_available_tables(): array
    {
        $cached = wp_cache_get('secop_available_tables', 'secop_suite');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $tables = [];

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) === $this->table_name) {
            $tables[$this->table_name] = 'SECOP Contracts';
        }

        // Legacy tables (backwards compatibility)
        $legacy = $wpdb->prefix . 'wp_data_contracting';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $legacy)) === $legacy) {
            $tables[$legacy] = 'Data Contracting (Legacy)';
        }

        $custom = $wpdb->get_results(
            $wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'dat_%'),
            ARRAY_N
        );
        foreach ($custom as $row) {
            $tables[$row[0]] = str_replace($wpdb->prefix, '', $row[0]);
        }

        // VIEW del módulo de Contratación (vista_secop_sysman). Sin esto, el motor de
        // gráficas rechaza la tabla y devuelve vacío → las gráficas no renderizan.
        $view = $this->get_view_name();
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $view)) === $view) {
            $tables[$view] = str_replace($wpdb->prefix, '', $view);
        }

        wp_cache_set('secop_available_tables', $tables, 'secop_suite', 300);
        return $tables;
    }

    /** Nombre del VIEW del módulo de seguimiento (creado por el usuario). */
    public function get_view_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'vista_secop_sysman';
    }

    /** ¿Existen las tablas Sysman requeridas por el VIEW? */
    public function sysman_tables_exist(): bool
    {
        global $wpdb;
        foreach (['sysman_auxiliar_cuentas', 'sysman_plan_presupuestal'] as $t) {
            $full = $wpdb->prefix . $t;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) !== $full) {
                return false;
            }
        }
        return true;
    }

    /**
     * Crear/reemplazar el VIEW que cruza ejecución presupuestal y contratos.
     * Devuelve true si se creó; false si faltan tablas Sysman.
     */
    public function create_view(): bool
    {
        global $wpdb;
        if (!$this->sysman_tables_exist()) {
            Logger::log('VIEW no creado: faltan tablas Sysman (sysman_auxiliar_cuentas / sysman_plan_presupuestal)');
            return false;
        }
        $view = $this->get_view_name();
        $aux  = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        $plan = $wpdb->prefix . 'sysman_plan_presupuestal';
        $sec  = $this->table_name; // {prefix}secop_contracts

        // Identificadores desde whitelist/$wpdb->prefix → seguro interpolar.
        // Definición EXACTA de la vista creada por el usuario (ga_vista_secop_sysman),
        // para que una instalación nueva la reproduzca de forma idéntica.
        //
        // v5.9.0: base = secop_contracts con LEFT JOIN → TODOS los contratos aparecen.
        // Los contratos sin cruce Sysman tienen NULL en dependencia/tercero/valordebito/…
        // La vigencia ahora es YEAR(fecha_de_firma_del_contrato) (año de firma del
        // contrato), de modo que la vista se auto-renueva cada año con YEAR(CURDATE()).
        // Columnas del asiento renombradas: fecha→fecha_asiento, anio→anio_asiento,
        // mes→mes_asiento. Nuevas: rubro_codigo, rubro_nombre, idsecop/idauxiliar/idplan.
        $sql = "CREATE OR REPLACE VIEW `{$view}` AS
            SELECT sec.id AS idsecop, aux.id AS idauxiliar, plan.id AS idplan,
              sec.numero_del_contrato, sec.numero_de_proceso, sec.objeto_a_contratar, sec.tipo_de_contrato,
              sec.modalidad_de_contratacion, sec.fecha_de_firma_del_contrato, sec.fecha_inicio_ejecucion,
              sec.fecha_fin_ejecucion, sec.valor_contrato, sec.nom_raz_social_contratista, sec.documento_proveedor,
              sec.url_contrato, aux.tercero, aux.nombretercero, plan.dependencia, plan.nombredependencia,
              aux.numero, aux.valordebito, aux.valorcredito, aux.saldoporejecutaresp, aux.cmpteafectado,
              aux.fecha AS fecha_asiento, aux.anio AS anio_asiento, aux.mes AS mes_asiento,
              plan.codigo AS rubro_codigo, plan.nombre AS rubro_nombre
            FROM (`{$sec}` sec
              LEFT JOIN `{$aux}` aux ON (aux.nrodocumento = sec.numero_de_proceso AND aux.tipocpte = 'RES'))
              LEFT JOIN `{$plan}` plan ON (plan.codigo = aux.rubro)
            WHERE YEAR(sec.fecha_de_firma_del_contrato) = YEAR(CURDATE())";

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query($sql);
        if ($result === false) {
            Logger::log('Error al crear VIEW: ' . $wpdb->last_error);
            return false;
        }
        Logger::info("VIEW {$view} creado/actualizado");
        return true;
    }

    // ── Diagnóstico del VIEW ───────────────────────────────────

    /** ¿Existe el VIEW en la base de datos? (SHOW TABLES lo devuelve igual que tablas normales.) */
    public function view_exists(): bool
    {
        global $wpdb;
        $view = $this->get_view_name();
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $view)) === $view;
    }

    /**
     * Cuenta filas del VIEW, opcionalmente filtradas por año.
     * Devuelve -1 si el VIEW no existe (para distinguir de "0 filas").
     */
    public function count_view_rows(?int $anio = null): int
    {
        if (!$this->view_exists()) {
            return -1;
        }
        global $wpdb;
        $view = $this->get_view_name();
        if ($anio !== null) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$view}` WHERE YEAR(`fecha_de_firma_del_contrato`) = %d", $anio));
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$view}`");
    }

    /**
     * Devuelve un mapa [ anio => count ] para todas las filas del VIEW.
     * Si el VIEW no existe devuelve [].
     *
     * @return array<int,int>
     */
    public function rows_by_year(): array
    {
        if (!$this->view_exists()) {
            return [];
        }
        global $wpdb;
        $view = $this->get_view_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SELECT YEAR(`fecha_de_firma_del_contrato`) AS anio, COUNT(*) c FROM `{$view}` GROUP BY YEAR(`fecha_de_firma_del_contrato`) ORDER BY anio DESC", ARRAY_A);
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['anio']] = (int) $row['c'];
        }
        return $result;
    }

    /**
     * Cuenta el cruce de las 3 tablas subyacentes SIN filtro de vigencia,
     * para diagnosticar si el JOIN produce filas independientemente del VIEW.
     * Devuelve -1 si faltan las tablas Sysman.
     */
    public function count_raw_join(): int
    {
        if (!$this->sysman_tables_exist()) {
            return -1;
        }
        global $wpdb;
        $aux  = $wpdb->prefix . 'sysman_auxiliar_cuentas';
        $plan = $wpdb->prefix . 'sysman_plan_presupuestal';
        $sec  = $this->table_name;

        // Mismo cruce que la vista (LEFT JOIN desde secop_contracts): cuenta TODAS
        // las filas que produce la vista sin el filtro de vigencia.
        $sql = "SELECT COUNT(*) FROM (
            SELECT 1
            FROM `{$sec}` sec
            LEFT JOIN `{$aux}` aux  ON (aux.nrodocumento = sec.numero_de_proceso AND aux.tipocpte = 'RES')
            LEFT JOIN `{$plan}` plan ON (plan.codigo = aux.rubro)
        ) t";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var($sql);
    }
}
