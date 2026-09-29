<?php
// Respaldos de configuración: funciones puras de Config_Backup (sin WordPress).
if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');
foreach (['add_action', 'add_shortcode'] as $fn) {
    if (!function_exists($fn)) { eval("function {$fn}() {}"); }
}
if (!function_exists('__')) { function __($s, $d = null) { return $s; } }
require_once dirname(__DIR__) . '/includes/class-config-backup.php';
use SecopSuite\Config_Backup;

it('shortcodes: reconoce gráficas, filtros y cards con su ID', function () {
    $found = Config_Backup::parse_shortcodes(
        'a [secop_chart id="12"] b [sdv_chart id=\'7\'] [secop_filter id=9 class="x"] '
        . '[secop_dep_chart tipo="bar" card="5"] [secop_consulta] [secop_chart height="300" id="4"]'
    );
    $refs = array_map(static fn($f) => [$f['type'], $f['id']], $found);
    assert_eq([
        ['secop_chart', 12], ['secop_chart', 7], ['secop_filter', 9],
        ['secop_dep_card', 5], [null, null], ['secop_chart', 4],
    ], $refs, 'tipo e ID de cada shortcode');
});
it('shortcodes: sin ID, con atributos parecidos o de otros plugins', function () {
    $found = Config_Backup::parse_shortcodes('[secop_dep_chart preset="dependencias"] [secop_chart data-id="3" grid="2"] [gallery id="1"] [secop_diccionario]');
    assert_eq(3, count($found), 'solo los shortcodes del plugin');
    assert_eq(null, $found[0]['id'], 'preset no apunta a una card guardada');
    assert_eq(null, $found[1]['id'], 'data-id y grid no son el atributo id');
    assert_eq('[secop_diccionario]', $found[2]['code'], 'shortcode sin atributos');
});
it('shortcodes: comillas escapadas de Elementor', function () {
    $found = Config_Backup::parse_shortcodes('{"shortcode":"[secop_filter id=\\"15\\"]"}');
    assert_eq(1, count($found), 'un shortcode');
    assert_eq(15, $found[0]['id'], 'ID leído a pesar de las comillas escapadas');
    assert_eq('[secop_filter id="15"]', $found[0]['code'], 'se muestra sin barras');
});
it('vista: acepta SHOW CREATE VIEW de MariaDB/MySQL y descarta DEFINER', function () {
    $sql = "CREATE ALGORITHM=UNDEFINED DEFINER=`usr`@`%` SQL SECURITY DEFINER VIEW `wp_vista_secop_sysman` AS select `sec`.`id` AS `idsecop` from `wp_secop_contracts` `sec` where `sec`.`valor_contrato` > 0";
    assert_eq("select `sec`.`id` AS `idsecop` from `wp_secop_contracts` `sec` where `sec`.`valor_contrato` > 0",
        Config_Backup::view_select_body($sql, 'wp_vista_secop_sysman'), 'cuerpo SELECT');
    assert_true(Config_Backup::view_select_body("CREATE VIEW wp_vista_secop_sysman AS (select 1) union (select 2)", 'wp_vista_secop_sysman') !== null, 'UNION entre paréntesis');
});
it('vista: rechaza otra vista, varias sentencias u otras órdenes', function () {
    $ok = "CREATE VIEW `wp_vista_secop_sysman` AS select 1 AS `a`";
    assert_eq(null, Config_Backup::view_select_body($ok, 'wp2_vista_secop_sysman'), 'otro prefijo');
    assert_eq(null, Config_Backup::view_select_body("CREATE VIEW `wp_otra` AS select 1", 'wp_vista_secop_sysman'), 'otra vista');
    assert_eq(null, Config_Backup::view_select_body($ok . '; DROP TABLE wp_users', 'wp_vista_secop_sysman'), 'punto y coma');
    assert_eq(null, Config_Backup::view_select_body('DROP TABLE wp_secop_contracts', 'wp_vista_secop_sysman'), 'no es CREATE VIEW');
    assert_eq(null, Config_Backup::view_select_body("CREATE VIEW `wp_vista_secop_sysman` AS delete from wp_users", 'wp_vista_secop_sysman'), 'no es SELECT');
    assert_eq(null, Config_Backup::view_select_body('', 'wp_vista_secop_sysman'), 'vacío');
});
