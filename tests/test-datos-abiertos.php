<?php
// Stubs mínimos para cargar las clases sin WordPress.
if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');
foreach (['add_action', 'add_shortcode'] as $fn) {
    if (!function_exists($fn)) { eval("function {$fn}() {}"); }
}
if (!function_exists('__')) { function __($s, $d = null) { return $s; } }
require_once dirname(__DIR__) . '/includes/class-open-data.php';
require_once dirname(__DIR__) . '/includes/class-deduplicator.php';
use SecopSuite\Open_Data;
use SecopSuite\Deduplicator;

// Columnas reales del VIEW vista_secop_sysman (class-database.php → create_view()).
$view_cols = array_fill_keys([
    'idsecop', 'idauxiliar', 'idplan', 'numero_del_contrato', 'numero_de_proceso', 'objeto_a_contratar',
    'tipo_de_contrato', 'modalidad_de_contratacion', 'fecha_de_firma_del_contrato', 'fecha_inicio_ejecucion',
    'fecha_fin_ejecucion', 'valor_contrato', 'nom_raz_social_contratista', 'documento_proveedor', 'url_contrato',
    'tercero', 'nombretercero', 'dependencia', 'nombredependencia', 'numero', 'valordebito', 'valorcredito',
    'saldoporejecutaresp', 'cmpteafectado', 'fecha_asiento', 'anio_asiento', 'mes_asiento', 'rubro_codigo', 'rubro_nombre',
], 'varchar(100)');

it('detalle de /consulta excluye PII e ids internos', function () use ($view_cols) {
    $cols = Open_Data::detail_columns($view_cols);
    foreach (array_merge(Open_Data::PII_COLS, Open_Data::SURROGATE_COLS) as $c) {
        assert_true(!in_array($c, $cols, true), "{$c} no debe publicarse");
    }
    assert_true(in_array('numero_del_contrato', $cols, true), 'numero_del_contrato presente');
});
it('detalle usa DISTINCT y agrupación por contrato usa GROUP BY', function () use ($view_cols) {
    $d = Open_Data::consulta_sql('wp_vista', $view_cols, 'detalle', []);
    assert_true(str_starts_with($d['select'], 'SELECT DISTINCT '), 'DISTINCT en detalle');
    $c = Open_Data::consulta_sql('wp_vista', $view_cols, 'contrato', ['`nombredependencia` = %s']);
    assert_true(str_contains($c['select'], 'GROUP BY d.`numero_del_contrato`'), 'una fila por contrato');
    assert_true(str_contains($c['count'], 'COUNT(DISTINCT d.`numero_del_contrato`)'), 'conteo por contrato');
    assert_true(str_contains($c['select'], 'AND `nombredependencia` = %s'), 'filtros aplicados a las filas de detalle');
    assert_eq(1, substr_count($c['select'], '%d'), 'solo el placeholder de la vigencia');
    foreach (Open_Data::PII_COLS as $pii) {
        assert_true(!in_array($pii, $c['columns'], true), "{$pii} fuera de la agrupación por contrato");
    }
});
it('campos de contrato se omiten si falta su columna en el VIEW', function () use ($view_cols) {
    $sin_rubro = $view_cols;
    unset($sin_rubro['rubro_codigo']);
    $f = Open_Data::available_contract_fields($sin_rubro);
    assert_true(!isset($f['rubros']), 'rubros depende de rubro_codigo');
    assert_true(isset($f['valor_efectivo'], $f['numero_del_contrato']), 'el resto sigue disponible');
});
it('orden de /consulta: rechaza columnas no publicadas y sanea la dirección', function () {
    $cols = ['numero_del_contrato', 'valor_efectivo', 'valordebito'];
    assert_eq('ORDER BY `valor_efectivo` DESC, `numero_del_contrato` ASC', Open_Data::consulta_order($cols, 'contrato', 'tercero', 'desc', 'valor_efectivo'));
    assert_eq('ORDER BY `valor_efectivo` DESC, `numero_del_contrato` ASC', Open_Data::consulta_order($cols, 'contrato', 'valor_efectivo', 'DESC; DROP TABLE x', 'valor_efectivo'));
    assert_eq('ORDER BY `valordebito` ASC, `numero_del_contrato` ASC', Open_Data::consulta_order($cols, 'contrato', 'valordebito', 'asc', 'valor_efectivo'));
    $det = Open_Data::consulta_order($cols, 'detalle', '', '', 'valordebito');
    assert_eq('ORDER BY `valordebito` DESC, `numero_del_contrato` ASC, `valor_efectivo` ASC', $det, 'detalle desempata por todas las columnas');
});
it('tipos publicados a partir del tipo MySQL', function () {
    assert_eq('decimal', Open_Data::type_from_mysql('decimal(20,2)'));
    assert_eq('entero', Open_Data::type_from_mysql('bigint(20) unsigned'));
    assert_eq('fecha_hora', Open_Data::type_from_mysql('datetime'));
    assert_eq('fecha', Open_Data::type_from_mysql('date'));
    assert_eq('texto', Open_Data::type_from_mysql('varchar(255)'));
});

it('huella de depuración: SHA-256 con marca de NULL y longitud', function () {
    $sql = Deduplicator::fingerprint_sql(['a', 'b']);
    assert_true(str_starts_with($sql, 'SHA2(CONCAT_WS('), 'usa SHA2');
    assert_true(str_contains($sql, "IF(`a` IS NULL, 'N', CONCAT('V', CHAR_LENGTH(CAST(`a` AS CHAR)), ':'"), 'NULL distinto de vacío, longitud prefijada');
    assert_true(str_contains($sql, '`b`'), 'incluye todas las columnas');
});
it('ids a eliminar: conserva el mayor o el menor de cada grupo', function () {
    $groups = [['ids' => '3,7,9'], ['ids' => '4'], ['ids' => '10,11']];
    assert_eq(['3', '7', '10'], Deduplicator::ids_to_delete($groups, 'max'));
    assert_eq(['7', '9', '11'], Deduplicator::ids_to_delete($groups, 'min'));
});
it('filas idénticas excluyen la PK y las marcas de tiempo automáticas', function () {
    $cols = [
        'id'                 => ['type' => 'bigint', 'default' => null, 'extra' => 'auto_increment'],
        'numero'             => ['type' => 'varchar(50)', 'default' => null, 'extra' => ''],
        'fecha_importacion'  => ['type' => 'datetime', 'default' => 'current_timestamp()', 'extra' => ''],
        'fecha_modificacion' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP', 'extra' => 'on update current_timestamp()'],
    ];
    assert_eq(['id', 'fecha_importacion', 'fecha_modificacion'], Deduplicator::auto_columns($cols, 'id'));
});
it('condición de criterio no vacío', function () {
    assert_eq("(`a` IS NOT NULL AND CAST(`a` AS CHAR) <> '') AND (`b` IS NOT NULL AND CAST(`b` AS CHAR) <> '')", Deduplicator::non_empty_sql(['a', 'b']));
});
