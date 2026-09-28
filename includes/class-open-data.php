<?php
/**
 * Open_Data — Esquemas y diccionario de datos de las APIs de Datos Abiertos.
 *
 * Fuente única de verdad de los campos que publica cada API: la REST (Rest_Api)
 * construye sus consultas con estas definiciones y el shortcode
 * [secop_diccionario] / el endpoint /diccionario las documentan, de modo que la
 * documentación y la respuesta real no pueden divergir.
 *
 * Deduplicación de /consulta (v5.17.0): el VIEW vista_secop_sysman cruza cada
 * contrato con sus asientos presupuestales (LEFT JOIN), así que un contrato con
 * N asientos aparece N veces. Las APIs parten de un conjunto DISTINCT de filas
 * de detalle (sin los ids internos de cada tabla, para que las filas repetidas
 * por reimportaciones de Sysman colapsen) y, por defecto, agrupan por contrato.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Open_Data
{
    /**
     * Datos personales (Ley 1581): nunca se exponen, filtran ni ordenan en APIs
     * públicas. `tercero` es la identificación del tercero en Sysman (NIT/cédula).
     */
    public const PII_COLS = ['documento_proveedor', 'tipo_documento_proveedor', 'tercero'];

    /** Ids internos del VIEW (uno por tabla cruzada): se excluyen para que DISTINCT colapse filas repetidas. */
    public const SURROGATE_COLS = ['idsecop', 'idauxiliar', 'idplan'];

    /** Agrupaciones admitidas por /consulta. */
    public const GROUPINGS = ['contrato', 'detalle'];

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        add_action('rest_api_init', [$this, 'register_routes']);
        add_shortcode('secop_diccionario', [$this, 'render_shortcode']);
    }

    // ── Esquema de /consulta agrupado por contrato ─────────────

    /**
     * Campos de /consulta con agrupar=contrato (uno por contrato).
     * Cada campo: sql (expresión sobre la tabla derivada `d`), requires (columnas
     * del VIEW que necesita), type (tipo publicado) y desc.
     *
     * @return array<string,array{sql:string,requires:array<int,string>,type:string,desc:string}>
     */
    public static function contract_fields(): array
    {
        $label_dep = "COALESCE(GROUP_CONCAT(DISTINCT NULLIF(d.`nombredependencia`, '') ORDER BY d.`nombredependencia` SEPARATOR ' | '), 'No Registra SYSMAN')";
        $label_ter = "COALESCE(GROUP_CONCAT(DISTINCT NULLIF(d.`nombretercero`, '') ORDER BY d.`nombretercero` SEPARATOR ' | '), MAX(NULLIF(d.`nom_raz_social_contratista`, '')), 'No Registra SYSMAN')";
        $rubros    = "GROUP_CONCAT(DISTINCT NULLIF(CONCAT_WS(' - ', d.`rubro_codigo`, d.`rubro_nombre`), '') ORDER BY d.`rubro_codigo` SEPARATOR ' | ')";
        $asientos  = 'SUM(CASE WHEN d.`numero` IS NOT NULL OR d.`fecha_asiento` IS NOT NULL OR d.`valordebito` IS NOT NULL THEN 1 ELSE 0 END)';

        $max = static fn(string $c): string => "MAX(d.`{$c}`)";

        return [
            'numero_del_contrato'        => ['sql' => 'd.`numero_del_contrato`', 'requires' => ['numero_del_contrato'], 'type' => 'texto',   'desc' => __('Número del contrato en SECOP. Identificador único de cada fila en esta agrupación.', 'secop-suite')],
            'numero_de_proceso'          => ['sql' => $max('numero_de_proceso'),          'requires' => ['numero_de_proceso'],          'type' => 'texto',   'desc' => __('Número del proceso de contratación en SECOP.', 'secop-suite')],
            'objeto_a_contratar'         => ['sql' => $max('objeto_a_contratar'),         'requires' => ['objeto_a_contratar'],         'type' => 'texto',   'desc' => __('Descripción del objeto del contrato.', 'secop-suite')],
            'tipo_de_contrato'           => ['sql' => $max('tipo_de_contrato'),           'requires' => ['tipo_de_contrato'],           'type' => 'texto',   'desc' => __('Tipo de contrato (prestación de servicios, obra, suministro…).', 'secop-suite')],
            'modalidad_de_contratacion'  => ['sql' => $max('modalidad_de_contratacion'),  'requires' => ['modalidad_de_contratacion'],  'type' => 'texto',   'desc' => __('Modalidad de selección (contratación directa, licitación pública, mínima cuantía…).', 'secop-suite')],
            'fecha_de_firma_del_contrato'=> ['sql' => $max('fecha_de_firma_del_contrato'),'requires' => ['fecha_de_firma_del_contrato'],'type' => 'fecha_hora', 'desc' => __('Fecha de firma del contrato. Su año define la vigencia.', 'secop-suite')],
            'fecha_inicio_ejecucion'     => ['sql' => $max('fecha_inicio_ejecucion'),     'requires' => ['fecha_inicio_ejecucion'],     'type' => 'fecha_hora', 'desc' => __('Fecha de inicio de ejecución del contrato.', 'secop-suite')],
            'fecha_fin_ejecucion'        => ['sql' => $max('fecha_fin_ejecucion'),        'requires' => ['fecha_fin_ejecucion'],        'type' => 'fecha_hora', 'desc' => __('Fecha de terminación de ejecución del contrato.', 'secop-suite')],
            'nom_raz_social_contratista' => ['sql' => $max('nom_raz_social_contratista'), 'requires' => ['nom_raz_social_contratista'], 'type' => 'texto',   'desc' => __('Nombre o razón social del contratista según SECOP.', 'secop-suite')],
            'url_contrato'               => ['sql' => $max('url_contrato'),               'requires' => ['url_contrato'],               'type' => 'url',     'desc' => __('Enlace al contrato en la plataforma SECOP.', 'secop-suite')],
            'nombredependencia'          => ['sql' => $label_dep, 'requires' => ['nombredependencia'], 'type' => 'texto', 'desc' => __('Dependencia(s) que ejecutan el contrato según Sysman, separadas por " | ". "No Registra SYSMAN" si el contrato no cruza con la ejecución presupuestal.', 'secop-suite')],
            'nombretercero'              => ['sql' => $label_ter, 'requires' => ['nombretercero', 'nom_raz_social_contratista'], 'type' => 'texto', 'desc' => __('Tercero (contratista) registrado en Sysman; si no hay cruce, el contratista del SECOP.', 'secop-suite')],
            'rubros'                     => ['sql' => $rubros, 'requires' => ['rubro_codigo', 'rubro_nombre'], 'type' => 'texto', 'desc' => __('Rubros presupuestales afectados ("código - nombre"), separados por " | ".', 'secop-suite')],
            'valordebito'                => ['sql' => 'SUM(d.`valordebito`)',         'requires' => ['valordebito'],         'type' => 'decimal', 'desc' => __('Suma del valor débito (ejecutado) de los asientos presupuestales del contrato. Vacío si no cruza con Sysman.', 'secop-suite')],
            'valorcredito'               => ['sql' => 'SUM(d.`valorcredito`)',        'requires' => ['valorcredito'],        'type' => 'decimal', 'desc' => __('Suma del valor crédito de los asientos presupuestales del contrato.', 'secop-suite')],
            'saldoporejecutaresp'        => ['sql' => 'SUM(d.`saldoporejecutaresp`)', 'requires' => ['saldoporejecutaresp'], 'type' => 'decimal', 'desc' => __('Suma del saldo por ejecutar de los asientos presupuestales del contrato.', 'secop-suite')],
            'valor_contrato'             => ['sql' => $max('valor_contrato'),         'requires' => ['valor_contrato'],      'type' => 'decimal', 'desc' => __('Valor total del contrato según SECOP (una sola vez por contrato).', 'secop-suite')],
            'valor_efectivo'             => ['sql' => 'SUM(COALESCE(d.`valordebito`, d.`valor_contrato`))', 'requires' => ['valordebito', 'valor_contrato'], 'type' => 'decimal', 'desc' => __('Valor efectivo del contrato: lo ejecutado en Sysman (valor débito) o, si no hay cruce, el valor del contrato. Es la cifra que usan las gráficas del módulo de Contratación.', 'secop-suite')],
            'registros_presupuestales'   => ['sql' => $asientos, 'requires' => ['numero', 'fecha_asiento', 'valordebito'], 'type' => 'entero', 'desc' => __('Número de asientos presupuestales (distintos) asociados al contrato.', 'secop-suite')],
            'anio'                       => ['sql' => 'YEAR(MAX(d.`fecha_de_firma_del_contrato`))', 'requires' => ['fecha_de_firma_del_contrato'], 'type' => 'entero', 'desc' => __('Vigencia: año de firma del contrato.', 'secop-suite')],
            'mes'                        => ['sql' => $max('mes_asiento'), 'requires' => ['mes_asiento'], 'type' => 'entero', 'desc' => __('Último mes (1-12) con asiento presupuestal registrado en Sysman.', 'secop-suite')],
        ];
    }

    /**
     * Campos de contrato disponibles según las columnas reales del VIEW.
     *
     * @param array<string,string> $view_columns [columna => tipo MySQL]
     * @return array<string,array{sql:string,requires:array<int,string>,type:string,desc:string}>
     */
    public static function available_contract_fields(array $view_columns): array
    {
        return array_filter(
            self::contract_fields(),
            static function (array $f) use ($view_columns): bool {
                foreach ($f['requires'] as $col) {
                    if (!isset($view_columns[$col])) {
                        return false;
                    }
                }
                return true;
            }
        );
    }

    /**
     * Columnas publicadas en la agrupación por detalle: todas las del VIEW menos
     * los ids internos y los datos personales.
     *
     * @param array<string,string> $view_columns [columna => tipo MySQL]
     * @return array<int,string>
     */
    public static function detail_columns(array $view_columns): array
    {
        return array_values(array_diff(
            array_keys($view_columns),
            self::SURROGATE_COLS,
            self::PII_COLS
        ));
    }

    /**
     * SQL (sin ORDER/LIMIT) de /consulta para la agrupación indicada.
     *
     * Parte de un conjunto DISTINCT de filas de detalle de la vigencia (sin ids
     * internos ni PII); con agrupar=contrato se agrega una fila por contrato.
     * Los fragmentos $filters ya vienen validados (columnas reales) y usan
     * placeholders cuyos valores aporta el llamador.
     *
     * @param array<string,string> $view_columns
     * @param array<int,string>    $filters Fragmentos WHERE con placeholders.
     * @return array{select:string,count:string,columns:array<int,string>}
     */
    public static function consulta_sql(string $view, array $view_columns, string $grouping, array $filters): array
    {
        $detail = self::detail_columns($view_columns);
        $where  = 'YEAR(`fecha_de_firma_del_contrato`) = %d';
        if ($filters) {
            $where .= ' AND ' . implode(' AND ', $filters);
        }
        $cols  = implode(', ', array_map(static fn($c) => "`{$c}`", $detail));
        $inner = "SELECT DISTINCT {$cols} FROM `{$view}` WHERE {$where}";

        if ($grouping === 'detalle') {
            return [
                'select'  => $inner,
                'count'   => "SELECT COUNT(*) FROM ({$inner}) d",
                'columns' => $detail,
            ];
        }

        $fields = self::available_contract_fields($view_columns);
        $parts  = [];
        foreach ($fields as $name => $f) {
            $parts[] = "{$f['sql']} AS `{$name}`";
        }
        return [
            'select'  => 'SELECT ' . implode(', ', $parts) . " FROM ({$inner}) d GROUP BY d.`numero_del_contrato`",
            'count'   => "SELECT COUNT(DISTINCT d.`numero_del_contrato`) FROM ({$inner}) d",
            'columns' => array_keys($fields),
        ];
    }

    /**
     * ORDER BY determinista para /consulta. Solo admite columnas publicadas en la
     * agrupación; los desempates garantizan un orden total para que la
     * paginación y las descargas por lotes no repitan ni omitan filas.
     *
     * @param array<int,string> $columns Columnas publicadas en la agrupación.
     */
    public static function consulta_order(array $columns, string $grouping, string $order_by, string $order, string $default_col): string
    {
        $dir = in_array(strtoupper($order), ['ASC', 'DESC'], true) ? strtoupper($order) : 'DESC';
        $col = ($order_by !== '' && in_array($order_by, $columns, true)) ? $order_by : $default_col;
        if (!in_array($col, $columns, true)) {
            $col = $columns[0] ?? 'numero_del_contrato';
        }

        $parts = ["`{$col}` {$dir}"];
        // Por contrato: numero_del_contrato es único por fila → orden total.
        // Por detalle: las filas son DISTINCT sobre todas las columnas → desempatar por todas.
        $ties = $grouping === 'detalle' ? $columns : ['numero_del_contrato'];
        foreach ($ties as $tie) {
            if ($tie !== $col && in_array($tie, $columns, true)) {
                $parts[] = "`{$tie}` ASC";
            }
        }
        return 'ORDER BY ' . implode(', ', $parts);
    }

    // ── Diccionario de datos ───────────────────────────────────

    /** Tipo publicado a partir del tipo MySQL de la columna. */
    public static function type_from_mysql(string $mysql_type): string
    {
        $t = strtolower($mysql_type);
        return match (true) {
            str_starts_with($t, 'decimal'), str_starts_with($t, 'float'), str_starts_with($t, 'double') => 'decimal',
            (bool) preg_match('/^(tiny|small|medium|big)?int/', $t)                                       => 'entero',
            str_starts_with($t, 'datetime'), str_starts_with($t, 'timestamp')                             => 'fecha_hora',
            str_starts_with($t, 'date')                                                                   => 'fecha',
            default                                                                                       => 'texto',
        };
    }

    /** Etiqueta legible de cada tipo publicado. */
    public static function type_labels(): array
    {
        return [
            'texto'      => __('Texto', 'secop-suite'),
            'url'        => __('Texto (URL)', 'secop-suite'),
            'decimal'    => __('Número decimal (pesos colombianos)', 'secop-suite'),
            'entero'     => __('Número entero', 'secop-suite'),
            'fecha_hora' => __('Fecha y hora (AAAA-MM-DD HH:MM:SS)', 'secop-suite'),
            'fecha'      => __('Fecha (AAAA-MM-DD)', 'secop-suite'),
        ];
    }

    /** Descripción de las columnas de la tabla de contratos y del VIEW. */
    public static function column_docs(): array
    {
        return [
            'id'                          => __('Identificador interno del registro en la base de datos del portal.', 'secop-suite'),
            'nivel_entidad'               => __('Nivel de la entidad contratante (territorial, nacional).', 'secop-suite'),
            'codigo_entidad_en_secop'     => __('Código de la entidad en SECOP.', 'secop-suite'),
            'nombre_de_la_entidad'        => __('Nombre de la entidad contratante.', 'secop-suite'),
            'nit_de_la_entidad'           => __('NIT de la entidad contratante.', 'secop-suite'),
            'departamento_entidad'        => __('Departamento de la entidad contratante.', 'secop-suite'),
            'municipio_entidad'           => __('Municipio de la entidad contratante.', 'secop-suite'),
            'estado_del_proceso'          => __('Estado del proceso/contrato en SECOP.', 'secop-suite'),
            'modalidad_de_contratacion'   => __('Modalidad de selección (contratación directa, licitación pública, mínima cuantía…).', 'secop-suite'),
            'objeto_a_contratar'          => __('Descripción del objeto del contrato.', 'secop-suite'),
            'objeto_del_proceso'          => __('Descripción del objeto del proceso de contratación.', 'secop-suite'),
            'tipo_de_contrato'            => __('Tipo de contrato (prestación de servicios, obra, suministro…).', 'secop-suite'),
            'fecha_de_firma_del_contrato' => __('Fecha de firma del contrato. Su año define la vigencia.', 'secop-suite'),
            'fecha_inicio_ejecucion'      => __('Fecha de inicio de ejecución del contrato.', 'secop-suite'),
            'fecha_fin_ejecucion'         => __('Fecha de terminación de ejecución del contrato.', 'secop-suite'),
            'numero_del_contrato'         => __('Número del contrato en SECOP.', 'secop-suite'),
            'numero_de_proceso'           => __('Número del proceso de contratación en SECOP.', 'secop-suite'),
            'valor_contrato'              => __('Valor total del contrato según SECOP.', 'secop-suite'),
            'nom_raz_social_contratista'  => __('Nombre o razón social del contratista según SECOP.', 'secop-suite'),
            'url_contrato'                => __('Enlace al contrato en la plataforma SECOP.', 'secop-suite'),
            'origen'                      => __('Plataforma de origen del registro (SECOP I / SECOP II).', 'secop-suite'),
            'fecha_importacion'           => __('Fecha en que el registro se importó al portal.', 'secop-suite'),
            'fecha_modificacion'          => __('Última actualización del registro en el portal.', 'secop-suite'),
            'nombretercero'               => __('Nombre del tercero (contratista) registrado en Sysman.', 'secop-suite'),
            'dependencia'                 => __('Código de la dependencia responsable del rubro en Sysman.', 'secop-suite'),
            'nombredependencia'           => __('Nombre de la dependencia responsable del rubro en Sysman.', 'secop-suite'),
            'numero'                      => __('Número del comprobante (asiento) presupuestal en Sysman.', 'secop-suite'),
            'valordebito'                 => __('Valor débito (ejecutado) del asiento presupuestal.', 'secop-suite'),
            'valorcredito'                => __('Valor crédito del asiento presupuestal.', 'secop-suite'),
            'saldoporejecutaresp'         => __('Saldo por ejecutar del asiento presupuestal.', 'secop-suite'),
            'cmpteafectado'               => __('Comprobante afectado por el asiento (referencia al comprobante origen).', 'secop-suite'),
            'fecha_asiento'               => __('Fecha del asiento presupuestal.', 'secop-suite'),
            'anio_asiento'                => __('Año del asiento presupuestal.', 'secop-suite'),
            'mes_asiento'                 => __('Mes (1-12) del asiento presupuestal.', 'secop-suite'),
            'rubro_codigo'                => __('Código del rubro presupuestal afectado.', 'secop-suite'),
            'rubro_nombre'                => __('Nombre del rubro presupuestal afectado.', 'secop-suite'),
        ];
    }

    /**
     * Diccionario completo de los conjuntos de datos publicados.
     * Solo incluye columnas que existen en la base (DESCRIBE) y nunca PII.
     */
    public function datasets(): array
    {
        $docs    = self::column_docs();
        $base    = rest_url('secop-suite/v1/');
        $secop   = $this->db->get_table_columns($this->db->get_table_name());
        $view    = $this->db->view_exists() ? $this->db->get_view_name() : '';
        $viewcol = $view !== '' ? $this->db->get_table_columns($view) : [];

        $from_columns = static function (array $cols, array $exclude) use ($docs): array {
            $fields = [];
            foreach ($cols as $name => $mysql_type) {
                if (in_array($name, $exclude, true)) {
                    continue;
                }
                $fields[] = [
                    'nombre'      => $name,
                    'tipo'        => self::type_from_mysql((string) $mysql_type),
                    'descripcion' => $docs[$name] ?? '',
                ];
            }
            return $fields;
        };

        $contract_fields = [];
        foreach (self::available_contract_fields($viewcol) as $name => $f) {
            $contract_fields[] = ['nombre' => $name, 'tipo' => $f['type'], 'descripcion' => $f['desc']];
        }

        return [
            'contratos' => [
                'titulo'      => __('Contratos SECOP', 'secop-suite'),
                'descripcion' => __('Todos los contratos de la entidad importados desde SECOP (datos.gov.co), de todas las vigencias.', 'secop-suite'),
                'unicidad'    => __('Un registro por número de contrato: la base de datos garantiza la unicidad con un índice único, por lo que la API nunca devuelve el mismo contrato dos veces.', 'secop-suite'),
                'endpoints'   => [
                    ['metodo' => 'GET', 'url' => $base . 'contracts',     'formato' => 'JSON', 'descripcion' => __('Consulta paginada (per_page máx. 100).', 'secop-suite')],
                    ['metodo' => 'GET', 'url' => $base . 'contracts/{id}', 'formato' => 'JSON', 'descripcion' => __('Un contrato por su identificador interno.', 'secop-suite')],
                    ['metodo' => 'GET', 'url' => $base . 'export/csv',    'formato' => 'CSV',  'descripcion' => __('Descarga completa (UTF-8 con BOM, separador coma).', 'secop-suite')],
                    ['metodo' => 'GET', 'url' => $base . 'export/txt',    'formato' => 'TXT',  'descripcion' => __('Descarga completa en texto de ancho fijo.', 'secop-suite')],
                ],
                'agrupaciones' => [
                    'registro' => ['descripcion' => __('Un registro por contrato.', 'secop-suite'), 'campos' => $from_columns($secop, self::PII_COLS)],
                ],
            ],
            'consulta' => [
                'titulo'      => __('Ejecución presupuestal de la contratación (vigencia actual)', 'secop-suite'),
                'descripcion' => __('Contratos de la vigencia en curso cruzados con su ejecución presupuestal en Sysman (vista vista_secop_sysman).', 'secop-suite'),
                'unicidad'    => __('Sin duplicados: primero se eliminan las filas de detalle repetidas (DISTINCT) y, con agrupar=contrato (predeterminado), se entrega una sola fila por número de contrato con los valores presupuestales sumados.', 'secop-suite'),
                'disponible'  => $view !== '',
                'endpoints'   => [
                    ['metodo' => 'GET', 'url' => $base . 'consulta',     'formato' => 'JSON', 'descripcion' => __('Consulta paginada (per_page máx. 1000).', 'secop-suite')],
                    ['metodo' => 'GET', 'url' => $base . 'consulta/csv', 'formato' => 'CSV',  'descripcion' => __('Descarga completa de la vigencia (UTF-8 con BOM, separador coma).', 'secop-suite')],
                    ['metodo' => 'GET', 'url' => $base . 'consulta/txt', 'formato' => 'TXT',  'descripcion' => __('Descarga completa de la vigencia en texto de ancho fijo.', 'secop-suite')],
                ],
                'agrupaciones' => [
                    'contrato' => ['descripcion' => __('Predeterminada. Una fila por contrato (agrupar=contrato).', 'secop-suite'), 'campos' => $contract_fields],
                    'detalle'  => ['descripcion' => __('Una fila por asiento presupuestal distinto (agrupar=detalle).', 'secop-suite'), 'campos' => $from_columns(array_intersect_key($viewcol, array_flip(self::detail_columns($viewcol))), [])],
                ],
            ],
        ];
    }

    /** Reglas generales de uso de las APIs (parámetros, límites, privacidad). */
    public static function usage(): array
    {
        return [
            'parametros' => [
                ['nombre' => 'page',            'aplica' => 'JSON',                   'descripcion' => __('Página a consultar (desde 1).', 'secop-suite')],
                ['nombre' => 'per_page',        'aplica' => 'JSON',                   'descripcion' => __('Registros por página: /contracts máx. 100 (predeterminado 10); /consulta máx. 1000 (predeterminado 100).', 'secop-suite')],
                ['nombre' => 'agrupar',         'aplica' => '/consulta',              'descripcion' => __('contrato (predeterminado): una fila por contrato · detalle: una fila por asiento presupuestal distinto.', 'secop-suite')],
                ['nombre' => '{campo}=valor',   'aplica' => __('Todos', 'secop-suite'), 'descripcion' => __('Igualdad exacta sobre cualquier campo.', 'secop-suite')],
                ['nombre' => '{campo}_like=valor', 'aplica' => __('Todos', 'secop-suite'), 'descripcion' => __('Contiene el texto (sin distinguir mayúsculas).', 'secop-suite')],
                ['nombre' => '{campo}_min=valor', 'aplica' => __('Todos', 'secop-suite'), 'descripcion' => __('Mayor o igual (números y fechas AAAA-MM-DD).', 'secop-suite')],
                ['nombre' => '{campo}_max=valor', 'aplica' => __('Todos', 'secop-suite'), 'descripcion' => __('Menor o igual (números y fechas AAAA-MM-DD).', 'secop-suite')],
                ['nombre' => 'order_by / order', 'aplica' => __('Todos', 'secop-suite'), 'descripcion' => __('Campo de orden y dirección (asc|desc). Campos no publicados se ignoran.', 'secop-suite')],
            ],
            'notas' => [
                __('Acceso público y gratuito, sin autenticación, solo lectura (método GET).', 'secop-suite'),
                __('Límite de 30 solicitudes por minuto por dirección IP; al superarlo la API responde HTTP 429.', 'secop-suite'),
                __('En /consulta los filtros se aplican a las filas de detalle (asientos) antes de agrupar por contrato.', 'secop-suite'),
                __('Las respuestas JSON de /consulta se almacenan en caché hasta 30 minutos.', 'secop-suite'),
                __('Privacidad (Ley 1581 de 2012): los documentos de identidad del contratista y del tercero nunca se publican ni pueden usarse como filtro u orden.', 'secop-suite'),
                __('Los archivos CSV usan codificación UTF-8 con BOM (compatible con Excel) y protegen las celdas contra inyección de fórmulas.', 'secop-suite'),
            ],
        ];
    }

    // ── REST: /diccionario ─────────────────────────────────────

    public function register_routes(): void
    {
        register_rest_route('secop-suite/v1', '/diccionario', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_dictionary'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_dictionary(\WP_REST_Request $request): \WP_REST_Response
    {
        if (Rate_Limiter::limited('consulta', 30)) {
            return new \WP_REST_Response(['message' => 'Demasiadas solicitudes'], 429);
        }
        return new \WP_REST_Response([
            'version'  => SECOP_SUITE_VERSION,
            'tipos'    => self::type_labels(),
            'uso'      => self::usage(),
            'datasets' => $this->datasets(),
        ]);
    }

    // ── Shortcode [secop_diccionario] ──────────────────────────

    /**
     * [secop_diccionario api="todas|contratos|consulta" ejemplos="si|no" titulo="…"]
     */
    public function render_shortcode($atts): string
    {
        $atts = shortcode_atts([
            'api'      => 'todas',
            'ejemplos' => 'si',
            'titulo'   => __('Diccionario de datos y uso de la API', 'secop-suite'),
        ], is_array($atts) ? $atts : [], 'secop_diccionario');

        $all      = $this->datasets();
        $api      = sanitize_key($atts['api']);
        $datasets = isset($all[$api]) ? [$api => $all[$api]] : $all;
        $usage    = self::usage();
        $types    = self::type_labels();
        $ejemplos = !in_array(strtolower((string) $atts['ejemplos']), ['no', '0', 'false'], true);
        $titulo   = (string) $atts['titulo'];
        $base     = rest_url('secop-suite/v1/');

        wp_enqueue_style('secop-suite-diccionario', SECOP_SUITE_URL . 'assets/css/diccionario.css', [], SECOP_SUITE_VERSION);

        ob_start();
        include SECOP_SUITE_DIR . 'templates/frontend/diccionario.php';
        return (string) ob_get_clean();
    }
}
