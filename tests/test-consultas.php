<?php
// Stubs mínimos para cargar class-visualizer.php sin WordPress.
if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');
foreach (['add_action', 'add_shortcode', 'add_filter'] as $fn) {
    if (!function_exists($fn)) { eval("function {$fn}() {}"); }
}
require_once dirname(__DIR__) . '/includes/class-visualizer.php';
use SecopSuite\Visualizer;

it('consulta personalizada: JOIN por coma reconoce todas las tablas', function () {
    assert_eq(['wp_secop_contracts'], Visualizer::referenced_tables(
        'SELECT a.tipo_de_contrato AS x_value, COUNT(*) AS y_value FROM wp_secop_contracts a, wp_secop_contracts b WHERE a.id=b.id GROUP BY a.tipo_de_contrato'));
    assert_eq(['wp_a', 'wp_b'], Visualizer::referenced_tables('SELECT * FROM wp_a AS a, `wp_b` b WHERE 1'));
});
it('consulta personalizada: FROM dentro de funciones no cuenta como tabla', function () {
    assert_eq(['wp_t'], Visualizer::referenced_tables('SELECT EXTRACT(YEAR FROM fecha) AS y, TRIM(LEADING 0 FROM codigo) c FROM wp_t GROUP BY y'));
});
it('consulta personalizada: JOIN explícitos y literales', function () {
    assert_eq(['wp_a', 'wp_b', 'wp_c'], Visualizer::referenced_tables(
        'SELECT * FROM wp_a a INNER JOIN wp_b b ON a.id=b.id LEFT JOIN `wp_c` c USING (id)'));
    assert_eq(['wp_a'], Visualizer::referenced_tables("SELECT * FROM wp_a WHERE x = 'FROM wp_users'"));
});
it('consulta personalizada: tablas de otra base se detectan (y la lista blanca las rechaza)', function () {
    assert_eq(['mysql.user'], Visualizer::referenced_tables('SELECT * FROM mysql.user'));
    assert_eq([], Visualizer::referenced_tables('SELECT 1'));
});
